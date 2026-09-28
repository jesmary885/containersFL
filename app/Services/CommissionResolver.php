<?php

namespace App\Services;

use App\Enums\CommissionBase;
use App\Enums\CommissionMode;
use App\Enums\ProductType;
use App\Enums\CommissionStatus;
use App\Models\Commission;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * DE LA FACTURA A LA COMISIÓN
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── POR QUÉ HACE FALTA ESTA CLASE ──
 *
 * `commissions`, `commission_payments`, `CommissionPaymentObserver` y
 * `Commission::recalculateBalance()` ya estaban construidos. El
 * porcentaje, el monto fijo y los abonos parciales, todo resuelto.
 *
 * Lo que no existía era nada que CREARA una comisión. Ni una línea en
 * todo el proyecto escribía una fila en `commissions`. La mecánica
 * completa estaba ahí, sin arrancar.
 *
 * ── SOBRE QUÉ MONTO SE COMISIONA ──
 *
 * Sobre el subtotal, no sobre el total.
 *
 * El total lleva sales tax y recargo de tarjeta. El tax es dinero del
 * estado de Florida que pasa por la cuenta; comisionar sobre él sería
 * pagarle al vendedor un porcentaje de un impuesto. El recargo de
 * tarjeta es lo que cobra la procesadora, y tampoco es venta.
 *
 * El transporte SÍ entra en la base. Es facturación de la empresa y en
 * la hoja VENTAS del Excel la comisión de $150 sale de una venta de
 * $2,650 que incluye el delivery.
 *
 * ⚠️ CONFIRMAR: si comisionan solo sobre el contenedor y no sobre el
 * transporte, hay que cambiar baseAmount() por la suma de las líneas
 * cuyo producto sea de tipo container.
 *
 * ── CUÁNDO SE CREA ──
 *
 * Al EMITIR la factura, no al cobrarla. Así el vendedor ve lo que va
 * ganando aunque el cliente aún no haya pagado, que es la pregunta que
 * hace todos los días. Nace en estado `pending`; pagarla es otro acto,
 * con sus abonos.
 *
 * Es idempotente: si la factura ya tiene comisión, la actualiza en vez de
 * crear una segunda. Emitir dos veces no duplica la deuda con el
 * vendedor.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class CommissionResolver
{
    /**
     * Crea o actualiza la comisión de una factura.
     *
     * Devuelve null cuando la factura no genera comisión: sin vendedor,
     * sin modo pactado, o con monto cero. Una renta que se factura sola
     * cada mes no la cierra nadie.
     */
    public function syncFromInvoice(Invoice $invoice): ?Commission
    {
        /* -----------------------------------------------------------------
         | EL VENDEDOR ES UN TRABAJADOR — CORREGIDO 25-09
         |
         | Antes esta línea miraba `salesperson_id`, que apunta a USUARIOS
         | del sistema. El formulario de la factura nunca la llenaba: guarda
         | el vendedor en `sold_by_employee_id`, que apunta a TRABAJADORES.
         |
         | Resultado: el resolver salía por acá siempre y NO SE CREÓ NUNCA
         | UNA SOLA COMISIÓN. Se elegía el vendedor, se emitía la factura, y
         | no aparecía nada.
         |
         | Gana trabajadores porque es lo que dice el negocio: Miguelito
         | vende desde 2024 y probablemente nunca ha abierto el sistema.
         * -------------------------------------------------------------- */
        if (! $invoice->sold_by_employee_id || ! $invoice->commission_mode) {
            return null;
        }

        $modo  = $invoice->commission_mode;
        $base  = $this->baseAmount($invoice);
        $monto = $modo->resolveAmount(
            $base,
            $invoice->commission_percent !== null ? (float) $invoice->commission_percent : null,
            $invoice->commission_amount !== null ? (float) $invoice->commission_amount : null,
        );

        if ($monto <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $modo, $base, $monto) {

            $existente = Commission::where('invoice_id', $invoice->id)->first();

            /*
             | Una comisión que ya se empezó a pagar no se toca.
             |
             | Si se le abonaron $150 de $300 y alguien corrige la factura
             | a $200, recalcular dejaría la comisión con más pagado que
             | debido. Se avisa en las notas y se deja que una persona
             | decida.
             */
            if ($existente && (float) $existente->paid_amount > 0) {
                $existente->update([
                    'notes' => trim(($existente->notes ?? '')."\n"
                        .'⚠ La factura cambió el '.now()->format('d/m/Y')
                        .'. Monto recalculado: $'.number_format($monto, 2)
                        .'. No se aplicó porque ya tiene abonos.'),
                ]);

                return $existente;
            }

            $datos = [
                'company_id'     => $invoice->company_id,
                'invoice_id'     => $invoice->id,
                'sale_id'        => $invoice->sale_id,
                'salesperson_id' => $invoice->salesperson_id,
                'employee_id'    => $invoice->sold_by_employee_id,
                'sale_date'      => $invoice->issue_date,

                'base_amount'    => $base,
                'base_type'      => $invoice->commission_base instanceof CommissionBase
                    ? $invoice->commission_base->value
                    : (string) ($invoice->commission_base ?: CommissionBase::Subtotal->value),
                'mode'           => $modo->value,
                'percent'        => $modo === CommissionMode::Percent
                    ? $invoice->commission_percent
                    : $modo->equivalentPercent($base, $monto),
                'amount'         => $monto,
            ];

            if ($existente) {
                $existente->update($datos);
                $existente->recalculateBalance();

                return $existente;
            }

            $comision = Commission::create($datos + [
                'status'     => CommissionStatus::Pending,
                'created_by' => auth()->id(),
            ]);

            $comision->recalculateBalance();

            return $comision;
        });
    }

    /**
     * El monto sobre el que se comisiona.
     *
     * Subtotal menos descuento. Sin tax y sin recargo de tarjeta: ver la
     * explicación de arriba.
     */
    public function baseAmount(Invoice $invoice): float
    {
        $base = $invoice->commission_base instanceof CommissionBase
            ? $invoice->commission_base
            : CommissionBase::tryFrom((string) $invoice->commission_base);

        /* -----------------------------------------------------------------
         | SOLO LOS CONTENEDORES — REUNIÓN 16-09
         |
         | Denisse dijo que el vendedor principal cobra un porcentaje
         | "aplicado específicamente en unidades de venta directa". Con esta
         | opción el delivery y los servicios quedan fuera de la base.
         |
         | En una venta de $2,650 con $650 de delivery, al 5%, la diferencia
         | es $132.50 contra $100.00. Por venta.
         |
         | El descuento se reparte a prorrata entre lo que sí comisiona:
         | si se descontaron $100 de una venta donde los contenedores son el
         | 75% del subtotal, la base baja $75 y no $100. Restarlo entero
         | castigaría al vendedor por un descuento que también afectó al
         | transporte, que no es suyo.
         * -------------------------------------------------------------- */
        if ($base === CommissionBase::Containers) {

            $subtotal = (float) $invoice->subtotal;

            $deContenedores = (float) $invoice->items()
                ->whereHas('product', fn ($q) => $q->where('type', ProductType::Container->value))
                ->sum('amount');

            $descuento = (float) $invoice->discount_amount;

            $proporcion = $subtotal > 0 ? $deContenedores / $subtotal : 0;

            return max(0, round($deContenedores - ($descuento * $proporcion), 2));
        }

        // Por defecto: toda la venta menos el descuento.
        return max(0, round(
            (float) $invoice->subtotal - (float) $invoice->discount_amount,
            2,
        ));
    }
}
