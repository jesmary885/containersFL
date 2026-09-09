<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Container;

class ContainerPriceSeeder extends Seeder
{
    /**
     * ═══════════════════════════════════════════════════════════════════════
     * PRECIOS DE EJEMPLO PARA EL INVENTARIO DE PRUEBA
     * ═══════════════════════════════════════════════════════════════════════
     *
     * La migración agregó las columnas list_price y monthly_rate a los
     * contenedores, pero las dejó vacías. Este seeder les pone un valor
     * para que puedas probar el llenado automático del precio en el
     * presupuesto sin tener que escribirlos a mano uno por uno.
     *
     * ── ⚠️ SON NÚMEROS INVENTADOS ──
     *
     * Los saqué del costo de adquisición más un margen razonable. NO son
     * la lista de precios real. Cuando migres el inventario de verdad
     * desde el Excel, estos se pisan.
     *
     * ── POR QUÉ SOLO LLENA LOS QUE ESTÁN VACÍOS ──
     *
     * El `whereNull` de abajo es la protección importante. Si mañana
     * corres `db:seed` completo por cualquier motivo, este seeder NO
     * pisa los precios que alguien ya haya escrito en la pantalla de
     * inventario. Solo rellena huecos.
     *
     * Es la diferencia entre un seeder que se puede correr cien veces sin
     * miedo y uno que hay que pensárselo antes.
     * ═══════════════════════════════════════════════════════════════════════
     */
    public function run(): void
    {
        // internal_code => [precio de venta, renta mensual]
        //
        // Renta en null = esa unidad no se ofrece en renta.
        // Venta en null = esa unidad no se ofrece en venta.
        $precios = [
            'Unit #1'   => [2400.00,  225.00],   // 40HC usado cargo worthy
            'Unit #2'   => [1950.00,  175.00],   // 20FT usado WWT
            'Unit #3'   => [4200.00,  350.00],   // 40HC one-trip
            'Unit #4'   => [1350.00,  null],     // 20FT as-is, en reacondicionamiento
            'Unit #5'   => [2300.00,  200.00],   // 40 STD, todavía en el depósito
            'Unit #6'   => [1200.00,  150.00],   // 20FT sin clasificar
            'Reefer #1' => [8900.00,  850.00],   // reefer 40HC
        ];

        foreach ($precios as $codigo => [$venta, $renta]) {

            $contenedor = Container::where('internal_code', $codigo)->first();

            if (! $contenedor) {
                continue;
            }

            $cambios = [];

            if ($contenedor->list_price === null && $venta !== null) {
                $cambios['list_price'] = $venta;
            }

            if ($contenedor->monthly_rate === null && $renta !== null) {
                $cambios['monthly_rate'] = $renta;
            }

            if ($cambios) {
                $contenedor->forceFill($cambios)->save();
            }
        }
    }
}
