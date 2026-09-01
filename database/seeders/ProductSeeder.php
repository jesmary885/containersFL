<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Los conceptos que pueden aparecer como línea en una factura.
        *Lo que importa acá es `taxable`: **DELIVERY y PICKUP van en `false`** porque el transporte no paga impuesto. Eso es lo que hace que en una venta de $2,750 el impuesto salga solo sobre los $2,300 del contenedor.
     */
    public function run(): void
    {
         // [código, nombre, nombre EN, tipo, precio por defecto, ¿paga impuesto?]
        $products = [
            ['CONT-SALE',   'Venta de contenedor',        'Container sale',        'container', null,   true],
            ['CONT-RENT',   'Renta de contenedor',        'Container rental',      'container', null,   true],

            // El transporte NO paga sales tax en Florida
            ['DELIVERY',    'Entrega / Delivery',         'Delivery',              'service',   null,   false],
            ['PICKUP',      'Recogida en depósito',       'Depot pickup',          'service',   null,   false],

            ['EXPORT-CERT', 'Certificado de exportación', 'Export certificate',    'service',   null,   false],
            ['STORAGE-FEE', 'Almacenaje',                 'Storage fee',           'fee',       0.00,   false],
            ['LATE-FEE',    'Cargo por mora',             'Late fee',              'fee',       100.00, false],
            ['CC-FEE',      'Recargo por tarjeta',        'Credit card surcharge', 'fee',       null,   false],
            ['DEPOSIT',     'Anticipo / Depósito',        'Deposit',               'fee',       null,   false],
            ['REPAIR',      'Reparación / Modificación',  'Repair / modification', 'service',   null,   true],
        ];

        foreach ($products as [$code, $name, $nameEn, $type, $price, $taxable]) {
            Product::updateOrCreate(
                ['code' => $code],
                [
                    'company_id'    => null,          // compartido entre las dos empresas
                    'name'          => $name,
                    'name_en'       => $nameEn,
                    'type'          => $type,
                    'default_price' => $price,
                    'taxable'       => $taxable,
                    'is_active'     => true,
                ],
            );
        }
    }
}
