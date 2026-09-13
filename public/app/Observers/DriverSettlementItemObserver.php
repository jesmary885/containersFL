<?php

namespace App\Observers;

use App\Models\DriverSettlement;
use App\Models\DriverSettlementItem;

class DriverSettlementItemObserver
{
    /**
     * **Qué vigila:** las líneas de liquidación de choferes.
        **Qué hace:** recalcula el bruto, las deducciones y el neto a pagar.
     */



    /**
     * Mantiene los totales de la liquidación del chofer.
     *
     * La liquidación tiene líneas positivas (lo que ganó por sus viajes,
     * RB-032) y negativas (adelantos, combustible, daños). El neto es la
     * resta, y tiene que estar siempre al día porque es lo que se le
     * transfiere al chofer.
     */
   /**
     * Antes de agregar o modificar una línea: verificar que la
     * liquidación siga abierta.
     *
     * Una liquidación aprobada ya se pagó y sus viajes quedaron
     * marcados. Meterle una línea después cambiaría un monto que ya
     * salió del banco.
     */
    public function saving(DriverSettlementItem $item): void
    {
        $settlement = $item->settlement;

        if ($settlement && ! $settlement->isEditable()) {
            throw new \RuntimeException(
                'La liquidación '.$settlement->settlement_number
                .' ya fue aprobada y no admite cambios.',
            );
        }
    }

    public function saved(DriverSettlementItem $item): void
    {
        $this->recalcular($item);
    }

    public function deleted(DriverSettlementItem $item): void
    {
        $this->recalcular($item);
    }

    protected function recalcular(DriverSettlementItem $item): void
    {
        $afectadas = array_unique(array_filter([
            $item->driver_settlement_id,
            $item->getOriginal('driver_settlement_id'),
        ]));

        foreach ($afectadas as $settlementId) {
            DriverSettlement::query()
                ->allCompanies()
                ->find($settlementId)
                ?->recalculateTotals();
        }
    }
}
