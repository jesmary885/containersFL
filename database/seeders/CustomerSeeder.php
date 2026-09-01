<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Los clientes son **compartidos**: un solo cliente sirve para las dos empresas.
    *Lo único imprescindible acá es el **cliente intercompañía**: FLCHR tiene que existir como *cliente* de RS Transport, porque es a quien RS le factura los viajes cada semana. Ese cliente lleva `related_company_id` apuntando a FLCHR, que es lo que lo marca como especial.
     */
    public function run(): void
    {
        $flchr = Company::where('code', 'FLCHR')->firstOrFail();

        // ── EL CLIENTE INTERCOMPAÑÍA ──────────────────────────────
        // Sin esto la factura semanal de RS Transport a FLCHR no tiene
        // a quién emitirse. No borrar.
        Customer::updateOrCreate(
            ['customer_number' => 'CUST-0001'],
            [
                'type'               => 'business',
                'company_name'       => $flchr->legal_name,
                'display_name'       => $flchr->name,
                'primary_phone'      => $flchr->phone,
                'primary_email'      => $flchr->email,
                'related_company_id' => $flchr->id,        // ← esto lo marca como intercompañía
                'tax_exempt'         => true,              // venta entre empresas del grupo
                'allow_credit_card'  => false,
                'notes'              => 'Cliente intercompañía. RS Transport le factura los viajes semanalmente. '
                                      . 'No borrar ni fusionar con otro cliente.',
                'is_active'          => true,
            ],
        );

        // ── CLIENTES DE EJEMPLO ───────────────────────────────────
        // ⚠️ Estos dos son de muestra, para poder probar el sistema.
        // La cartera real se migra desde el Excel con un comando aparte.
        $customers = [
            [
                'customer_number' => 'CUST-0002',
                'type'            => 'business',
                'company_name'    => 'Cliente Empresa Demo LLC',
                'first_name'      => null,
                'last_name'       => null,
                'display_name'    => 'Cliente Empresa Demo LLC',
                'primary_phone'   => '(305) 000-0000',
                'primary_email'   => 'demo@ejemplo.com',
                'tax_exempt'      => false,
                'allow_credit_card' => true,
                'source'          => 'referral',
                'contacts'        => [
                    ['name' => 'Contacto Principal', 'role' => 'Owner',
                     'email' => 'demo@ejemplo.com', 'phone' => '(305) 000-0000',
                     'is_primary' => true],
                    ['name' => 'Contacto Contabilidad', 'role' => 'Accounting',
                     'email' => 'pagos@ejemplo.com', 'phone' => null,
                     'is_primary' => false],
                ],
            ],
            [
                'customer_number' => 'CUST-0003',
                'type'            => 'individual',
                'company_name'    => null,
                'first_name'      => 'Cliente',
                'last_name'       => 'Particular Demo',
                'display_name'    => 'Cliente Particular Demo',
                'primary_phone'   => '(786) 000-0000',
                'primary_email'   => 'particular@ejemplo.com',
                'tax_exempt'      => false,
                'allow_credit_card' => false,
                'source'          => 'facebook',
                'contacts'        => [],
            ],
        ];

        foreach ($customers as $data) {
            $contacts = $data['contacts'];
            unset($data['contacts']);

            $customer = Customer::updateOrCreate(
                ['customer_number' => $data['customer_number']],
                array_merge($data, [
                    'sunbiz_verified' => false,
                    'credit_hold'     => false,
                    'is_active'       => true,
                ]),
            );

            foreach ($contacts as $contact) {
                CustomerContact::updateOrCreate(
                    ['customer_id' => $customer->id, 'name' => $contact['name']],
                    array_merge($contact, [
                        // Los dos contactos reciben la cobranza. Eso es lo que
                        // permite que el aviso llegue a todos y no solo al primero.
                        'notify_invoices'   => true,
                        'notify_reminders'  => true,
                        'preferred_channel' => 'email',
                    ]),
                );
            }
        }
    }
}
