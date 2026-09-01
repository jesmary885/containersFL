<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::updateOrCreate(
            ['code' => 'FLCHR'],
            [
                'name'        => 'Florida Containers & Homes',        // ⚠️ REVISAR nombre comercial exacto
                'legal_name'  => 'Florida Containers & Homes LLC',    // ⚠️ REVISAR razón social
                'slug'        => 'flchr',

                'ein'                       => null,                  // ⚠️ pedir a Denisse
                'sales_tax_number'          => null,                  // ⚠️ pedir a Denisse
                'resale_certificate_number' => null,
                'resale_certificate_expires_at' => null,

                'collects_sales_tax'        => true,
                'default_tax_rate'          => 7.00,
                'credit_card_fee_percent'   => 3.50,

                // Esta es LA marca importante: es la compañía que se precarga
                // cuando alguien registra un contenedor.
                'is_default_container_owner' => true,

                'is_default_transport_provider' => false,
                'brand_color'                   => '#1B4D8F',

                'address_line1' => null,                              // ⚠️ pedir dirección fiscal
                'city'          => 'Miami',
                'state'         => 'FL',
                'zip'           => null,
                'country'       => 'US',

                'phone'   => null,
                'email'   => null,
                'website' => null,

                'invoice_template'     => 'default',
                'invoice_footer_terms' => 'Payment due on receipt. A 3.5% surcharge applies to credit card payments. '
                                        . 'All sales are final. Buyer is responsible for site accessibility.',

                // Esto se imprime al pie de la factura
                'payment_instructions' => [
                    'zelle'   => null,                                 // ⚠️ email o teléfono de Zelle
                    'bank'    => 'Bank of America',
                    'account' => null,                                 // ⚠️ número de cuenta
                    'routing' => null,                                 // ⚠️ routing
                    'swift'   => null,
                    'notes'   => 'Please include the invoice number with your payment.',
                ],

                'is_active' => true,
            ],
        );

        Company::updateOrCreate(
            ['code' => 'RST'],
            [
                'name'        => 'RS Transport',
                'legal_name'  => 'RS Transport LLC',                  // ⚠️ REVISAR razón social
                'slug'        => 'rs-transport',

                'ein'              => null,                            // ⚠️
                'sales_tax_number' => null,

                // El transporte no paga sales tax en Florida.
                // Aun así el campo queda editable por si aparece un caso mixto.
                'collects_sales_tax'      => false,
                'default_tax_rate'        => 0.00,
                'credit_card_fee_percent' => 3.50,

                'is_default_container_owner' => false,

                'is_default_transport_provider' => true,   // ← RB-002
                'brand_color'                   => '#C2410C',

                'city'    => 'Miami',
                'state'   => 'FL',
                'country' => 'US',

                'invoice_template'     => 'default',
                'invoice_footer_terms' => 'Payment due on receipt. Transportation services are not subject to sales tax.',

                'payment_instructions' => [
                    'zelle'   => null,                                 // ⚠️
                    'bank'    => 'Bank of America',
                    'account' => null,
                    'routing' => null,
                    'notes'   => 'Please include the invoice number with your payment.',
                ],

                'is_active' => true,
            ],
        );
    }
}
