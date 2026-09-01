<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        $flchr = Company::where('code', 'FLCHR')->firstOrFail();
        $rst   = Company::where('code', 'RST')->firstOrFail();

        $people = [
            [
                'name'      => 'Soporte Técnico',
                'email'     => 'soporte@containersfl.com',       // ⚠️ REVISAR
                'password'  => 'CambiarEsto2026!',               // ⚠️ CAMBIAR en el primer login
                'phone'     => null,
                'role'      => 'super_admin',
                'companies' => [$flchr->id => true, $rst->id => false],
            ],
            [
                'name'      => 'Denisse',                        // ⚠️ falta apellido
                'email'     => 'denisse@containersfl.com',       // ⚠️ REVISAR
                'password'  => 'CambiarEsto2026!',
                'phone'     => null,
                'role'      => 'admin',
                // Trabaja en las dos. Arranca en FLCHR.
                'companies' => [$flchr->id => true, $rst->id => false],
            ],
            [
                'name'      => 'Rosa',                           // ⚠️ falta apellido
                'email'     => 'rosa@containersfl.com',          // ⚠️ REVISAR
                'password'  => 'CambiarEsto2026!',
                'phone'     => null,
                'role'      => 'accounting',
                'companies' => [$flchr->id => true, $rst->id => false],
            ],
            [
                'name'      => 'Michael',                        // ⚠️ falta apellido
                'email'     => 'michael@containersfl.com',       // ⚠️ REVISAR
                'password'  => 'CambiarEsto2026!',
                'phone'     => null,
                'role'      => 'sales',
                'companies' => [$flchr->id => true],
            ],
            [
                'name'      => 'Operaciones',                    // ⚠️ reemplazar por la persona real
                'email'     => 'operaciones@containersfl.com',   // ⚠️ REVISAR
                'password'  => 'CambiarEsto2026!',
                'phone'     => null,
                'role'      => 'operations',
                'companies' => [$flchr->id => true, $rst->id => false],
            ],

            // Usuarios de choferes. Solo si les van a dar acceso al panel
            // para ver sus viajes. Si no, se borran de acá y el chofer
            // queda solo como ficha en la tabla drivers.
            [
                'name'      => 'Chofer 1',                       // ⚠️ nombre real
                'email'     => 'chofer1@rstransport.com',        // ⚠️ REVISAR
                'password'  => 'CambiarEsto2026!',
                'phone'     => null,
                'role'      => 'driver',
                'companies' => [$rst->id => true],
            ],
            [
                'name'      => 'Chofer 2',                       // ⚠️ nombre real
                'email'     => 'chofer2@rstransport.com',        // ⚠️ REVISAR
                'password'  => 'CambiarEsto2026!',
                'phone'     => null,
                'role'      => 'driver',
                'companies' => [$rst->id => true],
            ],
        ];

        foreach ($people as $person) {
            $user = User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'name'              => $person['name'],
                    'password'          => Hash::make($person['password']),
                    'phone'             => $person['phone'],
                    'locale'            => 'es',
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$person['role']]);

            // A qué compañías tiene acceso, y con cuál arranca
            $pivot = [];
            foreach ($person['companies'] as $companyId => $isDefault) {
                $pivot[$companyId] = ['is_default' => $isDefault];
            }
            $user->companies()->sync($pivot);
        }
    }
}
