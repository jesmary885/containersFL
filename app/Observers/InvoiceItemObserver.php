<?php

namespace App\Observers;

use App\Models\InvoiceItem;

/**
 * Mantiene los totales de la factura siempre cuadrados con sus líneas.
 *
 * Sin esto, alguien edita el precio de una línea y el total de arriba
 * sigue mostrando el valor viejo. El cliente paga lo que dice el total
 * y la contabilidad no cuadra.
 */


class InvoiceItemObserver
{
    /**
     * Se dispara al crear una línea Y al modificarla.
     *
     * Usamos 'saved' en vez de 'created' + 'updated' porque los dos casos
     * necesitan exactamente lo mismo: volver a sumar.
     */
    public function saved(InvoiceItem $item): void
    {
        $this->recalculate($item);
    }

    /**
     * Si se borra una línea, el total tiene que bajar.
     * Es el caso que más se olvida cuando se hace a mano.
     */
    public function deleted(InvoiceItem $item): void
    {
        $this->recalculate($item);
    }

    /**
     * El recálculo real.
     *
     * Tres protecciones, en orden:
     *
     * 1. ¿La factura existe? Si estamos borrando la factura completa,
     *    Laravel borra las líneas en cascada y para cuando llega acá
     *    el padre ya no está. Sin este check: "Call to a member
     *    function on null".
     *
     * 2. ¿Está bloqueada? Una factura pagada o anulada NO se recalcula.
     *    Si el cliente ya pagó 2,750 y alguien toca una línea, el total
     *    no puede moverse: eso es un documento fiscal emitido.
     *
     * 3. refresh() de las líneas: el modelo tiene en memoria la lista
     *    de antes del cambio. Sin recargarla sumaría los valores viejos.
     */
    protected function recalculate(InvoiceItem $item): void
    {
        $invoice = $item->invoice;

        if (! $invoice) {
            return;
        }

        if ($invoice->isLocked()) {
            return;
        }

        $invoice->load('items');
        $invoice->recalculate();   // por dentro usa saveQuietly()
    }
}
