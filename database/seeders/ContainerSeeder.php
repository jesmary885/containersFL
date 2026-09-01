<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Enums\ContainerStatus;
use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerCondition;
use App\Models\ContainerGrade;
use App\Models\ContainerSize;
use App\Models\ContainerType;
use App\Models\Depot;
use App\Models\Location;
use Illuminate\Database\Seeder;

class ContainerSeeder extends Seeder
{
    /**
     * dos cosas:

*1. **`owner_company_id` y `billing_company_id`** van llenos con FLCHR. El modelo los precargaría solo, pero acá se ponen explícitos para dejarlo claro. Ambos son editables después.
*2. **`at_supplier` vs `in_yard`.** Los que están en `at_supplier` están comprados pero todavía en el depósito: **no cuentan como stock disponible**. Esta distinción es la que resuelve el descuadre de inventario que tenían en Excel.
     */
    public function run(): void
    {
        $flchr = Company::where('code', 'FLCHR')->firstOrFail();
        $yard  = Location::where('type', 'yard')->first();
        $depot = Depot::where('code', 'MARITIME')->first();

        // Atajos para no repetir consultas
        $types      = ContainerType::pluck('id', 'code');
        $sizes      = ContainerSize::pluck('id', 'code');
        $conditions = ContainerCondition::pluck('id', 'code');
        $grades     = ContainerGrade::pluck('id', 'code');

        // ⚠️ INVENTARIO DE EJEMPLO.
        // El inventario real se migra desde la hoja del Excel.
        // Estos sirven para probar ventas, rentas y viajes.
        $containers = [
            [
                'container_number' => 'MSCU1234567',
                'internal_code'    => 'Unit #1',
                'size'             => '40FT-HC',
                'type'             => 'dry',
                'condition'        => 'USED',
                'grade'            => 'CARGO_WORTHY',
                'status'           => ContainerStatus::InYard,
                'acquisition_cost' => 1850.00,
                'pickup_cost'      => 100.00,
                'year'             => 2012,
                'in_yard'          => true,
            ],
            [
                'container_number' => 'TGHU7654321',
                'internal_code'    => 'Unit #2',
                'size'             => '20FT',
                'type'             => 'dry',
                'condition'        => 'USED',
                'grade'            => 'WWT',
                'status'           => ContainerStatus::InYard,
                'acquisition_cost' => 1400.00,
                'pickup_cost'      => 100.00,
                'year'             => 2010,
                'in_yard'          => true,
            ],
            [
                'container_number' => 'CAIU9988776',
                'internal_code'    => 'Unit #3',
                'size'             => '40FT-HC',
                'type'             => 'dry',
                'condition'        => 'ONE_TRIP',
                'grade'            => 'CARGO_WORTHY',
                'status'           => ContainerStatus::InYard,
                'acquisition_cost' => 3200.00,
                'pickup_cost'      => 100.00,
                'year'             => 2023,
                'in_yard'          => true,
            ],
            [
                // Contenedor sin número ISO: se registra igual.
                // Los dos campos de identificación son opcionales.
                'container_number' => null,
                'internal_code'    => 'Unit #4',
                'size'             => '20FT',
                'type'             => 'dry',
                'condition'        => 'USED',
                'grade'            => 'AS_IS',
                'status'           => ContainerStatus::Reconditioning,
                'acquisition_cost' => 950.00,
                'pickup_cost'      => 0.00,
                'year'             => 2008,
                'in_yard'          => true,
            ],
            [
                // Comprado pero TODAVÍA EN EL DEPÓSITO.
                // No cuenta como disponible para vender.
                'container_number' => 'HLXU5544332',
                'internal_code'    => 'Unit #5',
                'size'             => '40FT-STD',
                'type'             => 'dry',
                'condition'        => 'USED',
                'grade'            => 'CARGO_WORTHY',
                'status'           => ContainerStatus::AtSupplier,
                'acquisition_cost' => 1750.00,
                'pickup_cost'      => 0.00,       // todavía no se pagó la recogida
                'year'             => 2013,
                'in_yard'          => false,
            ],
            [
                // Contenedor viejo sin clasificar: condición y calidad en null.
                // Se guarda igual, por eso esos campos son nullable.
                'container_number' => null,
                'internal_code'    => 'Unit #6',
                'size'             => '20FT',
                'type'             => 'dry',
                'condition'        => null,
                'grade'            => null,
                'status'           => ContainerStatus::InYard,
                'acquisition_cost' => null,
                'pickup_cost'      => 0.00,
                'year'             => null,
                'in_yard'          => true,
            ],
            [
                'container_number' => 'MWCU3322110',
                'internal_code'    => 'Reefer #1',
                'size'             => '40FT-HC',
                'type'             => 'reefer',
                'condition'        => 'USED',
                'grade'            => 'WWT',
                'status'           => ContainerStatus::InYard,
                'acquisition_cost' => 6500.00,
                'pickup_cost'      => 150.00,
                'year'             => 2015,
                'in_yard'          => true,
            ],
        ];

        foreach ($containers as $c) {
            $grade = $c['grade'] ? $grades[$c['grade']] ?? null : null;

            $attributes = [
                // Las dos compañías. Se preguntan al registrar,
                // vienen precargadas con FLCHR, y las dos son editables.
                'owner_company_id'   => $flchr->id,
                'billing_company_id' => $flchr->id,

                'internal_code'          => $c['internal_code'],
                'container_type_id'      => $types[$c['type']] ?? null,
                'container_size_id'      => $sizes[$c['size']] ?? null,
                'container_condition_id' => $c['condition'] ? ($conditions[$c['condition']] ?? null) : null,
                'container_grade_id'     => $grade,
                'material'               => 'steel',

                'status'      => $c['status'],
                'location_id' => $c['in_yard'] ? $yard?->id : null,
                'depot_id'    => $c['in_yard'] ? null : $depot?->id,

                'acquisition_cost'    => $c['acquisition_cost'],
                'pickup_cost'         => $c['pickup_cost'],
                'reconditioning_cost' => 0.00,

                'year_manufactured' => $c['year'],

                // Solo el Cargo Worthy sirve para exportar.
                'is_export_eligible' => $c['grade'] === 'CARGO_WORTHY',
                'csc_valid_through'  => null,

                'received_at' => $c['in_yard'] ? now()->subDays(30)->toDateString() : null,
            ];

            // Los pesos por defecto salen de la medida, para no tipearlos
            $size = $c['size'] ? ContainerSize::find($sizes[$c['size']] ?? null) : null;
            if ($size) {
                $attributes['tare_weight_lbs'] = $size->default_tare_lbs;
                $attributes['max_weight_lbs']  = $size->default_max_lbs;
            }

            // Si tiene número ISO se busca por ahí (es único).
            // Si no, se busca por el código interno.
            $key = $c['container_number']
                ? ['container_number' => $c['container_number']]
                : ['internal_code' => $c['internal_code']];

            if ($c['container_number']) {
                $attributes['container_number'] = $c['container_number'];
            }

            Container::updateOrCreate($key, $attributes);
        }
    }
}
