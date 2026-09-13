<?php

/*
|--------------------------------------------------------------------------
| RENTAS
|--------------------------------------------------------------------------
|
| Las dos formas de cobrar una renta salen de dos hojas distintas del
| Excel que la empresa usa hoy, y son dos negocios distintos:
|
|   RENTAS        el cliente se lleva un contenedor nuestro. Por mes.
|   RENTAS YARDA  el cliente deja su contenedor guardado acá. Por día.
|
| La diferencia de fondo no es la unidad de tiempo: es quién tiene el
| contenedor. En la mensual sale de la yarda; en la diaria entra a ella.
| De ahí que la de yarda cobre entrada y salida y la mensual no.
|
*/

return [

    /* ── Ciclo de cobro ── */
    'cycle_monthly'     => 'Monthly',
    'cycle_daily'       => 'Daily (yard)',
    'per_month_abbr'    => '/mo',
    'per_day_abbr'      => '/day',

    /* ── Renta de yarda ── */
    'daily_rate'        => 'Daily rate',
    'days_elapsed'      => 'Days in yard',
    'paid_days'         => 'Days paid',
    'unpaid_days'       => 'Days outstanding',
    'daily_debt'        => 'Days owed',
    'as_of'             => 'As of :date',

    /* ── Cargos de una sola vez ── */
    'entry_fee'         => 'Entry',
    'exit_fee'          => 'Exit',
    'paint_fee'         => 'Paint',
    'repair_fee'        => 'Repair',
    'one_time_fees'     => 'One-time fees',

    /* ── Ayudas ── */
    'daily_rate_help'   => 'Days accrue from drop-off until the container is picked up.',
    'paid_days_help'    => 'For contracts migrated from the spreadsheet: days already paid at migration time.',
    'no_end_date_help'  => 'No end date: days keep accruing until the contract is closed.',

];
