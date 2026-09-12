<?php

/*
|--------------------------------------------------------------------------
| EL MENÚ LATERAL
|--------------------------------------------------------------------------
|
| Vive aparte de common.php porque son nombres de módulo, no palabras
| sueltas. Cuando se agregue un módulo, se agrega su clave acá y en el
| archivo en inglés. Si falta en inglés, Laravel muestra la clave cruda
| ("nav.reports"), así que conviene agregar las dos a la vez.
|
| ── LOS NOMBRES SON LOS DE LA PANTALLA DE ROLES ──
|
| A propósito, y palabra por palabra. Quien reparte permisos marca
| "Liquidación de choferes" en Roles y tiene que encontrar exactamente
| eso en el menú. Si acá dijera "Liquidaciones" y allá otra cosa, cada
| cambio de permisos sería una adivinanza.
|
| La lista viva está en App\Livewire\Roles\Index::MODULOS. Si se cambia
| un nombre allá, se cambia acá.
|
*/

return [

    'dashboard'        => 'Panel',

    /* ── COMERCIAL ── */
    'commercial'       => 'Comercial',
    'customers'        => 'Clientes',
    'estimates'        => 'Presupuestos',
    'sales'            => 'Ventas',

    /* ── OPERACIONES ── */
    'operations'       => 'Operaciones',
    'containers'       => 'Contenedores',
    'rentals'          => 'Rentas',
    'trips'            => 'Viajes',
    'drivers'          => 'Choferes',
    'vehicles'         => 'Camiones',

    /* ── COMPRAS ── */
    'purchasing'       => 'Compras',
    'suppliers'        => 'Proveedores',
    'purchases'        => 'Compras y releases',
    'depots'           => 'Depósitos',
    'parts'            => 'Insumos y piezas',

    /* ── FINANZAS ── */
    'finance'          => 'Finanzas',
    'invoicing'        => 'Facturación',
    'payments'         => 'Pagos',
    'expenses'         => 'Gastos',
    'commissions'      => 'Comisiones',
    'settlements'      => 'Liquidación de choferes',

    /* ── SISTEMA ── */
    'system'           => 'Sistema',
    'reports'          => 'Reportes',
    'catalogs'         => 'Catálogos',
    'settings'         => 'Configuración',
    'users'            => 'Usuarios',
    'roles'            => 'Roles y permisos',

    /*
    | ── CLAVES VIEJAS ──
    |
    | El menú ya no las usa. Se dejan porque no cuestan nada y porque
    | borrarlas rompería cualquier pantalla que todavía las llame sin
    | que nadie se entere: un __() sin traducción no da error, imprime
    | la clave cruda.
    */
    'inventory'        => 'Inventario',
    'supplies'         => 'Insumos, piezas y partes',
    'trucks'           => 'Camiones',
    'releases'         => 'Releases',
    'administration'   => 'Administración',

];
