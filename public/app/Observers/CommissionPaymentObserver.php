<?php

namespace App\Observers;

use App\Models\CommissionPayment;
use App\Models\Commission;

/*

**Qué vigila:** los abonos a comisiones de vendedores.
**Qué hace:** actualiza cuánto se le sigue debiendo al vendedor.
*/


/**
 * Mismo patrón exacto que ExpensePaymentObserver, pero para las
 * comisiones de vendedores (RB-030).
 *
 * Responde a la pregunta "¿cuánto le debo a Juan este mes?" sin que
 * nadie tenga que sumar a mano.
 */

class CommissionPaymentObserver
{
    public function saved(CommissionPayment $payment): void
    {
        $this->recalcular($payment);
    }

    public function deleted(CommissionPayment $payment): void
    {
        $this->recalcular($payment);
    }

    protected function recalcular(CommissionPayment $payment): void
    {
        $afectadas = array_unique(array_filter([
            $payment->commission_id,
            $payment->getOriginal('commission_id'),
        ]));

        foreach ($afectadas as $commissionId) {
            Commission::query()
                ->allCompanies()
                ->find($commissionId)
                ?->recalculateBalance();
        }
    }
}
