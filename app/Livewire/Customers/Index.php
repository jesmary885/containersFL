<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LISTADO DE CLIENTES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Comercial › Clientes.
 *
 * ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──
 *
 * 1 · Apareció el contador de DOCUMENTOS VENCIDOS O POR VENCER.
 *
 *     Es el mismo criterio que el de "sin dirección": un número que no
 *     es una estadística, es una lista de trabajo. Cada cliente que sale
 *     ahí tiene un papel que se venció o está a punto, y eso se convierte
 *     en un problema el día que hace falta y no antes.
 *
 * 2 · La pantalla ahora tiene las dos formas de ver la tabla del
 *     listado de presupuestos: lista compacta y tarjetas.
 *
 * ── POR QUÉ NO FILTRA POR EMPRESA ──
 *
 * Todos los demás listados sí lo hacen, porque sus modelos usan el trait
 * `BelongsToCompany`. `customers` NO lo usa, y es correcto: la tabla no
 * tiene `company_id` a propósito.
 *
 * El mismo cliente le compra un contenedor a FLCHR y le paga el
 * transporte a RST. Registrarlo dos veces sería tener dos historiales,
 * dos direcciones que mantener y dos versiones de la verdad sobre si
 * está exento de impuesto. Las reglas del negocio lo dicen claro:
 * registrar una sola vez y reutilizar.
 *
 * ── EL BUSCADOR BUSCA EN LOS CONTACTOS TAMBIÉN ──
 *
 * El scope `search()` del modelo ya lo hace, y es de las cosas más
 * útiles que tiene: es normal que llamen diciendo "soy Carlos, de la
 * constructora" sin acordarse del nombre de la empresa.
 *
 * ── DAR DE BAJA, NO BORRAR ──
 *
 * El modelo usa borrado lógico, pero esta pantalla ni siquiera lo
 * ofrece: un cliente tiene presupuestos, facturas y pagos colgando de su
 * id. Se desactiva. Deja de aparecer en el buscador de documentos nuevos
 * y sigue existiendo en los viejos.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination, AuthorizesAccess;

    protected string $permisoBase = 'customers';

    /** A cuántos días vista se considera que un papel "está por vencer". */
    public const DIAS_DE_AVISO = 30;

    /* =====================================================================
     | LOS FILTROS
     * ================================================================== */

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipo = '';

    #[Url(as: 'estado', except: 'activos')]
    public string $estado = 'activos';

    /** Filtros de un clic desde los contadores de arriba. */
    #[Url(as: 'marca', except: '')]
    public string $marca = '';

    /* ---------------------------------------------------------------
     | EL ORDEN DE ENTRADA: EL ULTIMO REGISTRADO, PRIMERO
     |
     | Estaba alfabetico por nombre. Suena prolijo y no sirve: quien
     | abre esta pantalla acaba de registrar a alguien, o esta buscando
     | a alguien concreto —y para eso esta el buscador—.
     |
     | Alfabetico, el cliente que acabas de crear aparece en la pagina
     | cuatro y parece que no se guardo.
     |
     | Se puede volver a ordenar por nombre desde la cabecera de la
     | tabla cuando haga falta.
     * ------------------------------------------------------------ */
    #[Url(as: 'orden', except: 'created_at')]
    public string $ordenarPor = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $direccion = 'desc';

    public int $porPagina = 15;

    /** El cliente que espera confirmación para cambiar de estado. */
    public ?int $porCambiar = null;

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    public function updatingBuscar(): void { $this->resetPage(); }
    public function updatingTipo(): void   { $this->resetPage(); }
    public function updatingEstado(): void { $this->resetPage(); }

    public function ordenar(string $columna): void
    {
        if ($this->ordenarPor === $columna) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->direccion  = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Los contadores son botones.
     *
     * Pulsar el mismo otra vez lo apaga. Sin eso, la única forma de
     * quitar el filtro sería el botón de limpiar, y nadie lo relaciona
     * con el número que acaba de pulsar.
     */
    public function filtrarPor(string $marca): void
    {
        $this->marca = $this->marca === $marca ? '' : $marca;
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'tipo', 'marca']);

        $this->estado     = 'activos';
        $this->ordenarPor = 'created_at';
        $this->direccion  = 'desc';

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

    public function cambiarEstado(): void
    {
        $this->exigirPermiso('update');

        $cliente = Customer::find($this->porCambiar);

        if (! $cliente) {
            $this->porCambiar = null;

            return;
        }

        $cliente->update(['is_active' => ! $cliente->is_active]);

        session()->flash('exito',
            $cliente->is_active
                ? $cliente->name.' quedó activo.'
                : $cliente->name.' quedó desactivado. Ya no aparece al crear documentos nuevos.');

        $this->porCambiar = null;
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        $columnasValidas = ['display_name', 'customer_number', 'created_at'];

        $orden = in_array($this->ordenarPor, $columnasValidas, true)
            ? $this->ordenarPor
            : 'created_at';

        /* -----------------------------------------------------------------
         | LOS DOCUMENTOS QUE PIDEN ATENCIÓN
         |
         | Un papel cuenta si tiene fecha de vencimiento y esa fecha ya
         | pasó, o cae dentro de los próximos 30 días.
         |
         | Los que no vencen —una foto, una nota— no entran: no hay nada
         | que renovar en ellos.
         * -------------------------------------------------------------- */
        $documentoQueVence = fn ($q) => $q
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(self::DIAS_DE_AVISO));

        $clientes = Customer::query()
            ->withCount(['addresses', 'contacts'])

            /*
             | Se cuentan aparte de la relación completa: la lista no
             | necesita los documentos, solo saber cuántos están
             | pidiendo atención. Traerlos todos para contarlos sería
             | leer archivos enteros de la base para mostrar un número.
             */
            ->withCount(['documents as documentos_alerta_count' => $documentoQueVence])

            ->when($this->buscar, fn ($q) => $q->search($this->buscar))

            ->when($this->tipo, fn ($q) => $q->where('type', $this->tipo))

            ->when($this->estado === 'activos',    fn ($q) => $q->where('is_active', true))
            ->when($this->estado === 'inactivos',  fn ($q) => $q->where('is_active', false))

            /* -------------------------------------------------------------
             | LOS FILTROS DE LOS CONTADORES
             |
             | 'sin_direccion' es el que importa. Un cliente sin dirección
             | obliga a teclearla entera en cada documento que se le haga,
             | y es la razón por la que el formulario de presupuesto tuvo
             | que llevar aquella casilla de "guardar en la ficha".
             * ---------------------------------------------------------- */
            ->when($this->marca === 'sin_direccion', fn ($q) => $q->doesntHave('addresses'))
            ->when($this->marca === 'exentos',       fn ($q) => $q->where('tax_exempt', true))
            ->when($this->marca === 'retenidos',     fn ($q) => $q->where('credit_hold', true))
            ->when($this->marca === 'papeles',
                fn ($q) => $q->whereHas('documents', $documentoQueVence))

            ->orderBy($orden, $this->direccion === 'asc' ? 'asc' : 'desc')
            ->paginate($this->porPagina);

        return view('livewire.customers.index', [
            'clientes' => $clientes,
            'tipos'    => CustomerType::options(),

            'resumen' => [
                'activos'      => Customer::where('is_active', true)->count(),
                'sinDireccion' => Customer::where('is_active', true)->doesntHave('addresses')->count(),
                'exentos'      => Customer::where('tax_exempt', true)->count(),
                'retenidos'    => Customer::where('credit_hold', true)->count(),

                'papeles' => Customer::where('is_active', true)
                    ->whereHas('documents', $documentoQueVence)
                    ->count(),
            ],
        ]);
    }
}
