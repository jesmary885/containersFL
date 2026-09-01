<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Company;
use Illuminate\Database\Seeder;

class CarrierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rst = Company::where('code', 'RST')->firstOrFail();

        // El transportista interno del grupo. Es RS Transport.
        // Que tenga company_id lleno es lo que lo marca como "de la casa"
        // y lo que dispara la facturación entre empresas.
        Carrier::updateOrCreate(
            ['name' => 'RS Transport'],
            [
                'company_id'            => $rst->id,
                'is_internal'           => true,
                'contact_name'          => null,
                'phone'                 => null,
                'email'                 => null,
                'default_rate_per_mile' => null,     // ⚠️ CONFIRMAR tarifa por milla
                'is_active'             => true,
            ],
        );

        // Ejemplo de transportista externo. Sin company_id.
        Carrier::updateOrCreate(
            ['name' => 'Transportista Externo'],      // ⚠️ reemplazar por los reales
            [
                'company_id'            => null,
                'is_internal'           => false,
                'default_rate_per_mile' => null,
                'is_active'             => true,
            ],
        );
    }
}
