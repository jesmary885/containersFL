<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Yardas propias. **Los depósitos de proveedores no van acá**, tienen su propia tabla.
     */
    public function run(): void
    {
        $flchr = Company::where('code', 'FLCHR')->firstOrFail();

        Location::updateOrCreate(
            ['name' => 'Yarda Principal'],                     // ⚠️ nombre real de la yarda
            [
                'company_id' => $flchr->id,
                'type'       => 'yard',
                'address'    => [
                    'line1' => null,                            // ⚠️ dirección real
                    'city'  => 'Miami',
                    'state' => 'FL',
                    'zip'   => null,
                ],
                'latitude'          => null,
                'longitude'         => null,
                'daily_storage_fee' => 0.00,                    // ⚠️ confirmar cargo por día
                'free_storage_days' => 2,
                'is_active'         => true,
            ],
        );

        // Destino genérico para viajes al puerto
        Location::updateOrCreate(
            ['name' => 'Port of Miami'],
            [
                'company_id' => null,
                'type'       => 'port',
                'address'    => ['city' => 'Miami', 'state' => 'FL'],
                'is_active'  => true,
            ],
        );

        Location::updateOrCreate(
            ['name' => 'Port Everglades'],
            [
                'company_id' => null,
                'type'       => 'port',
                'address'    => ['city' => 'Fort Lauderdale', 'state' => 'FL'],
                'is_active'  => true,
            ],
        );
    }
}
