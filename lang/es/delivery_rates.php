<?php

/*
|--------------------------------------------------------------------------
| TARIFAS DE ENTREGA POR RANGO
|--------------------------------------------------------------------------
|
| Reunión del 16 de septiembre: las tarifas de milla varían según rangos, y
| se acordó mantenerlas editables sobre una base estándar para ajustarlas
| según fluctúen los costos.
|
*/

return [

    'title'    => 'Tarifas de entrega',
    'subtitle' => 'Cuánto se cobra por milla según la distancia. El sistema elige el rango solo.',
    'saved'    => 'Tarifas guardadas.',
    'removed'  => 'Rango eliminado.',

    'ranges'    => 'Rangos de millas',
    'add_range' => 'Agregar rango',
    'name'      => 'Nombre',
    'from'      => 'Desde (mi)',
    'to'        => 'Hasta (mi)',
    'rate'      => 'Por milla',
    'active'    => 'Activo',
    'no_cap'    => 'Sin tope',
    'over'      => 'Más de :n mi',

    'confirm_remove' => '¿Eliminar este rango?',

    'bounds_hint' => 'El "desde" entra en el rango y el "hasta" no. Así, 100 millas caen en el '
                    .'rango que empieza en 100 y no en el que termina ahí. Dejar el "hasta" en '
                    .'blanco significa "de aquí en adelante".',

    'max_must_be_greater' => 'El "hasta" tiene que ser mayor que el "desde". Si no, no hay '
                            .'ninguna entrega que caiga en este rango.',
    'one_open_range'      => 'Solo puede haber un rango sin tope. Dos rangos abiertos se pisan '
                            .'y la tarifa dependería del orden en que se consulten.',

    'gaps_title'   => 'Hay tramos de millas sin tarifa',
    'gap_between'  => 'De :a a :b millas',
    'gap_over'     => 'De :n millas en adelante',
    'gaps_hint'    => 'No impide guardar. Una entrega que caiga en un tramo sin tarifa usa el '
                     .'valor general de Configuración, y nadie se entera de que pasó.',

    /* ── Combustible ── */
    'fuel'          => 'Recargo por combustible',
    'fuel_per_mile' => 'Recargo por milla',
    'fuel_hint'     => 'Se suma a la tarifa del rango. Mientras esté en $0.00 no tiene ningún '
                      .'efecto. Si el combustible sube y quiere trasladarlo al cliente, escriba '
                      .'acá cuánto por milla y se aplica a todas las entregas.',

];
