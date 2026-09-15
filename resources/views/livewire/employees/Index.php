<?php

namespace App\Livewire\Employees;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Employee;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TRABAJADORES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Sistema › Trabajadores. La gente de la empresa.
 *
 * ── NO SON LOS USUARIOS DEL SISTEMA ──
 *
 * Y es la confusión que este módulo viene a deshacer. Un usuario es
 * quien entra con contraseña. Un trabajador es quien vende, maneja o
 * limpia.
 *
 * Miguelito cobra comisiones desde 2024 y probablemente no ha abierto un
 * sistema en su vida. Hasta ahora no existía en ninguna parte: su nombre
 * estaba suelto dentro de una celda del Excel.
 *
 * ── LO DICE LA MINUTA ──
 *
 * 8 de agosto: "No existe ficha de choferes ni de trabajadores: hoy solo
 * aparece el nombre suelto dentro del viaje."
 *
 * ── PERMISOS ──
 *
 * Usa los de `users` a propósito: es administración de personal, y quien
 * puede dar de alta a un usuario es quien decide quién trabaja aquí.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination, AuthorizesAccess;

    protected string $permisoBase = 'users';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'rol', except: '')]
    public string $rol = '';

    #[Url(as: 'estado', except: 'activos')]
    public string $estado = 'activos';

    public int $porPagina = 20;

    public ?int $porCambiar = null;

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    public function updatingBuscar(): void { $this->resetPage(); }
    public function updatingRol(): void    { $this->resetPage(); }
    public function updatingEstado(): void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'rol']);
        $this->estado = 'activos';
        $this->resetPage();
    }

    public function pedirCambio(int $id): void { $this->porCambiar = $id; }
    public function cancelarCambio(): void     { $this->porCambiar = null; }

    public function cambiarEstado(): void
    {
        $this->exigirPermiso('update');

        $trabajador = Employee::find($this->porCambiar);

        if (! $trabajador) {
            $this->porCambiar = null;

            return;
        }

        $trabajador->update(['is_active' => ! $trabajador->is_active]);

        session()->flash('exito', $trabajador->name.' quedó '
            .($trabajador->is_active ? 'activo.' : 'desactivado. Ya no aparece al facturar.'));

        $this->porCambiar = null;
    }

    public function render()
    {
        $trabajadores = Employee::query()
            ->with('company:id,code')
            ->withCount('sales')

            ->when($this->buscar, fn ($q) => $q->where(function ($qq) {
                $t = '%'.trim($this->buscar).'%';
                $qq->where('first_name', 'like', $t)
                   ->orWhere('last_name', 'like', $t)
                   ->orWhere('phone', 'like', $t);
            }))

            ->when($this->rol, fn ($q) => $q->where('role', $this->rol))

            ->when($this->estado === 'activos',   fn ($q) => $q->where('is_active', true))
            ->when($this->estado === 'inactivos', fn ($q) => $q->where('is_active', false))

            /* El ultimo registrado, primero. Igual que el resto del sistema. */
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->porPagina);

        return view('livewire.employees.index', [
            'trabajadores' => $trabajadores,
            'roles'        => Employee::ROLES,

            'resumen' => [
                'activos'   => Employee::active()->count(),
                'vendedores'=> Employee::salespeople()->count(),

                /*
                 | Cuántos no tienen teléfono.
                 |
                 | No es estadística: si hay que avisarle a Miguelito que
                 | se le pagó la comisión, hace falta el número.
                 */
                'sinTelefono' => Employee::active()->whereNull('phone')->count(),
            ],
        ]);
    }
}
