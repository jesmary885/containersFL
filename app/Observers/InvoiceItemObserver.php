<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\InvoiceItem;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * QUÉ VIGILA: las líneas de la factura.
 * QUÉ HACE:   vuelve a sumar los totales cada vez que una línea cambia.
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Sin esto, alguien edita el precio de una línea y el total de arriba
 * sigue mostrando el valor viejo. El cliente paga lo que dice el total y
 * la contabilidad no cuadra.
 *
 * ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──
 *
 * Antes buscaba la factura con $item->invoice. Eso funciona bien mientras
 * haya alguien con sesión abierta, pero se rompe en silencio fuera del
 * navegador: en un comando programado o en una cola no hay compañía
 * activa, el filtro de BelongsToCompany devuelve cero filas, y el método
 * se va sin recalcular nada.
 *
 * No lanza error. Simplemente la factura se queda con el total viejo.
 *
 * Ahora se busca con allCompanies(), que apaga ese filtro. Es la misma
 * corrección que ya lleva el observer de las líneas del presupuesto.
 */
class InvoiceItemObserver
{
    /**
     * Se dispara al crear una línea Y al modificarla.
     *
     * Se usa 'saved' en vez de 'created' + 'updated' porque los dos casos
     * necesitan exactamente lo mismo: volver a sumar.
     */
    public function saved(InvoiceItem $item): void
    {
        $this->recalcular($item);
    }

    /**
     * Si se borra una línea, el total tiene que bajar.
     * Es el caso que más se olvida cuando esto se hace a mano.
     */
    public function deleted(InvoiceItem $item): void
    {
        $this->recalcular($item);
    }

    /**
     * El recálculo real, con tres protecciones en orden:
     */
    protected function recalcular(InvoiceItem $item): void
    {
        /* -----------------------------------------------------------------
         | 1 · ¿La factura todavía existe?
         |
         | Aunque las facturas no se borran, sus líneas sí desaparecen en
         | cascada si algún día alguien fuerza un borrado desde la base.
         | Sin esta comprobación: "Call to a member function on null".
         |
         | allCompanies() apaga el filtro por compañía, por el motivo
         | explicado arriba.
         * -------------------------------------------------------------- */
        $factura = Invoice::query()
            ->allCompanies()
            ->find($item->invoice_id);

        if (! $factura) {
            return;
        }

        /* -----------------------------------------------------------------
         | 2 · ¿Está bloqueada?
         |
         | Una factura pagada o anulada NO se recalcula. Si el cliente ya
         | pagó $2,750 y alguien toca una línea, el total no puede moverse:
         | eso es un documento fiscal emitido y cobrado.
         * -------------------------------------------------------------- */
        if ($factura->isLocked()) {
            return;
        }

        /* -----------------------------------------------------------------
         | 3 · Se recargan las líneas antes de sumar.
         |
         | El modelo tiene en memoria la lista de ANTES del cambio. Sin
         | volver a leerla de la base, sumaría los valores viejos y el
         | total quedaría un paso atrás de lo que se ve en pantalla.
         * -------------------------------------------------------------- */
        $factura->load('items');

        // Por dentro usa saveQuietly(), así que no despierta a nadie más.
        $factura->recalculate();
    }
}
