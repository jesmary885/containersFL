<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ContainerCondition;
use App\Models\ContainerGrade;
use App\Models\ContainerSize;
use App\Models\ContainerType;

class ContainerCatalogSeeder extends Seeder
{
     public function run(): void
    {
        $this->types();
        $this->sizes();
        $this->conditions();
        $this->grades();
    }

    /** Cómo está construido el contenedor */
    private function types(): void
    {
        $types = [
            ['dry',       'Dry / Estándar',      'Dry',          10],
            ['reefer',    'Refrigerado',         'Reefer',       20],
            ['open_top',  'Open Top',            'Open Top',     30],
            ['flat_rack', 'Flat Rack',           'Flat Rack',    40],
            ['other',     'Otro',                'Other',        999],
        ];

        foreach ($types as [$code, $name, $nameEn, $order]) {
            ContainerType::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'name_en' => $nameEn, 'sort_order' => $order, 'is_active' => true],
            );
        }
    }

    /** La medida. Los pesos se precargan al registrar el contenedor. */
    private function sizes(): void
    {
        // [código, nombre, pies, ¿high cube?, tara lbs, máximo lbs, orden]
        $sizes = [
            ['10FT',     '10 ft',            10.0, false,  2900,  22000, 10],
            ['20FT',     '20 ft Standard',   20.0, false,  4850,  67200, 20],
            ['20FT-HC',  '20 ft High Cube',  20.0, true,   5100,  67200, 30],
            ['40FT-STD', '40 ft Standard',   40.0, false,  8200,  67200, 40],
            ['40FT-HC',  '40 ft High Cube',  40.0, true,   8600,  67200, 50],
            ['45FT-HC',  '45 ft High Cube',  45.0, true,  10500,  67200, 60],
        ];

        foreach ($sizes as [$code, $name, $ft, $hc, $tare, $max, $order]) {
            ContainerSize::updateOrCreate(
                ['code' => $code],
                [
                    'name'             => $name,
                    'length_ft'        => $ft,
                    'is_high_cube'     => $hc,
                    'default_tare_lbs' => $tare,
                    'default_max_lbs'  => $max,
                    'sort_order'       => $order,
                    'is_active'        => true,
                ],
            );
        }
    }

    /**
     * El campo "TIPO" del Excel.
     * Lista provisional: ellos la editan desde la UI.
     */
    private function conditions(): void
    {
        // [código, nombre, nombre EN, línea de producto, orden]
        $conditions = [
            ['NEW',      'Nuevo',    'New',       'premium',  10],
            ['ONE_TRIP', 'One-Trip', 'One-Trip',  'premium',  20],
            ['USED',     'Usado',    'Used',      'standard', 30],
            ['OTHER',    'Otro',     'Other',     null,       999],
        ];

        foreach ($conditions as [$code, $name, $nameEn, $line, $order]) {
            ContainerCondition::updateOrCreate(
                ['code' => $code],
                [
                    'name'         => $name,
                    'name_en'      => $nameEn,
                    'product_line' => $line,
                    'sort_order'   => $order,
                    'is_active'    => true,
                ],
            );
        }
    }

    /**
     * La calidad. is_export_eligible es lo que dispara la advertencia
     * cuando alguien intenta vender para exportación algo que no califica.
     */
    private function grades(): void
    {
        // [código, nombre, nombre EN, ¿sirve para exportar?, orden]
        $grades = [
            ['CARGO_WORTHY', 'Cargo Worthy',       'Cargo Worthy',       true,  10],
            ['WWT',          'Wind & Water Tight', 'Wind & Water Tight', false, 20],
            ['AS_IS',        'AS-IS',              'AS-IS',              false, 30],
            ['OTHER',        'Otro',               'Other',              false, 999],
        ];

        foreach ($grades as [$code, $name, $nameEn, $export, $order]) {
            ContainerGrade::updateOrCreate(
                ['code' => $code],
                [
                    'name'               => $name,
                    'name_en'            => $nameEn,
                    'is_export_eligible' => $export,
                    'sort_order'         => $order,
                    'is_active'          => true,
                ],
            );
        }
    }
}
