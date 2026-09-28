<?php

/*
|--------------------------------------------------------------------------
| DELIVERY RATES BY MILEAGE RANGE
|--------------------------------------------------------------------------
|
| From the 16-Sep meeting: mileage rates vary by range, and it was agreed to
| keep them editable on a standard base so they can be adjusted as costs
| fluctuate.
|
*/

return [

    'title'    => 'Delivery rates',
    'subtitle' => 'What gets charged per mile by distance. The system picks the range for you.',
    'saved'    => 'Rates saved.',
    'removed'  => 'Range removed.',

    'ranges'    => 'Mileage ranges',
    'add_range' => 'Add range',
    'name'      => 'Name',
    'from'      => 'From (mi)',
    'to'        => 'To (mi)',
    'rate'      => 'Per mile',
    'active'    => 'Active',
    'no_cap'    => 'No cap',
    'over'      => 'Over :n mi',

    'confirm_remove' => 'Remove this range?',

    'bounds_hint' => 'The "from" is included and the "to" is not. So 100 miles falls in the range '
                    .'starting at 100, not the one ending there. Leaving "to" blank means '
                    .'"from here on".',

    'max_must_be_greater' => 'The "to" must be greater than the "from". Otherwise no delivery '
                            .'ever falls in this range.',
    'one_open_range'      => 'Only one range can be left without a cap. Two open ranges overlap '
                            .'and the rate would depend on query order.',

    'gaps_title'   => 'Some mileage stretches have no rate',
    'gap_between'  => 'From :a to :b miles',
    'gap_over'     => 'From :n miles on',
    'gaps_hint'    => 'This does not block saving. A delivery landing in an uncovered stretch '
                     .'falls back to the general setting, and nobody finds out.',

    /* ── Fuel ── */
    'fuel'          => 'Fuel surcharge',
    'fuel_per_mile' => 'Surcharge per mile',
    'fuel_hint'     => 'Added on top of the range rate. While it sits at $0.00 it has no effect. '
                      .'If fuel goes up and you want to pass it on, type how much per mile here '
                      .'and it applies to every delivery.',

];
