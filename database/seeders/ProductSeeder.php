<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * ═══════════════════════════════════════════════════════════════════════
     * EL CATÁLOGO DE CONCEPTOS FACTURABLES
     * ═══════════════════════════════════════════════════════════════════════
     *
     *
     *   1. Se agregó la columna usable_in, para que la mora, el recargo
     *      de tarjeta y el almacenaje NO salgan en el desplegable del
     *      presupuesto.
     *
     *   2. Se cargaron los PRECIOS BASE que faltaban. Antes casi todos
     *      estaban en null, y por eso al elegir un concepto el importe se
     *      quedaba en cero y había que escribirlo a mano cada vez.
     *
     * ── LOS TRES QUE SIGUEN EN null, Y POR QUÉ ──
     *
     *   CONT-SALE / CONT-RENT   el precio sale del CONTENEDOR que se
     *                           elija, no del concepto. Cada unidad tiene
     *                           el suyo (columnas list_price y
     *                           monthly_rate).
     *
     *   DELIVERY                se calcula millas × tarifa por milla
     *                           (RB-031). Un monto fijo aquí sería mentira:
     *                           no cuesta lo mismo Homestead que Orlando.
     *                           La tarifa vive en Configuración
     *                           (operations.default_rate_per_mile = 3.50).
     *
     *   PICKUP                  fee FIJO por depósito (RB-031), y cada
     *                           depósito tiene el suyo en su ficha. El
     *                           formulario lo trae de ahí.
     *
     *   CC-FEE                  es un porcentaje del total, no un monto.
     *                           Lo calcula la calculadora.
     *
     *   DEPOSIT                 cada anticipo es distinto.
     *
     * ── ⚠️ LOS QUE HAY QUE CONFIRMAR CON EL CLIENTE ──
     *
     * Los marqué con "CONFIRMAR". Son números que puse yo para que el
     * sistema funcione hoy; cámbialos aquí y vuelve a correr el seeder, o
     * cámbialos después desde la pantalla de catálogo cuando exista.
     * ═══════════════════════════════════════════════════════════════════════
     */
    public function run(): void
    {
        // [código, nombre, nombre EN, tipo, precio base, ¿paga tax?, dónde se usa]
        $products = [

            /* ===============================================================
             | CONTENEDORES — el precio sale de la unidad elegida
             * ============================================================ */

            ['CONT-SALE',   'Venta de contenedor',        'Container sale',
             'container', null,   true,  'both'],

            ['CONT-RENT',   'Renta de contenedor',        'Container rental',
             'container', null,   true,  'both'],

            /* ===============================================================
             | TRANSPORTE — RB-005: nunca lleva sales tax
             * ============================================================ */

            // Se calcula millas × tarifa. Ver PricingResolver::ratePerMile().
            ['DELIVERY',    'Entrega / Delivery',         'Delivery',
             'service',   null,   false, 'both'],

           // Fee fijo del depósito. NO se cotiza al cliente: el pickup es
            // depósito -> yarda y es un COSTO de FLCHR (RB-031).
            //
            // Queda en 'invoice' y no fuera del catálogo porque RS
            // Transport SÍ se lo factura a FLCHR en el invoice semanal
            // intercompañía (RB-003), un renglón por viaje. Si se
            // eliminara, esa factura no se podría armar.
            ['PICKUP',      'Recogida en depósito',       'Depot pickup',
             'service',   null,   false, 'invoice'],

            /* ===============================================================
             | SERVICIOS
             * ============================================================ */

            // RB-056: incluido en el precio de exportación, que ya es más
            // alto justamente por eso. Si además se agrega como renglón,
            // se cobra dos veces.
            //
            // RB-016: y tampoco es un ingreso. El CSC Safety Survey lo
            // emite un inspector externo y hay que pagárselo. Va como
            // gasto, categoría CERTS.
            //
            // Se deja en el catálogo con 'none' en vez de borrarlo para
            // que quede constancia de la decisión. scopeUsableIn() nunca
            // lo va a devolver.
            ['EXPORT-CERT', 'Certificado de exportación', 'Export certificate',
             'service',   null,   false, 'none'],

            // ⚠️ CONFIRMAR el monto y si paga tax.
            ['REPAIR',      'Reparación / Modificación',  'Repair / modification',
             'service',   null,   true,  'both'],

            /* ===============================================================
             | RECARGOS — NO se cotizan, solo se facturan
             * ============================================================ */

            // RB-021: desde el 3er día. Igual al ajuste yard.daily_storage_fee.
            ['STORAGE-FEE', 'Almacenaje',                 'Storage fee',
             'fee',       25.00,  false, 'invoice'],

            // RB-024: $100 por contrato, sin importar los días de atraso.
            ['LATE-FEE',    'Cargo por mora',             'Late fee',
             'fee',       100.00, false, 'invoice'],

            // RB-009: es un % del total, lo calcula la calculadora sola.
            ['CC-FEE',      'Recargo por tarjeta',        'Credit card surcharge',
             'fee',       null,   false, 'invoice'],

            /* ===============================================================
             | OTROS
             * ============================================================ */

            ['DEPOSIT',     'Anticipo / Depósito',        'Deposit',
             'fee',       null,   false, 'both'],
        ];

        foreach ($products as [$code, $name, $nameEn, $type, $price, $taxable, $usableIn]) {
            Product::updateOrCreate(
                ['code' => $code],
                [
                    'company_id'    => null,          // compartido entre las dos empresas
                    'name'          => $name,
                    'name_en'       => $nameEn,
                    'type'          => $type,
                    'usable_in'     => $usableIn,
                    'default_price' => $price,
                    'taxable'       => $taxable,
                    'is_active'     => true,
                ],
            );
        }
    }
}
