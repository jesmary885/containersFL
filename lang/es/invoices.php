<?php

/*
|--------------------------------------------------------------------------
| FACTURACIÓN
|--------------------------------------------------------------------------
|
| Solo lo que cambia con el idioma.
|
| ── LOS TÉRMINOS DE PAGO NO SE TRADUCEN ──
|
| "Net 30" y "Due on receipt" se imprimen tal cual en los dos idiomas.
| Son texto de un documento legal: el cliente los conoce así y su
| contador espera verlos así. Traducirlos sería cambiar el contenido del
| documento por cambiar el idioma de la pantalla.
|
| Lo que sí cambia es la explicación que acompaña a cada uno dentro del
| desplegable, que es para quien factura, no para el cliente.
|
*/

return [

    'terms_on_receipt' => 'pagadero al recibir',
    'terms_days'       => ':n días',
    'terms_deposit'    => '50% de anticipo, saldo contra entrega',
    'terms_other'      => 'Otro — escribirlo a mano',
    'terms_other_ph'   => 'Ej: Net 45, 30% anticipo y saldo a 60 días…',

];
