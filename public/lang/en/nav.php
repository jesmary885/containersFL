<?php

/*
|--------------------------------------------------------------------------
| THE SIDEBAR MENU
|--------------------------------------------------------------------------
|
| Kept apart from common.php because these are module names, not loose
| words. When a module is added, add its key here and in the Spanish
| file. A missing key renders raw ("nav.reports"), so add both at once.
|
| The names mirror the Roles screen on purpose, word for word. Whoever
| assigns permissions ticks "Driver settlements" there and must find
| exactly that in the menu.
|
*/

return [

    'dashboard'        => 'Dashboard',

    /* ── COMMERCIAL ── */
    'commercial'       => 'Sales & Quotes',
    'customers'        => 'Customers',
    'estimates'        => 'Estimates',
    'sales'            => 'Sales',

    /* ── OPERATIONS ── */
    'operations'       => 'Operations',
    'containers'       => 'Containers',
    'rentals'          => 'Rentals',
    'trips'            => 'Trips',
    'drivers'          => 'Drivers',
    'vehicles'         => 'Trucks',

    /* ── PURCHASING ── */
    'purchasing'       => 'Purchasing',
    'suppliers'        => 'Suppliers',
    'purchases'        => 'Purchases & releases',
    'depots'           => 'Depots',
    'parts'            => 'Supplies & parts',

    /* ── FINANCE ── */
    'finance'          => 'Finance',
    'invoicing'        => 'Invoicing',
    'payments'         => 'Payments',
    'expenses'         => 'Expenses',
    'commissions'      => 'Commissions',
    'settlements'      => 'Driver settlements',

    /* ── SYSTEM ── */
    'system'           => 'System',
    'reports'          => 'Reports',
    'catalogs'         => 'Catalogs',
    'settings'         => 'Settings',
    'users'            => 'Users',
    'roles'            => 'Roles & permissions',

    /* ── OLD KEYS, kept so nothing prints raw ── */
    'inventory'        => 'Inventory',
    'supplies'         => 'Supplies & parts',
    'trucks'           => 'Trucks',
    'releases'         => 'Releases',
    'administration'   => 'Administration',

];
