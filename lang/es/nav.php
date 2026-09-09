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
*/

return [

    'dashboard'        => 'Panel',

    'commercial'       => 'Comercial',
    'customers'        => 'Clientes',
    'estimates'        => 'Presupuestos',
    'sales'            => 'Ventas',

    'operations'       => 'Operaciones',
    'containers'       => 'Contenedores',
    'rentals'          => 'Rentas',
    'trips'            => 'Viajes / Delivery',
    'drivers'          => 'Choferes',

    'purchasing'       => 'Compras',
    'suppliers'        => 'Proveedores',
    'purchases'        => 'Compras',
    'releases'         => 'Releases',

    'inventory'        => 'Inventario',
    'supplies'         => 'Insumos, piezas y partes',
    'trucks'           => 'Camiones',

    'finance'          => 'Finanzas',
    'invoicing'        => 'Facturación',
    'payments'         => 'Pagos',
    'expenses'         => 'Gastos',

    'administration'   => 'Administración',
    'users'            => 'Usuarios',
    'roles'            => 'Roles y permisos',

    'reports'          => 'Reportes',
    'settings'         => 'Configuración',

];
