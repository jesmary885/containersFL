<?php

namespace Database\Seeders;

use App\Models\DeliveryRate;
use Illuminate\Database\Seeder;

/**
 * Los tres rangos que nombró Denisse en la reunión del 16 de septiembre.
 *
 * Van como GENERALES (company_id null) para que las dos compañías los usen.
 * Si una necesita otros, se cargan los suyos desde la pantalla y esos pisan
 * a los generales.
 *
 * EL PRIMER RANGO ES UN PUNTO DE PARTIDA, NO UN DATO DEL CLIENTE.
 *
 * La minuta dice, textual: "0 a 100 millas como variable". Los otros dos
 * traen número ($4.50 y $5.00); ese no. Se carga en $4.00 —por debajo del
 * escalón siguiente— para que el sistema cotice desde el primer día.
 *
 * No se espera respuesta de nadie para usarlo: la pantalla de tarifas lo
 * deja editable y lo que se escriba ahí es lo que manda. Igual que los
 * precios de venta.
 */
class DeliveryRateSeeder extends Seeder
{
    public function run(): void
    {
        $rangos = [
            [
                'min_miles'     => 0,
                'max_miles'     => 100,
                'rate_per_mile' => 4.00,
                'label'         => '0 – 100 mi',
                'notes'         => 'En la reunión del 16-09 este rango quedó como "variable", sin '
                                  .'número. Se puso $4.00 —por debajo del siguiente escalón— como '
                                  .'punto de partida. Edítelo cuando quiera: manda lo que diga acá.',
            ],
            [
                'min_miles'     => 100,
                'max_miles'     => 200,
                'rate_per_mile' => 4.50,
                'label'         => '100 – 200 mi',
                'notes'         => 'Confirmado por Denisse en la reunión del 16-09.',
            ],
            [
                'min_miles'     => 200,
                'max_miles'     => null,
                'rate_per_mile' => 5.00,
                'label'         => 'Más de 200 mi',
                'notes'         => 'Confirmado por Denisse en la reunión del 16-09.',
            ],
        ];

        foreach ($rangos as $r) {
            /*
             | updateOrCreate por los límites y no por id: volver a correr
             | el seeder no debe duplicar rangos, y tampoco debe pisar un
             | precio que el cliente ya corrigió desde la pantalla... por
             | eso solo se escriben los campos de identidad si la fila ya
             | existe.
             */
            DeliveryRate::firstOrCreate(
                [
                    'company_id' => null,
                    'min_miles'  => $r['min_miles'],
                    'max_miles'  => $r['max_miles'],
                ],
                [
                    'rate_per_mile' => $r['rate_per_mile'],
                    'label'         => $r['label'],
                    'notes'         => $r['notes'],
                    'is_active'     => true,
                ],
            );
        }
    }
}
