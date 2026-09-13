<?php

namespace App\Livewire\Depots;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Depot;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * DEPÓSITOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Compras › Depósitos.
 *
 * ── QUÉ ES UN DEPÓSITO ──
 *
 * El patio de un tercero donde el proveedor deja los contenedores que le
 * compramos, hasta que vamos a buscarlos. No es nuestro y no es la
 * yarda.
 *
 * ── PARA QUÉ SIRVE TENERLOS FICHADOS ──
 *
 * Por dos números que nadie recuerda de memoria:
 *
 *   EL COSTO DEL PICKUP     traer una unidad de Medley no cuesta lo
 *                           mismo que traerla de Jacksonville. Con el
 *                           depósito fichado, el sistema lo sabe.
 *
 *   LOS DÍAS LIBRES         cuánto tiempo dejan el contenedor ahí sin
 *                           cobrar, y cuánto cobran después. Es lo que
 *                           avisa cuando un release se está poniendo caro
 *                           por no ir a buscarlo.
 *
 * ── EL PICKUP NUNCA SE LE COTIZA AL CLIENTE ──
 *
 * Es costo nuestro. El cliente paga el delivery desde la yarda hasta su
 * terreno; lo que cuesta traer el contenedor al patio es nuestro
 * problema. Confundirlos es cobrar dos veces un transporte.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination, AuthorizesAccess;

    protected string $permisoBase = 'depots';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'estado', except: 'activos')]
    public string $estado = 'activos';

    public int $porPagina = 15;

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    public function updatingBuscar(): void { $this->resetPage(); }
    public function updatingEstado(): void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->buscar = '';
        $this->estado = 'activos';
        $this->resetPage();
    }

    public function render()
    {
        $depositos = Depot::query()
            ->with('supplier:id,name')
            ->withCount('purchases')

            ->when($this->buscar, fn ($q) => $q->where(function ($qq) {
                $t = '%'.trim($this->buscar).'%';
                $qq->where('name', 'like', $t)
                   ->orWhere('code', 'like', $t)
                   ->orWhere('city', 'like', $t)
                   ->orWhere('contact_name', 'like', $t);
            }))

            ->when($this->estado === 'activos',   fn ($q) => $q->where('is_active', true))
            ->when($this->estado === 'inactivos', fn ($q) => $q->where('is_active', false))

            ->orderBy('name')
            ->paginate($this->porPagina);

        return view('livewire.depots.index', [
            'depositos' => $depositos,

            'resumen' => [
                'activos' => Depot::where('is_active', true)->count(),

                /*
                 | Cuántos no tienen costo de pickup cargado.
                 |
                 | No es una estadística: cada uno de esos obliga a
                 | inventar el costo cuando se compra ahí, y ese costo
                 | entra en el precio del contenedor.
                 */
                'sinCosto' => Depot::where('is_active', true)
                                ->whereNull('default_pickup_fee')->count(),
            ],
        ]);
    }
}
