<?php

/*
|--------------------------------------------------------------------------
| CONTENEDORES
|--------------------------------------------------------------------------
|
| Archivo nuevo. Nace con el cambio del 16 de septiembre: el campo
| "material" se reemplazó por "color" y los colores necesitan traducción,
| porque el sistema está en los dos idiomas y Denisse trabaja en inglés.
|
| Acá van también las etiquetas de la inspección de tanques y del marcado
| de reparaciones, que entraron en la misma reunión.
|
*/

return [

    /* ── El color de la unidad ── */
    'color'          => 'Color',
    'color_any'      => 'Cualquier color',
    'color_yellow'   => 'Amarillo',
    'color_gray'     => 'Gris',
    'color_blue'     => 'Azul',
    'color_red'      => 'Rojo',
    'color_green'    => 'Verde',
    'color_white'    => 'Blanco',
    'color_black'    => 'Negro',
    'color_brown'    => 'Marrón',
    'color_other'    => 'Otro',

    /* ── Certificado e inspección ──
     |
     | Dos cosas distintas que antes eran una sola:
     |
     |   El CERTIFICADO de exportación lo emite un inspector y se entrega
     |   con la venta. Para un contenedor corriente no hay nada que vigilar
     |   antes de vender.
     |
     |   La INSPECCIÓN de un tanque sí vence, y vencida deja al tanque
     |   fuera de servicio. Esa fecha hay que verla venir.
     */
    'export_eligible'      => 'Apta para exportación',
    'csc_at_sale'          => 'El certificado se emite al vender',
    'csc_at_sale_hint'     => 'En los contenedores corrientes el certificado lo emite el inspector '
                             .'en el momento de la venta. No hace falta cargar una fecha ahora.',
    'inspection_due'       => 'Vence la inspección',
    'inspection_due_hint'  => 'Los tanques llevan una fecha de inspección que vence. El sistema '
                             .'avisa antes para que no se quede fuera de servicio.',
    'inspection_expired'   => 'Inspección vencida',
    'inspection_soon'      => 'Inspección por vencer',

    /* ── Reparaciones ── */
    'needs_repair'         => 'Necesita reparación',
    'needs_repair_short'   => 'Por reparar',
    'repair_filter_any'    => 'Reparación: todas',
    'repair_filter_yes'    => 'Solo las que necesitan reparación',
    'repair_filter_no'     => 'Solo las que están listas',
    'repair_notes'         => 'Qué hay que arreglar',

];
