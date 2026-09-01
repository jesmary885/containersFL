<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\DocumentSequence;
use Illuminate\Database\Seeder;

class DocumentSequenceSeeder extends Seeder
{
    /**
     * La numeración. **Las dos facturas arrancan donde quedó el Excel + QuickBooks**, para que no haya salto ni repetición.
     */
    public function run(): void
    {
        $flchr = Company::where('code', 'FLCHR')->firstOrFail();
        $rst   = Company::where('code', 'RST')->firstOrFail();

        // Todos los tipos arrancan en 1, salvo las facturas.
        $types = [
            'estimate'           => ['prefix' => 'EST-', 'padding' => 4],
            'invoice'            => ['prefix' => null,   'padding' => 4],
            'sale'               => ['prefix' => 'SO-',  'padding' => 4],
            'purchase'           => ['prefix' => 'PO-',  'padding' => 4],
            'rental'             => ['prefix' => 'RNT-', 'padding' => 4],
            'trip'               => ['prefix' => 'TRP-', 'padding' => 5],
            'payment'            => ['prefix' => 'PAY-', 'padding' => 5],
            'expense'            => ['prefix' => 'EXP-', 'padding' => 5],
            'settlement'         => ['prefix' => 'SET-', 'padding' => 4],
            'commission'         => ['prefix' => 'COM-', 'padding' => 4],
            'export_certificate' => ['prefix' => 'CERT-','padding' => 4],
        ];

        // ⚠️ CRÍTICO: estos dos números vienen del Excel y de QuickBooks.
        // Si arrancan mal, se repiten números de factura con lo ya emitido.
        $invoiceStart = [
            'FLCHR' => 1358,
            'RST'   => 1240,
        ];

        foreach ([$flchr, $rst] as $company) {
            foreach ($types as $type => $config) {
                $next = ($type === 'invoice')
                    ? $invoiceStart[$company->code]
                    : 1;

                DocumentSequence::updateOrCreate(
                    ['company_id' => $company->id, 'type' => $type],
                    [
                        'prefix'        => $config['prefix'],
                        'next_number'   => $next,
                        'padding'       => $config['padding'],
                        'resets_yearly' => false,
                        'current_year'  => null,
                    ],
                );
            }
        }
    }
}
