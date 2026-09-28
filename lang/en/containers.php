<?php

/*
|--------------------------------------------------------------------------
| CONTAINERS
|--------------------------------------------------------------------------
|
| New file. Added with the 16-Sep change: the "material" field was replaced
| by "color", and the colors need translating because Denisse works in
| English.
|
*/

return [

    /* ── Unit color ── */
    'color'          => 'Color',
    'color_any'      => 'Any color',
    'color_yellow'   => 'Yellow',
    'color_gray'     => 'Gray',
    'color_blue'     => 'Blue',
    'color_red'      => 'Red',
    'color_green'    => 'Green',
    'color_white'    => 'White',
    'color_black'    => 'Black',
    'color_brown'    => 'Brown',
    'color_other'    => 'Other',

    /* ── Certificate and inspection ── */
    'export_eligible'      => 'Export eligible',
    'csc_at_sale'          => 'Certificate is issued at the time of sale',
    'csc_at_sale_hint'     => 'For regular containers the inspector issues the certificate when the '
                             .'unit is sold. No date is needed now.',
    'inspection_due'       => 'Inspection expires',
    'inspection_due_hint'  => 'Tanks carry an inspection date that expires. The system warns ahead '
                             .'of time so the unit does not fall out of service.',
    'inspection_expired'   => 'Inspection expired',
    'inspection_soon'      => 'Inspection due soon',

    /* ── Repairs ── */
    'needs_repair'         => 'Needs repair',
    'needs_repair_short'   => 'Needs repair',
    'repair_filter_any'    => 'Repair: all',
    'repair_filter_yes'    => 'Only units needing repair',
    'repair_filter_no'     => 'Only units ready',
    'repair_notes'         => 'What needs fixing',

];
