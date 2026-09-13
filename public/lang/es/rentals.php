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
    'cycle_monthly'     => 'Mensual',
    'cycle_daily'       => 'Por día (yarda)',
    'per_month_abbr'    => '/mes',
    'per_day_abbr'      => '/día',

    /* ── Renta de yarda ── */
    'daily_rate'        => 'Tarifa por día',
    'days_elapsed'      => 'Días en yarda',
    'paid_days'         => 'Días pagados',
    'unpaid_days'       => 'Días pendientes',
    'daily_debt'        => 'Deuda por días',
    'as_of'             => 'Al :date',

    /* ── Cargos de una sola vez ── */
    'entry_fee'         => 'Entrada',
    'exit_fee'          => 'Salida',
    'paint_fee'         => 'Pintura',
    'repair_fee'        => 'Reparación',
    'one_time_fees'     => 'Cargos fijos',

    /* ── Ayudas ── */
    'daily_rate_help'   => 'Los días corren desde la entrada hasta que se lleven el contenedor.',
    'paid_days_help'    => 'Para contratos que vienen del Excel: los días que ya estaban pagados al migrar.',
    'no_end_date_help'  => 'Sin fecha de fin: los días siguen sumando hasta que se cierre el contrato.',

];
