<?php

namespace App\Livewire\Suppliers;

use App\Enums\SupplierType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PROVEEDORES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Compras › Proveedores. A quién le compramos.
 *
 * ── UNA TRAMPA DEL EXCEL QUE YA ESTÁ RESUELTA ──
 *
 * En el Excel la columna se llama "cliente" pero guarda al PROVEEDOR.
 * Quien importe datos de ahí tiene que saberlo: lo que dice cliente en
 * la hoja de compras es de quién compramos, no a quién vendemos.
 *
 * ── NO ES UNA COPIA DE CLIENTES ──
 *
 * Se parecen y son cosas distintas. Un cliente necesita certificado de
 * exención, autorización de tarjeta y aviso de cobranza. Un proveedor no
 * necesita nada de eso.
 *
 * ── NO BORRA ──
 *
 * Un proveedor tiene compras, depósitos y gastos colgando. Se desactiva.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination, AuthorizesAccess;

    protected string $permisoBase = 'suppliers';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipo = '';

    #[Url(as: 'estado', except: 'activos')]
    public string $estado = 'activos';

    #[Url(as: 'marca', except: '')]
    public string $marca = '';

    public int $porPagina = 15;

    /** El proveedor que espera confirmación para cambiar de estado. */
    public ?int $porCambiar = null;

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    public function updatingBuscar(): void { $this->resetPage(); }
    public function updatingTipo(): void   { $this->resetPage(); }
    public function updatingEstado(): void { $this->resetPage(); }

    public function filtrarPor(string $marca): void
    {
        $this->marca = $this->marca === $marca ? '' : $marca;
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'tipo', 'marca']);
        $this->estado = 'activos';
        $this->resetPage();
    }

    public function getHayFiltrosProperty(): bool
    {
        return filled($this->buscar) || filled($this->tipo)
            || filled($this->marca) || $this->estado !== 'activos';
    }

    /* =====================================================================
     | ACTIVAR Y DESACTIVAR
     * ================================================================== */

    public function pedirCambio(int $id): void   { $this->porCambiar = $id; }
    public function cancelarCambio(): void       { $this->porCambiar = null; }

    public function cambiarEstado(): void
    {
        $this->exigirPermiso('update');

        $proveedor = Supplier::find($this->porCambiar);

        if (! $proveedor) {
            $this->porCambiar = null;

            return;
        }

        $proveedor->update(['is_active' => ! $proveedor->is_active]);

        session()->flash('exito',
            $proveedor->is_active
                ? $proveedor->name.' quedó activo.'
                : $proveedor->name.' quedó desactivado. Ya no aparece al registrar compras nuevas.');

        $this->porCambiar = null;
    }

    public function render()
    {
        $proveedores = Supplier::query()
            ->withCount(['purchases', 'depots'])

            ->when($this->buscar, fn ($q) => $q->where(function ($qq) {
                $termino = '%'.trim($this->buscar).'%';

                $qq->where('name', 'like', $termino)
                   ->orWhere('supplier_number', 'like', $termino)
                   ->orWhere('contact_name', 'like', $termino)
                   ->orWhere('phone', 'like', $termino)
                   ->orWhere('email', 'like', $termino);
            }))

            ->when($this->tipo, fn ($q) => $q->where('type', $this->tipo))

            ->when($this->estado === 'activos',   fn ($q) => $q->where('is_active', true))
            ->when($this->estado === 'inactivos', fn ($q) => $q->where('is_active', false))

            ->when($this->marca === 'sin_datos',  fn ($q) => $q
                ->whereNull('phone')->whereNull('email'))

            ->orderByDesc('created_at')
            ->paginate($this->porPagina);

        return view('livewire.suppliers.index', [
            'proveedores' => $proveedores,
            'tipos'       => SupplierType::options(),

            'resumen' => [
                'activos'   => Supplier::where('is_active', true)->count(),
                'sinDatos'  => Supplier::where('is_active', true)
                                  ->whereNull('phone')->whereNull('email')->count(),
                'depositos' => \App\Models\Depot::where('is_active', true)->count(),
            ],
        ]);
    }
}
