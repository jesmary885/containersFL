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


    /* ── El vendedor con condiciones especiales · reunión 16-09 ──
     |
     | Denisse: la mayoría maneja montos fijos, salvo un vendedor principal
     | que cobra porcentaje sobre unidades de venta directa.
     |
     | Por eso se pueden cargar los dos valores en la ficha del vendedor y
     | por eso hace falta decir cuál se propone.
     */
    'no_commission'        => 'Esta factura no comisiona',
    'default_mode'         => '¿Cuál se propone al facturar?',
    'default_mode_hint'    => 'Tiene cargados monto y porcentaje. Se propone el que elija acá, '
                             .'y el otro sigue disponible: en cada factura se cambia con un clic.',
    'no_mode_warning'      => 'Hay vendedor pero no se eligió cómo se calcula, así que esta factura '
                             .'no va a generar comisión. Elija monto o porcentaje.',

    'base'                 => 'Se calcula sobre',
    'base_subtotal'        => 'Toda la venta',
    'base_subtotal_hint'   => 'Incluye el delivery y los servicios. Sin impuesto ni recargo de tarjeta.',
    'base_containers'      => 'Solo los contenedores',
    'base_containers_hint' => 'Deja fuera el delivery y los servicios. Es lo de "unidades de venta directa".',

];
