<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Seeder;

class CustomerAddressSeeder extends Seeder
{
    /**
     * ═══════════════════════════════════════════════════════════════════════
     * DIRECCIONES DE LOS CLIENTES DE PRUEBA
     * ═══════════════════════════════════════════════════════════════════════
     *
     * ── POR QUÉ HACÍA FALTA ESTE ARCHIVO ──
     *
     * Preguntaste por qué al elegir un cliente no se llenaban las
     * direcciones, teniendo el sistema una tabla para guardarlas.
     *
     * La respuesta es que la tabla existe, el código que copia la
     * dirección existe y funciona, pero **la tabla está vacía**. Ningún
     * seeder creaba direcciones. Los clientes de demo nacieron con
     * nombre, teléfono y correo, y sin una sola dirección.
     *
     * O sea que el formulario buscaba la dirección del cliente, no
     * encontraba ninguna, y dejaba los campos en blanco. Correctamente,
     * pero en blanco.
     *
     * ── LO QUE FALTA DE VERDAD ──
     *
     * Este seeder tapa el hueco para que puedas probar hoy, pero la
     * solución real es la pantalla de clientes, que todavía no existe:
     * la ruta comercial.clientes.index apunta a Placeholder.
     *
     * Mientras tanto, el formulario de presupuesto trae una casilla nueva
     * —"Guardar esta dirección en la ficha del cliente"— que hace que la
     * primera vez que escribas la dirección de alguien, quede guardada
     * para siempre. Se llena una vez y nunca más.
     *
     * ── LOS DOS TIPOS DE DIRECCIÓN ──
     *
     *   is_default_billing    la fiscal, la que va en el documento
     *   is_default_shipping   dónde se deja el contenedor
     *
     * El cliente empresa de abajo tiene las dos distintas a propósito:
     * oficina en Doral, obra en Homestead. Es el caso que hace que
     * BILL TO y SHIP TO tengan que ser campos separados, y sirve para
     * probar que el interruptor de "la entrega va a otra dirección" se
     * enciende solo.
     *
     * ⚠️ Son direcciones inventadas. La cartera real se migra del Excel.
     * ═══════════════════════════════════════════════════════════════════════
     */
    public function run(): void
    {
        $direcciones = [

            // El cliente intercompañía: FLCHR como cliente de RS Transport.
            'CUST-0001' => [
                [
                    'label'              => 'Oficina principal',
                    'type'               => 'billing',
                    'line1'              => '8200 NW 27th St',
                    'line2'              => 'Suite 100',
                    'city'               => 'Doral',
                    'state'              => 'FL',
                    'zip'                => '33122',
                    'is_default_billing' => true,
                ],
            ],

            // Empresa con oficina y obra en sitios distintos.
            'CUST-0002' => [
                [
                    'label'              => 'Oficina',
                    'type'               => 'billing',
                    'line1'              => '1450 NW 87th Ave',
                    'line2'              => 'Suite 210',
                    'city'               => 'Doral',
                    'state'              => 'FL',
                    'zip'                => '33172',
                    'is_default_billing' => true,
                ],
                [
                    'label'               => 'Obra Homestead',
                    'type'                => 'shipping',
                    'line1'               => '29500 SW 187th Ave',
                    'line2'               => 'Portón trasero',
                    'city'                => 'Homestead',
                    'state'               => 'FL',
                    'zip'                 => '33030',
                    'is_default_shipping' => true,
                ],
            ],

            // Persona física: una sola dirección, sirve para las dos cosas.
            'CUST-0003' => [
                [
                    'label'               => 'Casa',
                    'type'                => 'both',
                    'line1'               => '742 SW 12th Ave',
                    'city'                => 'Miami',
                    'state'               => 'FL',
                    'zip'                 => '33135',
                    'is_default_billing'  => true,
                    'is_default_shipping' => true,
                ],
            ],
        ];

        foreach ($direcciones as $numeroCliente => $lista) {

            $cliente = Customer::where('customer_number', $numeroCliente)->first();

            if (! $cliente) {
                continue;   // ese cliente de demo no está: no es un error
            }

            foreach ($lista as $datos) {

                /*
                 | updateOrCreate por cliente + línea 1.
                 |
                 | Así el seeder se puede correr las veces que haga falta
                 | sin que se dupliquen las direcciones. Y si alguien ya
                 | corrigió una a mano en la base, la clave no coincide y
                 | se respeta lo que hay.
                 */
                CustomerAddress::updateOrCreate(
                    [
                        'customer_id' => $cliente->id,
                        'line1'       => $datos['line1'],
                    ],
                    $datos + ['country' => 'US'],
                );
            }
        }
    }
}