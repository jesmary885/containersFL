<?php

namespace App\Observers;

use App\Models\PurchaseItem;
use App\Models\Purchase;

/**
 * Mantiene el estado y los totales de la compra.
 *
 * El estado de una compra NO se elige en un desplegable: se deriva de
 * cuántas unidades se han retirado del depósito.
 *
 *   0 de 10 retiradas       → open
 *   4 de 10 retiradas       → partially_received
 *   10 de 10 retiradas      → received
 *
 * Así es imposible tener una compra marcada "recibida" con unidades
 * pendientes en el depósito, que es exactamente el error que hace que
 * te cobren fee diario sin darte cuenta (RB-020).
 */

class PurchaseItemObserver
{
    /**
     *
    **Qué vigila:** las líneas de compra.
    **Qué hace:** deriva el estado de la compra y sus totales.
     */
 public function saved(PurchaseItem $item): void
    {
        $this->actualizarCompra($item->purchase_id);
    }

    public function deleted(PurchaseItem $item): void
    {
        $this->actualizarCompra($item->purchase_id);
    }

    protected function actualizarCompra(?int $purchaseId): void
    {
        if (! $purchaseId) {
            return;
        }

        $purchase = Purchase::query()->allCompanies()->find($purchaseId);

        if (! $purchase) {
            return;   // se está borrando la compra completa en cascada
        }

        // 1. El estado, derivado de las unidades recibidas.
        $purchase->refreshStatus();

        // 2. Los totales, derivados de las líneas.
        $this->recalcularTotales($purchase);
    }

    /**
     * Suma las líneas y agrega el fee de recogida.
     *
     * El pickup_fee va aparte porque no es de ninguna línea en
     * particular: es lo que cobra el depósito por sacar la mercancía,
     * y es fijo por depósito (RB-031).
     */
    protected function recalcularTotales(Purchase $purchase): void
    {
        $subtotal = (float) $purchase->items()->sum('total_cost');

        $purchase->subtotal = round($subtotal, 2);

        $purchase->total = round(
            $subtotal
            + (float) $purchase->pickup_fee
            + (float) $purchase->tax_amount,
            2,
        );

        // saveQuietly para no despertar a nadie más.
        $purchase->saveQuietly();
    }
}
