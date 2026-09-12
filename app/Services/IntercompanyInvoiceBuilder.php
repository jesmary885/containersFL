<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Trip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LA FACTURA SEMANAL ENTRE LAS DOS COMPAÑÍAS (RB-003)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── LA DECISIÓN, POR FIN ESCRITA ──
 *
 * El sistema hace presupuestos y facturas para LAS DOS compañías. Sin
 * QuickBooks.
 *
 * Hoy el cliente lleva la compañía de transporte en QuickBooks y la de
 * contenedores en Excel. Como el sistema tiene que reemplazar el Excel —o
 * sea hacer los documentos de contenedores— hacerlos también para
 * transporte no agrega arquitectura: es el mismo módulo con otro
 * `company_id`.
 *
 * Esto queda escrito acá porque la minuta del 8 de agosto dice lo
 * contrario ("opción preferida por ambos: dejar presupuesto y factura en
 * QuickBooks y consumir su API"). La decisión cambió después. Quien lea
 * esa minuta sin leer esto va a pensar que el módulo de facturación está
 * fuera de alcance.
 *
 * ── QUÉ HACE ESTA CLASE ──
 *
 * R.S. Transport le emite a FLCHR una factura por semana con todos los
 * viajes que hizo para ella. Un renglón por viaje.
 *
 * Todo lo que hace falta estaba construido y nada lo usaba:
 *
 *   InvoiceType::Intercompany              el tipo de documento
 *   trips.intercompany_invoice_id          el vínculo
 *   Trip::pendingIntercompanyBilling()     el scope de los que faltan
 *   companies.customer_id                  FLCHR como cliente de RST
 *   customers.related_company_id           el camino de vuelta
 *   Invoice::trips()                       los viajes de la factura
 *   Company::nextNumber('invoice')         numeración propia por compañía
 *
 * Es el mismo patrón que las comisiones: la mecánica entera montada, sin
 * una sola línea que la arrancara.
 *
 * ── POR QUÉ UN RENGLÓN POR VIAJE Y NO UN TOTAL ──
 *
 * Porque FLCHR tiene que poder auditar la factura. Un renglón que diga
 * "servicios de transporte de la semana — $4,200" no se puede verificar
 * contra nada. Con un renglón por viaje, cada uno con su número de
 * contenedor y su ruta, la factura se comprueba viaje por viaje.
 *
 * Y porque es lo que exige el otro lado del asiento: FLCHR registra esa
 * factura como gasto, y un gasto de $4,200 sin desglose no se puede
 * repartir entre las ventas que lo generaron.
 *
 * ── SIN SALES TAX ──
 *
 * RB-005: el transporte no paga sales tax en Florida. Todos los renglones
 * nacen con `taxable = false`, así que el tax de esta factura es siempre
 * cero. No es una omisión.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IntercompanyInvoiceBuilder
{
    /**
     * Arma la factura de los viajes pendientes de un período.
     *
     * Devuelve null si no hay viajes que facturar. Una semana sin viajes
     * no genera una factura de $0: genera nada.
     */
    public function build(
        Company $transportista,
        Company $contenedores,
        Carbon $desde,
        Carbon $hasta,
    ): ?Invoice {

        /* -----------------------------------------------------------------
         | 1 · FLCHR TIENE QUE EXISTIR COMO CLIENTE
         |
         | Una factura necesita un cliente, y el cliente de esta es la
         | otra compañía. El CustomerSeeder ya crea esa ficha; si falta,
         | es mejor un error que se entienda que un fallo de SQL.
         * -------------------------------------------------------------- */
        $cliente = $contenedores->customer;

        if (! $cliente) {
            throw new \RuntimeException(
                $contenedores->name.' no tiene ficha de cliente. Sin ella no se le '
                .'puede emitir la factura intercompañía. Revisa companies.customer_id.',
            );
        }

        /* -----------------------------------------------------------------
         | 2 · LOS VIAJES QUE FALTAN
         |
         | Completados, del período, y sin factura intercompañía todavía.
         | El scope ya existía; nadie lo llamaba.
         |
         | Se ordenan por fecha para que la factura se lea como un
         | recorrido cronológico, no como el orden de inserción.
         * -------------------------------------------------------------- */
        $viajes = Trip::query()
            ->where('company_id', $transportista->id)
            ->pendingIntercompanyBilling()
            ->whereBetween('completed_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->with('container:id,internal_code')
            ->orderBy('completed_at')
            ->get();

        if ($viajes->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($transportista, $cliente, $viajes, $desde, $hasta) {

            $producto = Product::where('code', 'DELIVERY')
                ->forCompany($transportista->id)
                ->first();

            $factura = Invoice::create([
                'company_id'  => $transportista->id,
                'customer_id' => $cliente->id,

                'invoice_number' => $transportista->nextNumber('invoice'),
                'type'           => InvoiceType::Intercompany,
                'status'         => InvoiceStatus::Draft,

                'issue_date' => now()->toDateString(),
                'terms'      => 'net_30',

                /*
                 | La dirección se copia de la ficha de la compañía, no de
                 | la del cliente: el documento tiene que decir a nombre
                 | de quién se emitió el día que se emitió (RB-058).
                 */
                'bill_to' => $this->direccionDe($cliente),

                /*
                 | Sin sales tax. RB-005: el transporte no lo paga en
                 | Florida. Se pone explícito y no se deja al default de
                 | la compañía, que es 7%.
                 */
                'tax_rate'   => 0,
                'tax_exempt' => false,

                'notes' => 'Viajes del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y')
                    .'. '.$viajes->count().' viaje(s).',

                'created_by' => auth()->id(),
            ]);

            /* -----------------------------------------------------------------
             | 3 · UN RENGLÓN POR VIAJE
             |
             | La descripción lleva el contenedor y la ruta, que es lo que
             | permite auditar la factura del otro lado.
             * -------------------------------------------------------------- */
            foreach ($viajes as $i => $viaje) {
                $factura->items()->create([
                    'line_number'  => $i + 1,
                    'product_id'   => $producto?->id,
                    'container_id' => $viaje->container_id,

                    'description' => $this->describirViaje($viaje),

                    'quantity'   => 1,
                    'unit_price' => $viaje->customer_price,

                    // RB-005 · el transporte no paga sales tax
                    'taxable' => false,

                    'sort_order' => $i + 1,
                ]);

                // El vínculo de vuelta: este viaje ya se facturó.
                $viaje->update(['intercompany_invoice_id' => $factura->id]);
            }

            $factura->load('items')->recalculate();

            return $factura;
        });
    }

    /**
     * El texto del renglón.
     *
     * Fecha, contenedor y ruta. Sin el contenedor, FLCHR no puede
     * asociar el gasto a la venta que lo generó.
     */
    protected function describirViaje(Trip $viaje): string
    {
        $partes = array_filter([
            $viaje->completed_at?->format('d/m'),
            $viaje->container?->internal_code,
            $this->ciudadDe($viaje->origin_address),
            $this->ciudadDe($viaje->destination_address) ?: $viaje->destination_zip,
        ]);

        $texto = implode(' · ', $partes);

        if ($viaje->miles) {
            $texto .= ' ('.rtrim(rtrim(number_format((float) $viaje->miles, 1), '0'), '.').' mi)';
        }

        return $texto ?: 'Viaje '.$viaje->trip_number;
    }

    /**
     * La ciudad de una dirección JSON, para el renglón.
     *
     * Solo la ciudad y no la dirección completa: en una factura de
     * cuarenta viajes, cada renglón con calle y número la vuelve
     * ilegible. El detalle está en la ficha del viaje.
     */
    protected function ciudadDe(?array $direccion): ?string
    {
        if (! $direccion) {
            return null;
        }

        return collect([$direccion['city'] ?? null, $direccion['state'] ?? null])
            ->filter()
            ->implode(', ') ?: null;
    }

    /**
     * La dirección de facturación del cliente, congelada en el documento.
     *
     * Las direcciones de un cliente viven en customer_addresses, no en
     * columnas planas de customers. Se busca la de facturación y, si no
     * hay, la primera que exista: una factura sin bill_to no se guarda,
     * porque invoices.bill_to no es nullable.
     */
    protected function direccionDe(\App\Models\Customer $cliente): array
    {
        /*
         | El orden importa: 'billing' primero, 'both' después.
         |
         | 'both' TAMBIÉN sirve para facturar —así está documentado en la
         | migración— así que ordenar solo por 'billing' dejaría fuera al
         | cliente que tiene una única dirección marcada 'both', que es el
         | caso normal.
         */
        $dir = $cliente->addresses()
            ->orderByRaw("CASE type WHEN 'billing' THEN 0 WHEN 'both' THEN 1 ELSE 2 END")
            ->first();

        return [
            'label' => $cliente->display_name,
            'line1' => $dir?->line1,
            'line2' => $dir?->line2,
            'city'  => $dir?->city,
            'state' => $dir?->state,
            'zip'   => $dir?->zip,
        ];
    }
}
