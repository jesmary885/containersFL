<?php

namespace App\Livewire;

use Livewire\Component;

use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {

      /*
        |--------------------------------------------------------------------------
        | DATOS DE PRUEBA
        |--------------------------------------------------------------------------
        | Estos datos serán reemplazados posteriormente por consultas
        | reales a la base de datos.
        */

        $indicadores = [
            'clientes'        => 125,
            'contenedores'    => 86,
            'rentas_activas'  => 82,
            'ventas_mes'      => 25450,
            'viajes_mes'      => 24,
            'por_cobrar'      => 600,
        ];

        /*
        |--------------------------------------------------------------------------
        | PAGOS PENDIENTES
        |--------------------------------------------------------------------------
        */

        $pagosPendientes = collect([
            [
                'cliente'     => 'ABC Corp',
                'contenedor'  => 'CNT-001',
                'vencido'     => '09 Ago',
                'monto'       => 150,
            ],
            [
                'cliente'     => 'XYZ Inc',
                'contenedor'  => 'CNT-024',
                'vencido'     => '06 Ago',
                'monto'       => 300,
            ],
            [
                'cliente'     => 'John Smith',
                'contenedor'  => 'CNT-035',
                'vencido'     => '03 Ago',
                'monto'       => 150,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | DATOS PARA GRÁFICA DE VENTAS
        |--------------------------------------------------------------------------
        */

        $ventasMensuales = [
            'labels' => [
                'Mar',
                'Abr',
                'May',
                'Jun',
                'Jul',
                'Ago',
            ],

            'data' => [
                18200,
                21500,
                19800,
                23400,
                22100,
                25450,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | DATOS PARA GRÁFICA DE CUENTAS POR COBRAR
        |--------------------------------------------------------------------------
        */

        $estadoCuentas = [
            'labels' => [
                'Pagadas',
                'Pendientes',
                'Vencidas',
            ],

            'data' => [
                72,
                8,
                3,
            ],
        ];

        return view('livewire.dashboard', [
           'indicadores'      => $indicadores,
            'pagosPendientes'  => $pagosPendientes,
            'totalMorosos'     => $pagosPendientes->count(),
            'totalDeuda'       => $pagosPendientes->sum('monto'),
            'ventasMensuales'  => $ventasMensuales,
            'estadoCuentas'    => $estadoCuentas,
        ]);
    }
}
