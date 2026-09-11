<?php

/*
|--------------------------------------------------------------------------
| TEXTOS QUE SE REPITEN EN TODO EL SISTEMA
|--------------------------------------------------------------------------
|
| Acá va solo lo que aparece en más de una pantalla. Lo propio de un
| módulo va en su archivo: estimates.php, invoices.php, etc.
|
| Regla para no ensuciar esto: si dudas si va acá o en el módulo, va en
| el módulo. Mover una clave a common después es más fácil que sacarla.
|
*/

return [

    // ── ACCIONES ──
    'save'            => 'Guardar',
    'save_draft'      => 'Guardar borrador',
    'save_and_send'   => 'Guardar y enviar correo',
    'cancel'          => 'Cancelar',
    'delete'          => 'Eliminar',
    'edit'            => 'Editar',
    'new'             => 'Nuevo',
    'search'          => 'Buscar',
    'close'           => 'Cerrar',
    'back'            => 'Volver',
    'back_to_list'    => 'Volver al listado',
    'change'          => 'Cambiar',
    'missing'         => 'Falta',
    'remove'          => 'Quitar',
    'undo'            => 'Deshacer',
    'add'             => 'Agregar',
    'print'           => 'Imprimir',
    'download'        => 'Descargar',
    'duplicate'       => 'Duplicar',
    'view'            => 'Ver',
    'actions'         => 'Acciones',

    // ── ESTADOS DE LA PANTALLA ──
    'saving'          => 'Guardando…',
    'loading'         => 'Cargando…',
    'no_results'      => 'No se encontró ningún resultado.',
    'none'            => 'Ninguno',
    'not_assigned'    => '— Sin asignar —',
    'not_applicable'  => '— No aplica —',
    'optional'        => '(opcional)',
    'required_field'  => 'Campo obligatorio',
    'select'          => '— Seleccionar —',

    // ── DINERO ──
    'subtotal'        => 'Subtotal',
    'discount'        => 'Descuento',
    'tax'             => 'Impuesto',
    'sales_tax'       => 'Sales tax',
    'taxable_base'    => 'Base gravable',
    'non_taxable'     => 'No gravable',
    'total'           => 'Total',
    'totals'          => 'Totales',
    'amount'          => 'Importe',
    'price'           => 'Precio',
    'quantity'        => 'Cant.',
    'card_surcharge'  => 'Recargo tarjeta',
    'list_price'      => 'Lista',

    // ── CAMPOS COMUNES ──
    'customer'        => 'Cliente',
    'date'            => 'Fecha',
    'status'          => 'Estado',
    'notes'           => 'Notas',
    'description'     => 'Descripción',
    'company'         => 'Empresa',
    'salesperson'     => 'Vendedor',
    'number'          => 'Número',

    // ── DIRECCIONES ──
    'address'         => 'Dirección',
    'address_line2'   => 'Línea 2',
    'city'            => 'Ciudad',
    'state'           => 'Estado',
    'zip'             => 'ZIP',
    'bill_to'         => 'Facturar a',
    'ship_to'         => 'Entregar en',

    // ── IDIOMA ──
    'language'        => 'Idioma',
    'spanish'         => 'Español',
    'english'         => 'Inglés',

];
