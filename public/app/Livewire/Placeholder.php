<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * PANTALLA PROVISIONAL
 * ====================
 *
 * Ocupa el lugar de los módulos que todavía no existen, para que el
 * menú completo se pueda navegar mientras construimos.
 *
 * Un solo componente sirve para las 17 rutas: el título sale del
 * nombre de la ruta, así que "comercial.clientes.index" se convierte
 * solo en "Comercial › Clientes".
 *
 * Se va borrando a medida que cada módulo real lo reemplaza.
 */
#[Layout('layouts.app')]
class Placeholder extends Component
{
    public function render()
    {
        /* -----------------------------------------------------------
         | El nombre de la ruta actual, por ejemplo:
         |   "comercial.clientes.index"
         |
         | El ?? '' cubre el caso raro de una ruta sin nombre: sin eso
         | explode() recibiría null y PHP 8.2 avisa.
         * -------------------------------------------------------- */
        $nombreRuta = request()->route()?->getName() ?? '';

        /* -----------------------------------------------------------
         | Se parte por los puntos y se descarta el último pedazo,
         | que siempre es "index", "create" o "edit" y no le dice
         | nada al usuario.
         |
         |   comercial.clientes.index  →  ['comercial', 'clientes']
         * -------------------------------------------------------- */
        $partes = array_filter(explode('.', $nombreRuta));

        if (count($partes) > 1) {
            array_pop($partes);
        }

        /* -----------------------------------------------------------
         | Se limpia cada pedazo y se unen con una flecha.
         |
         |   str_replace  →  "cuentas_por_cobrar" queda "cuentas por cobrar"
         |   ucfirst      →  la primera letra en mayúscula
         * -------------------------------------------------------- */
        $titulo = collect($partes)
            ->map(fn (string $parte) => ucfirst(str_replace(['_', '-'], ' ', $parte)))
            ->implode(' › ');

        return view('livewire.placeholder', [
            'titulo' => $titulo ?: 'Módulo',
        ]);
    }
}