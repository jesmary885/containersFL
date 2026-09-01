<?php

namespace Database\Seeders;

use App\Models\Depot;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DepotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $maritime = Supplier::where('name', 'Maritime Container')->first();

        // ⚠️ MONTOS PROVISIONALES.
        // Denisse tiene que mandar la lista real de depósitos con:
        //   - cuánto cobra cada uno por sacar un contenedor
        //   - cuánto cobra por día si te pasas del plazo
        //   - cuántos días te dan para retirar
        // Mientras tanto quedan en 0/null y el usuario los escribe en cada operación.
        $depots = [
            [
                'code'        => 'MARITIME',
                'name'        => 'Maritime Container',
                'supplier_id' => $maritime?->id,
                'city'        => 'Miami',
                'state'       => 'FL',
                'zip'         => null,

                'default_pickup_fee'  => 100.00,   // ⚠️ CONFIRMAR
                'daily_late_fee'      => 10.00,    // ⚠️ CONFIRMAR
                'default_pickup_days' => 14,       // ⚠️ CONFIRMAR
                'default_miles'       => null,     // distancia habitual a la yarda

                'notes' => 'Depósito principal. Retiro por número de release.',
            ],
            [
                'code'        => 'DIRECT',
                'name'        => 'Directo de la calle',
                'supplier_id' => null,
                'city'        => null,
                'state'       => 'FL',
                'zip'         => null,

                // Cuando el contenedor se compra directo, no hay fee de depósito.
                'default_pickup_fee'  => 0.00,
                'daily_late_fee'      => null,
                'default_pickup_days' => null,
                'default_miles'       => null,

                'notes' => 'Compra directa sin depósito. Sin fee de recogida ni plazo.',
            ],
        ];

        foreach ($depots as $depot) {
            Depot::updateOrCreate(
                ['code' => $depot['code']],
                array_merge($depot, [
                    'address'      => null,
                    'latitude'     => null,
                    'longitude'    => null,
                    'contact_name' => null,
                    'phone'        => null,
                    'email'        => null,
                    'is_active'    => true,
                ]),
            );
        }
    }
}
