<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    /**
     * El `default_pay_percent` es el **nivel 2** de la cascada del pago al chofer:
    *```
    *30% global (settings)  →  % de este chofer  →  lo que pongas en el viaje
    *```
    *Cada nivel pisa al anterior. Si dejas `default_pay_percent` en null, el chofer usa el 30% global. Y en el viaje siempre se puede cambiar el porcentaje **o escribir directamente el monto**, que pisa todo.
     */
    public function run(): void
    {
        $rst     = Company::where('code', 'RST')->firstOrFail();
        $carrier = Carrier::where('is_internal', true)->first();

        // ⚠️ Nombres y datos provisionales. Falta la lista real de choferes.
        $drivers = [
            [
                'first_name'         => 'Chofer',
                'last_name'          => 'Uno',
                'user_email'         => 'chofer1@rstransport.com',  // enlaza con su usuario
                'phone'              => null,
                'license_number'     => null,
                'license_expires_at' => null,
                // null = usa el 30% global
                'default_pay_percent' => null,
                'default_pay_amount'  => null,
            ],
            [
                'first_name'         => 'Chofer',
                'last_name'          => 'Dos',
                'user_email'         => 'chofer2@rstransport.com',
                'phone'              => null,
                'license_number'     => null,
                'license_expires_at' => null,
                // Ejemplo de un chofer con porcentaje propio distinto al global
                'default_pay_percent' => 35.00,
                'default_pay_amount'  => null,
            ],
        ];

        foreach ($drivers as $d) {
            $user = $d['user_email'] ? User::where('email', $d['user_email'])->first() : null;

            Driver::updateOrCreate(
                ['first_name' => $d['first_name'], 'last_name' => $d['last_name']],
                [
                    'company_id'          => $rst->id,
                    'carrier_id'          => $carrier?->id,
                    'user_id'             => $user?->id,
                    'phone'               => $d['phone'],
                    'email'               => $d['user_email'],
                    'license_number'      => $d['license_number'],
                    'license_expires_at'  => $d['license_expires_at'],
                    'default_pay_percent' => $d['default_pay_percent'],
                    'default_pay_amount'  => $d['default_pay_amount'],
                    'is_1099_reportable'  => true,
                    'is_active'           => true,
                ],
            );
        }
    }
}
