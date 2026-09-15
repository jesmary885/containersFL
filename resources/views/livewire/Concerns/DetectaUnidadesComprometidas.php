<?php

namespace App\Livewire\Concerns;

use App\Enums\ContainerStatus;
use App\Models\ContainerMovement;
use App\Models\InvoiceItem;
use Carbon\Carbon;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UNIDADES YA COMPROMETIDAS EN UNA FACTURA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── EL PROBLEMA QUE RESUELVE ──
 *
 * Se podía cotizar un contenedor que ya estaba facturado y a punto de
 * cobrarse. A veces avisaba y a veces no, y esa inconsistencia tenía una
 * explicación exacta:
 *
 * El buscador de unidades solo miraba el ESTADO del contenedor, a través
 * del scope available() —en yarda y sin venta ni renta encima—. Y el
 * estado solo cambia si la factura lo movió.
 *
 * Facturar mueve la unidad desde hace poco, y solo cuando el concepto del
 * renglón es venta o renta. Todo lo facturado antes de eso dejó el
 * contenedor diciendo "en yarda", y el buscador lo seguía ofreciendo como
 * si estuviera libre.
 *
 * El aviso que sí salía a veces era otro: el de "ya cotizada en otro
 * presupuesto". Ese mira presupuestos, no facturas. Por eso funcionaba
 * cuando el choque era con una cotización y guardaba silencio cuando era
 * con una factura, que es el caso grave.
 *
 * ── QUÉ HACE ESTE TRAIT ──
 *
 * Pregunta directamente a los renglones de factura, sin depender de que
 * el estado esté al día. Si una unidad está en una factura viva con un
 * concepto de venta o de renta, queda bloqueada en el buscador y se dice
 * en qué factura.
 *
 * ── EL DETALLE QUE EVITA BLOQUEARLA PARA SIEMPRE ──
 *
 * Una renta termina y el contenedor vuelve a la yarda. Si contáramos
 * cualquier factura pasada, esa unidad no se podría volver a ofrecer
 * nunca.
 *
 * Por eso solo cuentan las facturas emitidas DESPUÉS de la última vez que
 * la unidad entró a la yarda. Registrar la devolución con el botón
 * "Mover" la libera sola, sin tocar nada más.
 */
trait DetectaUnidadesComprometidas
{
    /**
     * Las unidades del buscador que ya están facturadas.
     *
     * Devuelve [container_id => Invoice].
     */
    public function getComprometidasEnFacturasProperty(): array
    {
        $ids = $this->resultadosContenedor->pluck('id')->all();

        if (empty($ids)) {
            return [];
        }

        /* -----------------------------------------------------------------
         | LA ÚLTIMA VEZ QUE CADA UNIDAD ENTRÓ A LA YARDA
         |
         | Es la línea que separa "lo que ya se cobró" de "lo que se cobró
         | antes de que volviera".
         |
         | Siempre hay al menos un movimiento así: el alta del contenedor
         | deja un asiento de recepción. Si por lo que sea no lo hubiera,
         | se cuentan todas las facturas, que es el lado prudente.
         * -------------------------------------------------------------- */
        $ultimaEntrada = ContainerMovement::query()
            ->whereIn('container_id', $ids)
            ->where('status_after', ContainerStatus::InYard->value)
            ->selectRaw('container_id, MAX(moved_at) as entrada')
            ->groupBy('container_id')
            ->pluck('entrada', 'container_id');

        return InvoiceItem::query()
            ->whereIn('container_id', $ids)

            /*
             | Una factura anulada no compromete nada: su número queda
             | consumido y con su motivo, pero el contenedor vuelve a
             | estar libre.
             */
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))

            ->with([
                'invoice:id,invoice_number,status,issue_date,balance_due',
                'product:id,code,name',
            ])
            ->get()

            /* -----------------------------------------------------------------
             | SOLO VENTA Y RENTA
             |
             | Una línea de reparación o de almacenaje menciona el
             | contenedor pero no lo compromete: se le arregló el piso y
             | sigue en venta.
             |
             | Se filtra aquí y no en la consulta porque quién es venta y
             | quién es renta lo decide el propio catálogo
             | (Product::isSale / isRental), y ahí es donde tiene que
             | seguir decidiéndose el día que se agregue un concepto nuevo.
             * -------------------------------------------------------------- */
            ->filter(fn (InvoiceItem $linea) => $linea->product?->isSale()
                                             || $linea->product?->isRental())

            /* -----------------------------------------------------------------
             | Y SOLO LAS POSTERIORES A LA ÚLTIMA ENTRADA A LA YARDA
             * -------------------------------------------------------------- */
            ->filter(function (InvoiceItem $linea) use ($ultimaEntrada) {

                $entrada = $ultimaEntrada[$linea->container_id] ?? null;

                if (! $entrada || ! $linea->invoice?->issue_date) {
                    return true;
                }

                return $linea->invoice->issue_date->gte(
                    Carbon::parse($entrada)->startOfDay(),
                );
            })

            /*
             | Si hay varias, se nombra la más reciente. Listar todas
             | alargaría el renglón y no cambia la decisión: lo que
             | importa es que YA está comprometida.
             */
            ->sortByDesc('id')
            ->groupBy('container_id')
            ->map(fn ($lineas) => $lineas->first()->invoice)
            ->filter()
            ->all();
    }
}
