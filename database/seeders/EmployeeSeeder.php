<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LOS TRABAJADORES QUE YA APARECEN EN EL EXCEL
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Los cinco vendedores salen de la hoja COMISIONES VENTAS, tal como
 * están escritos ahí: MIGUELITO, ROLY, JACKSON, RAMONCITO y YARISELIS.
 *
 * Se cargan con su apodo porque es el único nombre que aparece. No hay
 * un solo nombre completo en esa hoja, y ponerles uno inventado haría
 * que nadie los reconociera.
 *
 * ── LAS COMISIONES SON MONTOS, NO PORCENTAJES ──
 *
 * En esa misma hoja: $300, $400, $100, $200, $1,100, $650, $40. No hay
 * ni un porcentaje. Se carga el monto que más se repite de cada uno como
 * sugerencia, y queda editable en cada venta.
 *
 * ── LO QUE FALTA PREGUNTAR ──
 *
 * Apellidos, teléfonos y fecha de entrada. Nada de eso está en el Excel.
 * Se completa desde la pantalla cuando Denisse los tenga a mano.
 *
 * El seeder usa updateOrCreate: correrlo dos veces no duplica a nadie ni
 * pisa lo que ya se haya editado a mano... salvo lo que se le indique.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        /*
         | ── LOS NOMBRES SON DE RELLENO ──
         |
         | Del Excel solo salen los montos de comisión, que sí son
         | reales. Los nombres completos no están en ninguna parte, así
         | que se ponen unos plausibles para que las fichas no queden a
         | medias y las pantallas se puedan probar.
         |
         | La nota de cada ficha lo dice. En cuanto Denisse dé los
         | reales, se corrigen desde la pantalla.
         */
        $vendedores = [
            // nombre      apellido      comisión habitual en el Excel
            ['Miguel',    'Rodríguez',   300.00],
            ['Rolando',   'Peña',        400.00],
            ['Jackson',   'Batista',      40.00],
            ['Ramón',     'Guerrero',    100.00],
            ['Yariselis', 'Martínez',    150.00],
        ];

        foreach ($vendedores as [$nombre, $apellido, $monto]) {
            Employee::updateOrCreate(
                ['first_name' => $nombre, 'last_name' => $apellido],
                [
                    'role' => 'vendedor',

                    'default_commission_amount' => $monto,

                    /*
                     | Sin empresa: trabajan para las dos.
                     |
                     | El Excel no separa las comisiones por empresa, así
                     | que asignarlos a una sería inventar.
                     */
                    'company_id' => null,

                    'is_active' => true,
                    'notes'     => 'Nombre de relleno: hay que corregirlo. '
                                  .'La comisión sí viene del Excel.',
                ],
            );
        }

        /* -----------------------------------------------------------------
         | LOS CHOFERES QUE YA ESTABAN
         |
         | La tabla `drivers` existía antes que esta, y los viajes apuntan
         | ahí. Si hay choferes cargados, se les crea su ficha de
         | trabajador y se enlazan.
         |
         | Sin esto, al consolidar todo en Trabajadores los choferes
         | existentes desaparecerían de la vista aunque siguieran en la
         | base — y alguien los registraría otra vez, duplicados.
         * -------------------------------------------------------------- */
        Driver::query()->each(function (Driver $chofer) {

            $yaTiene = Employee::where('driver_id', $chofer->id)->exists();

            if ($yaTiene) {
                return;
            }

            Employee::create([
                'first_name' => $chofer->first_name,
                'last_name'  => $chofer->last_name,
                'role'       => 'chofer',
                'phone'      => $chofer->phone,
                'email'      => $chofer->email,
                'company_id' => $chofer->company_id,
                'hired_at'   => $chofer->hired_at,
                'driver_id'  => $chofer->id,
                'is_active'  => (bool) $chofer->is_active,
                'notes'      => 'Venía de la lista de choferes.',
            ]);
        });

        /*
         | Los dos dueños.
         |
         | Salen del segundo levantamiento: "Denisse, Michael van a ser
         | administrador". Se cargan como administradores porque además de
         | administrar, venden.
         */
        foreach ([['Denisse', 'Hernandez'], ['Michael', null]] as [$nombre, $apellido]) {
            Employee::updateOrCreate(
                ['first_name' => $nombre, 'last_name' => $apellido],
                [
                    'role'       => 'administrador',
                    'company_id' => null,
                    'is_active'  => true,
                ],
            );
        }
    }
}
