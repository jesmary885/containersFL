<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // [código, nombre, nombre en inglés, marca 1099 por defecto, orden]
        // ⚠️ Lista provisional. Denisse tiene que mandar la definitiva.
        // En el Excel las columnas "TIPO DE GASTO" y "PROVEEDOR GENERAL"
        // están intercambiadas en muchas filas, así que la migración de
        // gastos históricos va a necesitar mapeo manual.

        $categories = [
            ['YARD',        'Yarda',                     'Yard',                  false, 10],
            ['VEHICLES',    'Vehículos',                 'Vehicles',              false, 20],
            ['FUEL',        'Combustible',               'Fuel',                  false, 30],
            ['MATERIALS',   'Materiales',                'Materials',             false, 40],
            ['SALARIES',    'Salarios',                  'Salaries',              false, 50],
            ['TRANSPORT',   'Transportación',            'Transportation',        true,  60],
            ['DEPOT_FEES',  'Container fees / depósito', 'Container fees',        false, 70],
            ['COMMISSIONS', 'Comisiones',                'Commissions',           true,  80],
            ['OFFICE',      'Oficina',                   'Office',                false, 90],
            ['INSURANCE',   'Seguros',                   'Insurance',             false, 100],
            ['TOLLS',       'Peajes',                    'Tolls',                 false, 110],

            // Esta es la que resuelve la hoja "TAXES" del Excel:
            // pagos a técnicos y contratistas para el reporte 1099 de fin de año.
            ['CONTRACTORS', 'Técnicos y contratistas',   'Contractors (1099)',    true,  120],

            ['REPAIRS',     'Reparación de contenedores','Container repairs',     true,  130],
            ['OTHER',       'Otros',                     'Other',                 false, 999],
        ];

        foreach ($categories as [$code, $name, $nameEn, $is1099, $order]) {
            ExpenseCategory::updateOrCreate(
                ['code' => $code],
                [
                    'name'            => $name,
                    'name_en'         => $nameEn,
                    'parent_id'       => null,
                    'is_1099_default' => $is1099,
                    'sort_order'      => $order,
                    'is_active'       => true,
                ],
            );
        }
    }
}
