<?php

namespace App\Observers;

use App\Models\Estimate;
use App\Models\EstimateItem;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * QUÉ VIGILA: las líneas del presupuesto.
 * QUÉ HACE:   vuelve a sumar los totales cada vez que una línea cambia.
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Es el gemelo de InvoiceItemObserver, que ya existe para las facturas.
 *
 * ── POR QUÉ HACE FALTA SI LA PANTALLA YA SUMA ──
 *
 * Porque la pantalla no es el único sitio desde donde se tocan las líneas.
 * También las toca duplicate(), y mañana las tocará una importación o una
 * corrección desde tinker.
 *
 * Si la suma viviera solo en el formulario, cualquier cambio hecho por
 * otro camino dejaría el encabezado diciendo $2,750 y las líneas sumando
 * $3,100. Y ese descuadre no avisa: simplemente el documento está mal.
 */
class EstimateItemObserver
{
    /**
     * Se dispara al crear una línea Y al modificarla.
     *
     * Se usa 'saved' en vez de 'created' + 'updated' porque los dos casos
     * necesitan exactamente lo mismo: volver a sumar.
     */
    public function saved(EstimateItem $item): void
    {
        $this->recalcular($item);
    }

    /**
     * Si se borra una línea, el total tiene que bajar.
     * Es el caso que más se olvida cuando esto se hace a mano.
     */
    public function deleted(EstimateItem $item): void
    {
        $this->recalcular($item);
    }

    /**
     * El recálculo real, con tres protecciones en orden:
     */
    protected function recalcular(EstimateItem $item): void
    {
        /* -----------------------------------------------------------------
         | 1 · ¿El presupuesto todavía existe?
         |
         | Cuando se borra el presupuesto completo, la base borra sus
         | líneas en cascada. Para cuando este método se entera, el padre
         | ya no está.
         |
         | Sin esta comprobación: "Call to a member function on null".
         |
         | allCompanies() apaga el filtro por compañía. Hace falta porque
         | este vigilante puede correr desde un comando programado, donde
         | no hay sesión y por tanto no hay compañía activa: sin apagarlo,
         | la consulta devolvería cero filas y el total se quedaría sin
         | actualizar, en silencio.
         * -------------------------------------------------------------- */
        $presupuesto = Estimate::query()
            ->allCompanies()
            ->find($item->estimate_id);

        if (! $presupuesto) {
            return;
        }

        /* -----------------------------------------------------------------
         | 2 · ¿Está cerrado?
         |
         | Un presupuesto convertido en factura ya no se recalcula. Sus
         | números son los que aprobó el cliente y los que se copiaron a
         | la factura.
         * -------------------------------------------------------------- */
        if ($presupuesto->status->isClosed()) {
            return;
        }

        /* -----------------------------------------------------------------
         | 3 · Se recargan las líneas antes de sumar.
         |
         | El modelo tiene en memoria la lista de ANTES del cambio. Sin
         | volver a leerla de la base, sumaría los valores viejos y el
         | total quedaría un paso atrás de lo que se ve en pantalla.
         * -------------------------------------------------------------- */
        $presupuesto->load('items');

        // Por dentro usa saveQuietly(), así que no despierta a nadie más.
        $presupuesto->recalculate();
    }
}
