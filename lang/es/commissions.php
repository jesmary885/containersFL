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

    'title'            => 'Comisiones',
    'registered_by'    => 'Registró',
    'closed_by'        => 'Cerró la venta',

    /* ── Cómo se calcula ── */
    'mode'             => 'Cómo se calcula',
    'mode_percent'     => 'Porcentaje de la venta',
    'mode_fixed'       => 'Monto pactado',
    'percent'          => 'Porcentaje',
    'fixed_amount'     => 'Monto',
    'base_amount'      => 'Se calcula sobre',
    'equivalent'       => 'Equivale a :percent%',

    /* ── Estado del pago ── */
    'amount'           => 'Comisión',
    'paid'             => 'Pagado',
    'balance'          => 'Se le debe',
    'owed_to'          => 'Se le debe a :name',

    /* ── Ayudas ── */
    'base_help'        => 'Sobre el subtotal menos descuento. El sales tax y el recargo de tarjeta no entran: no son venta.',
    'closed_by_help'   => 'Quien cerró el negocio, no quien teclea. De aquí sale la comisión.',
    'created_at_issue' => 'La comisión nace al emitir la factura, no al cobrarla.',
    'has_payments'     => 'Ya tiene abonos: el monto no se recalcula solo.',

];
