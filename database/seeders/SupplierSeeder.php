<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ⚠️ Lista provisional armada con lo que aparece en el Excel.
        // Falta contacto, teléfono y dirección de casi todos.
        $suppliers = [
            [
                'supplier_number'    => 'SUP-0001',
                'name'               => 'Maritime Container',       // aparece en la hoja RELEASES
                'type'               => 'depot',
                'is_1099_reportable' => false,
                'notes'              => 'Depósito. Retiro de contenedores por release.',
            ],
            [
                'supplier_number'    => 'SUP-0002',
                'name'               => 'Proveedor Directo',        // ⚠️ el "DIRECTO DE LA CALLE" del Excel
                'type'               => 'container_supplier',
                'is_1099_reportable' => false,
                'notes'              => 'Compras puntuales de contenedores sin depósito fijo.',
            ],
            [
                'supplier_number'    => 'SUP-0003',
                'name'               => 'Home Depot',
                'type'               => 'materials',
                'is_1099_reportable' => false,
                'notes'              => 'Materiales y accesorios.',
            ],
            [
                'supplier_number'    => 'SUP-0004',
                'name'               => 'Técnico de Refrigeración', // hoja TAXES
                'type'               => 'service',
                'is_1099_reportable' => true,
                'notes'              => 'Contratista. Reportable al 1099.',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(
                ['supplier_number' => $supplier['supplier_number']],
                array_merge($supplier, [
                    'contact_name' => null,
                    'phone'        => null,
                    'email'        => null,
                    'address'      => null,
                    'is_active'    => true,
                ]),
            );
        }
    }
}
