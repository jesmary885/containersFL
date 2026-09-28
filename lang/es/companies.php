<?php

/*
|--------------------------------------------------------------------------
| DATOS DE LA EMPRESA
|--------------------------------------------------------------------------
|
| Archivo nuevo, de la reunión del 16 de septiembre: Denisse pidió una
| sección estática al pie de la factura con la información bancaria y los
| métodos de pago, para que el cliente elija cómo pagar.
|
| El campo donde vive esa información ya existía en la base. Lo que no
| existía era una pantalla para escribirla.
|
*/

return [

    'title'    => 'Datos de la empresa',
    'subtitle' => 'Lo que sale impreso en los presupuestos y las facturas de esta compañía.',
    'saved'    => 'Datos de la empresa guardados.',

    /* ── Cabecera del documento ── */
    'header_data'   => 'Cabecera del documento',
    'address'       => 'Dirección',
    'address_2'     => 'Suite, piso, referencia (opcional)',
    'city'          => 'Ciudad',
    'state'         => 'Estado',
    'zip'           => 'Código postal',
    'phone'         => 'Teléfono',
    'email'         => 'Correo',
    'website'       => 'Sitio web',

    'footer_terms'      => 'Términos al pie',
    'footer_terms_hint' => 'Texto fijo que aparece al final de cada documento. '
                          .'Por ejemplo: "Invoice valid for: 3 days".',

    /* ── Formas de pago ── */
    'payment_methods' => 'Formas de pago',
    'payment_methods_hint' =>
        'Salen impresas al pie de cada factura de esta compañía, siempre, sin depender '
        .'de cómo se pague. El cliente recibe el documento y elige. '
        .'Cada compañía tiene sus propias cuentas: cargue las de la compañía activa.',

    'method_name'    => 'Zelle, Wire/SWIFT, Check...',
    'method_details' => "Los datos, en renglones.\nBanco, routing, número de cuenta, a nombre de...",
    'add_method'     => 'Agregar forma de pago',
    'remove_method'  => 'Quitar',
    'no_methods'     => 'Todavía no hay formas de pago cargadas. Sin esto, el pie de la factura '
                       .'sale en blanco y el cliente no sabe a dónde transferir.',

];
