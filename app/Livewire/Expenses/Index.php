<?php

namespace App\Livewire\Expenses;

use App\Enums\ExpenseStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Container;
use App\Models\Depot;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Support\CompanyContext;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LOS GASTOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── PARA QUÉ SIRVE DE VERDAD ──
 *
 * No es un libro de contabilidad. Es lo que hace que los números de
 * rentabilidad signifiquen algo.
 *
 * La pantalla de Ventas ya calcula margen por contenedor, y el detalle de
 * un viaje dice "queda para la empresa". Los dos números son mentira
 * mientras el combustible, los peajes y las reparaciones no estén
 * cargados en ninguna parte.
 *
 * ── A QUÉ SE PUEDE COLGAR UN GASTO ──
 *
 * A un contenedor, un viaje, un camión, una compra o un chofer. Es
 * opcional, pero es lo que convierte un gasto suelto en costo real de
 * algo. Un tanque de diésel sin viaje asociado es un número en una lista;
 * colgado del viaje, baja el margen de ese viaje.
 *
 * ── LO QUE NO HACE ──
 *
 * No registra pagos. Un gasto puede pagarse en partes y eso ya lo resuelve
 * expense_payments con su propia pantalla. Acá se registra que el gasto
 * existe y cuánto es.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess, WithPagination;

    protected string $permisoBase = 'expenses';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'categoria', except: '')]
    public string $categoria = '';

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'hasta', except: '')]
    public string $hasta = '';

    /* =====================================================================
     | EL PANEL
     * ================================================================== */

    public bool $editando = false;
    public ?int $expenseId = null;

    public ?int $expense_category_id = null;
    public ?int $supplier_id = null;
    public ?string $payee_name = null;

    public string $description = '';
    public ?string $notes = null;

    public $amount = null;
    public ?string $expense_date = null;
    public ?string $due_date = null;
    public ?string $supplier_invoice_number = null;

    /* A qué se cuelga. Todo opcional. */
    public ?int $container_id = null;
    public ?int $trip_id      = null;
    public ?int $vehicle_id   = null;
    public ?int $driver_id    = null;
    public ?int $depot_id     = null;

    public bool $is_billable        = false;
    public bool $is_1099_reportable = false;

    public function mount(): void
    {
        $this->exigirPermiso('view');

        /* Arranca con el mes en curso: es lo que se mira al entrar. */
        $this->desde = $this->desde ?: now()->startOfMonth()->toDateString();
        $this->hasta = $this->hasta ?: now()->endOfMonth()->toDateString();
    }

    public function updatingBuscar(): void    { $this->resetPage(); }
    public function updatingCategoria(): void { $this->resetPage(); }
    public function updatingEstado(): void    { $this->resetPage(); }
    public function updatingDesde(): void     { $this->resetPage(); }
    public function updatingHasta(): void     { $this->resetPage(); }

    /* =====================================================================
     | ABRIR Y CERRAR
     * ================================================================== */

    public function nuevo(): void
    {
        $this->exigirPermiso('create');

        $this->reset([
            'expenseId', 'expense_category_id', 'supplier_id', 'payee_name',
            'description', 'notes', 'amount', 'due_date', 'supplier_invoice_number',
            'container_id', 'trip_id', 'vehicle_id', 'driver_id', 'depot_id',
        ]);

        $this->expense_date = now()->toDateString();
        $this->is_billable = false;
        $this->is_1099_reportable = false;

        $this->resetValidation();

        $this->editando = true;
    }

    public function editar(int $id): void
    {
        $this->exigirPermiso('update');

        $e = Expense::findOrFail($id);

        $this->expenseId = $e->id;

        $this->expense_category_id = $e->expense_category_id;
        $this->supplier_id = $e->supplier_id;
        $this->payee_name  = $e->payee_name;

        $this->description = $e->description;
        $this->notes       = $e->notes;

        $this->amount       = $e->amount;
        $this->expense_date = $e->expense_date?->toDateString();
        $this->due_date     = $e->due_date?->toDateString();
        $this->supplier_invoice_number = $e->supplier_invoice_number;

        $this->container_id = $e->container_id;
        $this->trip_id      = $e->trip_id;
        $this->vehicle_id   = $e->vehicle_id;
        $this->driver_id    = $e->driver_id;
        $this->depot_id     = $e->depot_id;

        $this->is_billable        = (bool) $e->is_billable;
        $this->is_1099_reportable = (bool) $e->is_1099_reportable;

        $this->resetValidation();

        $this->editando = true;
    }

    public function cerrar(): void
    {
        $this->editando = false;
        $this->resetValidation();
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    public function guardar(): void
    {
        $this->exigirPermiso($this->expenseId ? 'update' : 'create');

        $this->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'supplier_id'         => ['nullable', 'exists:suppliers,id'],
            'payee_name'          => ['nullable', 'string', 'max:200'],

            'description' => ['required', 'string', 'max:255'],
            'notes'       => ['nullable', 'string', 'max:2000'],

            'amount'       => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'expense_date' => ['required', 'date'],
            'due_date'     => ['nullable', 'date', 'after_or_equal:expense_date'],

            'supplier_invoice_number' => ['nullable', 'string', 'max:50'],

            'container_id' => ['nullable', 'exists:containers,id'],
            'trip_id'      => ['nullable', 'exists:trips,id'],
            'vehicle_id'   => ['nullable', 'exists:vehicles,id'],
            'driver_id'    => ['nullable', 'exists:drivers,id'],
            'depot_id'     => ['nullable', 'exists:depots,id'],
        ], [], [
            'expense_category_id' => 'categoría',
            'description'         => 'descripción',
            'amount'              => 'importe',
            'expense_date'        => 'fecha',
            'due_date'            => 'vencimiento',
        ]);

        /* -----------------------------------------------------------------
         | HAY QUE SABER A QUIÉN SE LE PAGÓ
         |
         | O un proveedor del catálogo, o un nombre escrito a mano. Un
         | gasto sin destinatario no se puede reclamar, ni conciliar, ni
         | reportar en un 1099.
         * -------------------------------------------------------------- */
        if (! $this->supplier_id && blank($this->payee_name)) {
            $this->addError('payee_name',
                'Diga a quién se le pagó: elija un proveedor o escriba el nombre.');

            return;
        }

        $empresa = app(CompanyContext::class)->get();

        $e = $this->expenseId ? Expense::findOrFail($this->expenseId) : new Expense();

        if (! $this->expenseId) {
            $e->company_id     = $empresa?->id;
            $e->expense_number = $this->siguienteNumero($empresa?->id);
            $e->created_by     = auth()->id();
            $e->status         = ExpenseStatus::Pending;
        }

        $e->fill([
            'expense_category_id' => $this->expense_category_id,
            'supplier_id'         => $this->supplier_id ?: null,
            'payee_name'          => $this->payee_name ?: null,

            'description' => $this->description,
            'notes'       => $this->notes ?: null,

            'amount'       => (float) $this->amount,
            'expense_date' => $this->expense_date,
            'due_date'     => $this->due_date ?: null,

            'supplier_invoice_number' => $this->supplier_invoice_number ?: null,

            'container_id' => $this->container_id ?: null,
            'trip_id'      => $this->trip_id ?: null,
            'vehicle_id'   => $this->vehicle_id ?: null,
            'driver_id'    => $this->driver_id ?: null,
            'depot_id'     => $this->depot_id ?: null,

            'is_billable'        => $this->is_billable,
            'is_1099_reportable' => $this->is_1099_reportable,
        ]);

        $e->save();

        /*
         | El saldo se recalcula porque el importe pudo cambiar. Si un
         | gasto de $500 con $500 pagados se corrige a $800, deja de estar
         | pagado y tiene que volver a "pendiente".
         */
        $e->recalculateBalance()->save();

        $this->editando = false;

        session()->flash('exito', 'Gasto '.$e->expense_number.' guardado.');
    }

    protected function siguienteNumero(?int $companyId): string
    {
        $prefijo = 'EXP-'.now()->format('y').'-';

        $ultimo = Expense::where('company_id', $companyId)
            ->where('expense_number', 'like', $prefijo.'%')
            ->orderByDesc('expense_number')
            ->value('expense_number');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        do {
            $numero = $prefijo.str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
            $siguiente++;
        } while (Expense::where('company_id', $companyId)
                        ->where('expense_number', $numero)->exists());

        return $numero;
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        $base = fn () => Expense::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id))
            ->when($this->desde, fn ($q) => $q->whereDate('expense_date', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('expense_date', '<=', $this->hasta));

        $gastos = $base()
            ->with(['category:id,name', 'supplier:id,name'])

            ->when($this->buscar, function ($q) {
                $t = '%'.$this->buscar.'%';
                $q->where(fn ($qq) => $qq
                    ->where('expense_number', 'like', $t)
                    ->orWhere('description', 'like', $t)
                    ->orWhere('payee_name', 'like', $t));
            })

            ->when($this->categoria, fn ($q) => $q->where('expense_category_id', $this->categoria))
            ->when($this->estado,    fn ($q) => $q->where('status', $this->estado))

            ->orderByDesc('expense_date')->orderByDesc('id')
            ->paginate(25);

        return view('livewire.expenses.index', [
            'gastos' => $gastos,

            'kpis' => [
                'total'     => (float) $base()->sum('amount'),
                'porPagar'  => (float) $base()->whereIn('status', ['pending', 'partial'])->sum('balance'),
                'cantidad'  => $base()->count(),
            ],

            'categorias' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
            'estados'    => ExpenseStatus::options(),
            'proveedores'=> Supplier::where('is_active', true)->orderBy('name')->get(),
            'depositos'  => Depot::where('is_active', true)->orderBy('name')->get(),
            'choferes'   => Driver::where('is_active', true)
                ->orderBy('first_name')->orderBy('last_name')->get(),
            'camiones'   => Vehicle::where('is_active', true)->orderBy('plate_number')->get(),

            /*
             | Solo los viajes recientes. La lista completa crecería sin
             | límite y un desplegable de 4000 viajes no lo usa nadie.
             */
            'viajes' => Trip::query()
                ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id))
                ->orderByDesc('id')->limit(200)->get(),

            'contenedores' => Container::query()
                ->when($empresa, fn ($q) => $q->where('owner_company_id', $empresa->id))
                ->orderByDesc('id')->limit(300)->get(),
        ]);
    }
}
