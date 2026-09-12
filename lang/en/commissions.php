<?php

/*
|--------------------------------------------------------------------------
| COMISIONES DE VENTA
|--------------------------------------------------------------------------
|
| Dos personas distintas en cada documento:
|
|   Registró    quien tecleó el presupuesto o la factura
|   Cerró       quien hizo la venta y cobra la comisión
|
| No son la misma. Denisse registra desde administración y la venta la
| cerró Miguelito. El sistema guarda las dos por separado: created_by y
| salesperson_id.
|
*/

return [

    'title'            => 'Commissions',
    'registered_by'    => 'Entered by',
    'closed_by'        => 'Closed by',

    /* ── Cómo se calcula ── */
    'mode'             => 'How it is calculated',
    'mode_percent'     => 'Percentage of sale',
    'mode_fixed'       => 'Agreed amount',
    'percent'          => 'Percentage',
    'fixed_amount'     => 'Amount',
    'base_amount'      => 'Calculated on',
    'equivalent'       => 'Equals :percent%',

    /* ── Estado del pago ── */
    'amount'           => 'Commission',
    'paid'             => 'Paid',
    'balance'          => 'Outstanding',
    'owed_to'          => 'Owed to :name',

    /* ── Ayudas ── */
    'base_help'        => 'On subtotal less discount. Sales tax and card surcharge are excluded: they are not revenue.',
    'closed_by_help'   => 'Who closed the deal, not who typed it. The commission comes from here.',
    'created_at_issue' => 'The commission is created when the invoice is issued, not when it is paid.',
    'has_payments'     => 'Already has payments: the amount is not recalculated automatically.',

];
