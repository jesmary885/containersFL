<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /** Cada módulo con las acciones que se pueden hacer sobre él */
    private array $modules = [
        'customers'    => ['view', 'create', 'update', 'delete'],
        'suppliers'    => ['view', 'create', 'update', 'delete'],
        'depots'       => ['view', 'create', 'update', 'delete'],
        'catalogs'     => ['view', 'create', 'update', 'delete'], // tipos, medidas, condiciones, calidades
        'containers'   => ['view', 'create', 'update', 'delete'],
        'purchases'    => ['view', 'create', 'update', 'delete'],
        'estimates'    => ['view', 'create', 'update', 'delete', 'send'],
        'sales'        => ['view', 'create', 'update', 'delete'],
        'rentals'      => ['view', 'create', 'update', 'delete', 'waive_late_fee'],
        'trips'        => ['view', 'create', 'update', 'delete', 'dispatch'],
        'invoices'     => ['view', 'create', 'update', 'send', 'void'],
        'payments'     => ['view', 'create', 'update', 'delete'],
        'expenses'     => ['view', 'create', 'update', 'delete', 'approve'],
        'commissions'  => ['view', 'create', 'update', 'pay'],
        'settlements'  => ['view', 'create', 'update', 'pay'],
        'drivers'      => ['view', 'create', 'update', 'delete'],
        'vehicles'     => ['view', 'create', 'update', 'delete'],
        'parts'        => ['view', 'create', 'update', 'delete'],
        'reports'      => ['view', 'export'],
        'settings'     => ['view', 'update'],
        'users'        => ['view', 'create', 'update', 'delete'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Crear todos los permisos
        foreach ($this->modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name'       => "{$module}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // 2. Crear los roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $operations = Role::firstOrCreate(['name' => 'operations',  'guard_name' => 'web']);
        $sales      = Role::firstOrCreate(['name' => 'sales',       'guard_name' => 'web']);
        $accounting = Role::firstOrCreate(['name' => 'accounting',  'guard_name' => 'web']);
        $driver     = Role::firstOrCreate(['name' => 'driver',      'guard_name' => 'web']);

        // 3. Repartir permisos

        // super_admin: todo. Este rol se maneja con un Gate::before, pero
        // igual se le asignan todos por si acaso.
        $superAdmin->syncPermissions(Permission::all());

        // admin: todo menos administrar usuarios y borrar facturas
        $admin->syncPermissions(
            Permission::whereNotIn('name', [
                'users.create', 'users.update', 'users.delete',
            ])->get()
        );

        // operations: inventario, viajes, compras. No ve dinero de clientes.
        $operations->syncPermissions($this->permissionsFor([
            'containers' => ['view', 'create', 'update'],
            'purchases'  => ['view', 'create', 'update'],
            'trips'      => ['view', 'create', 'update', 'dispatch'],
            'depots'     => ['view'],
            'catalogs'   => ['view'],
            'drivers'    => ['view'],
            'vehicles'   => ['view', 'create', 'update'],
            'parts'      => ['view', 'create', 'update'],
            'customers'  => ['view'],
            'reports'    => ['view'],
        ]));

        // sales: cotiza, vende, renta. Ve facturas pero no las anula.
        $sales->syncPermissions($this->permissionsFor([
            'customers'  => ['view', 'create', 'update'],
            'containers' => ['view'],
            'depots'     => ['view'],
            'catalogs'   => ['view'],
            'estimates'  => ['view', 'create', 'update', 'send'],
            'sales'      => ['view', 'create', 'update'],
            'rentals'    => ['view', 'create', 'update'],
            'invoices'   => ['view', 'create', 'send'],
            'trips'      => ['view', 'create'],
            'commissions'=> ['view'],
            'reports'    => ['view'],
        ]));

        // accounting: el dinero completo. Este es el rol de Denisse.
        $accounting->syncPermissions($this->permissionsFor([
            'customers'   => ['view', 'create', 'update'],
            'invoices'    => ['view', 'create', 'update', 'send', 'void'],
            'payments'    => ['view', 'create', 'update', 'delete'],
            'expenses'    => ['view', 'create', 'update', 'delete', 'approve'],
            'commissions' => ['view', 'create', 'update', 'pay'],
            'settlements' => ['view', 'create', 'update', 'pay'],
            'rentals'     => ['view', 'update', 'waive_late_fee'],
            'sales'       => ['view'],
            'purchases'   => ['view'],
            'trips'       => ['view'],
            'containers'  => ['view'],
            'reports'     => ['view', 'export'],
            'settings'    => ['view', 'update'],
        ]));

        // driver: solo sus viajes, desde el teléfono
        $driver->syncPermissions($this->permissionsFor([
            'trips' => ['view'],
        ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionsFor(array $map): array
    {
        $names = [];

        foreach ($map as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

      

}
