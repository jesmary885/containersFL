<?php

namespace App\Livewire\Users;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LISTADO DE USUARIOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Administración › Usuarios. Es el bullet literal del Módulo 2 del
 * acuerdo: "gestión de usuarios, autenticación y roles de permisos".
 *
 * ── POR QUÉ ESTE LISTADO NO FILTRA POR EMPRESA ──
 *
 * Todos los demás listados del sistema sí lo hacen, porque sus modelos
 * usan el trait BelongsToCompany. `users` NO lo usa, y es correcto: una
 * persona puede trabajar en las dos compañías a la vez. Esa relación
 * vive en la tabla pivote `company_user`, no en una columna
 * `users.company_id`.
 *
 * Así que aquí se ven todos los usuarios del sistema, con una columna
 * que dice a qué empresas entra cada uno. Filtrar por la empresa activa
 * escondería a la mitad de la plantilla y nadie entendería por qué.
 *
 * ── DAR DE BAJA, NO BORRAR ──
 *
 * No hay botón de eliminar. Un usuario firmó presupuestos, cerró ventas
 * y tiene comisiones colgando de su id: borrarlo dejaría documentos
 * apuntando a nadie, y `salesperson_id` es justo lo que hace falta para
 * saber que la venta la cerró Miguelito.
 *
 * Se desactiva. El middleware EnsureUserIsActive lo expulsa en su
 * siguiente clic, y los documentos viejos conservan su firma.
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

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    public int $porPagina = 15;

    /** El usuario que espera confirmación para cambiar de estado. */
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
        $this->reset(['buscar', 'rol', 'estado']);
        $this->resetPage();
    }

    /* =====================================================================
     | ACTIVAR Y DESACTIVAR
     * ================================================================== */

    public function pedirCambio(int $id): void
    {
        $this->porCambiar = $id;
    }

    public function cancelarCambio(): void
    {
        $this->porCambiar = null;
    }

    /**
     * Le da la vuelta al interruptor.
     *
     * ── LAS DOS PROTECCIONES ──
     *
     * 1. No puedes desactivarte a ti mismo. Es fácil de hacer sin querer
     *    —la fila propia está en la lista como cualquier otra— y el
     *    resultado es que te quedas fuera en el siguiente clic, con la
     *    pantalla de usuarios al otro lado de la puerta.
     *
     * 2. No se puede desactivar al último super administrador. Si se
     *    permite, el sistema queda sin nadie que pueda volver a crear
     *    usuarios y hay que entrar por la base de datos a mano.
     */
    public function cambiarEstado(): void
    {
        $this->exigirPermiso('update');

        $usuario = User::find($this->porCambiar);

        if (! $usuario) {
            $this->porCambiar = null;

            return;
        }

        if ($usuario->id === auth()->id()) {
            session()->flash('error', 'No puede desactivar su propio usuario.');
            $this->porCambiar = null;

            return;
        }

        if ($usuario->is_active && $this->esElUltimoSuperAdmin($usuario)) {
            session()->flash('error',
                'No se puede desactivar al único super administrador del sistema. '
                .'Asigne ese rol a otra persona antes.');
            $this->porCambiar = null;

            return;
        }

        $usuario->update(['is_active' => ! $usuario->is_active]);

        session()->flash('exito',
            $usuario->is_active
                ? $usuario->name.' quedó activo.'
                : $usuario->name.' quedó desactivado. Se le cerrará la sesión en su siguiente clic.');

        $this->porCambiar = null;
    }

    protected function esElUltimoSuperAdmin(User $usuario): bool
    {
        if (! $usuario->hasRole('super_admin')) {
            return false;
        }

        return User::role('super_admin')->where('is_active', true)->count() <= 1;
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        $usuarios = User::query()
            ->with(['roles:id,name', 'companies:id,name,code'])

            ->when($this->buscar, function ($q) {
                $texto = '%'.$this->buscar.'%';

                $q->where(fn ($sub) => $sub
                    ->where('name', 'like', $texto)
                    ->orWhere('email', 'like', $texto)
                    ->orWhere('phone', 'like', $texto));
            })

            /*
             | El scope role() lo trae Spatie. Filtra por el nombre del
             | rol sin que haya que escribir el join a mano.
             */
            ->when($this->rol, fn ($q) => $q->role($this->rol))

            ->when($this->estado !== '', fn ($q) => $q->where('is_active', $this->estado === 'activos'))

            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate($this->porPagina);

        return view('livewire.users.index', [
            'usuarios' => $usuarios,

            'roles' => Role::orderBy('name')->get(['id', 'name']),

            'resumen' => [
                'total'        => User::count(),
                'activos'      => User::where('is_active', true)->count(),
                'sinEmpresa'   => User::doesntHave('companies')->count(),
                'sinRol'       => User::doesntHave('roles')->count(),
            ],
        ]);
    }
}
