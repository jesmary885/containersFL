<?php

namespace App\Observers;

use App\Models\Expense;
use App\Models\ExpensePayment;

/*
**Qué vigila:** los abonos a gastos.
**Qué hace:** actualiza el saldo pendiente del gasto.
*/


/**
 * Mantiene el saldo de las cuentas por pagar (RB-037).
 *
 * Un gasto de 3,000 puede pagarse en tres partes. La pantalla de
 * cuentas por pagar tiene que mostrar cuánto falta, y ese número no
 * puede depender de que alguien se acuerde de actualizarlo.
 */

class ExpensePaymentObserver
{
     public function saved(ExpensePayment $payment): void
    {
        $this->recalcular($payment);
    }

    public function deleted(ExpensePayment $payment): void
    {
        $this->recalcular($payment);
    }

    /**
     * Igual que con los gastos y los contenedores: si alguien movió el
     * abono de un gasto a otro, hay que recalcular los dos.
     */
    protected function recalcular(ExpensePayment $payment): void
    {
        $afectados = array_unique(array_filter([
            $payment->expense_id,
            $payment->getOriginal('expense_id'),
        ]));

        foreach ($afectados as $expenseId) {
            // allCompanies porque el observer puede correr desde un
            // comando sin compañía activa, y ahí el scope devolvería
            // nada y el saldo se quedaría sin actualizar.
            Expense::query()
                ->allCompanies()
                ->find($expenseId)
                ?->recalculateBalance();
        }
    }
}
