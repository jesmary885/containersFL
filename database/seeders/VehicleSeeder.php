<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $rst     = Company::where('code', 'RST')->firstOrFail();
        $flchr   = Company::where('code', 'FLCHR')->firstOrFail();
        $carrier = Carrier::where('is_internal', true)->first();

        // ⚠️ Datos provisionales. Falta la lista real de la flota
        // (VIN, placa, marca, modelo, año, vencimientos de registro y seguro).
        $vehicles = [
            [
                'company_id' => $rst->id,
                'carrier_id' => $carrier?->id,
                'vin'        => null,
                'plate_number' => 'TRUCK-01',        // ⚠️ placa real
                'type'       => 'truck',
                'make'       => null,
                'model'      => null,
                'year'       => null,
            ],
            [
                'company_id' => $rst->id,
                'carrier_id' => $carrier?->id,
                'vin'        => null,
                'plate_number' => 'CHASSIS-01',      // ⚠️
                'type'       => 'chassis',
            ],
            [
                'company_id' => $flchr->id,
                'carrier_id' => null,
                'vin'        => null,
                'plate_number' => 'FORKLIFT-01',     // el montacargas de la yarda
                'type'       => 'forklift',
            ],
        ];

        foreach ($vehicles as $v) {
            Vehicle::updateOrCreate(
                ['plate_number' => $v['plate_number']],
                array_merge([
                    'make'                    => null,
                    'model'                   => null,
                    'year'                    => null,
                    'registration_expires_at' => null,
                    'insurance_expires_at'    => null,
                    'is_active'               => true,
                ], $v),
            );
        }
    }
}
