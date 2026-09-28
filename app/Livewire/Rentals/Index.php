<?php

namespace App\Livewire\Rentals;

use App\Enums\BillingCycle;
use App\Enums\RentalStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Customer;
use App\Models\Rental;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LOS CONTRATOS DE RENTA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DOS NEGOCIOS DISTINTOS EN LA MISMA PANTALLA ──
 *
 * Salen de dos hojas distintas del Excel y no se parecen tanto como
 * parece:
 *
 *   MENSUAL    el cliente se lleva un contenedor nuestro. El ciclo se
 *              ancla al día de la entrega, no al primero de mes.
 *
 *   DE YARDA   el cliente deja SU contenedor guardado acá. Se cobra por
 *              día, y además entrada, salida, pintura y reparación.
 *
 * La diferencia de fondo no es mes contra día: es quién tiene el
 * contenedor. En la mensual sale de la yarda; en la de yarda entra.
 *
 * ── LO QUE TIENE QUE CONTESTAR ESTA PANTALLA ──
 *
 * Cuánto entra cada mes de forma fija, y qué contratos están vencidos.
 * Lo segundo es lo que se persigue por teléfono el lunes.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess, WithPagination;

    protected string $permisoBase = 'rentals';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'estado', except: 'active')]
    public string $estado = 'active';

    #[Url(as: 'ciclo', except: '')]
    public string $ciclo = '';

    #[Url(as: 'cliente', except: '')]
    public string $cliente = '';

    /** '' todos · 'vencidos' · 'sin_periodo' */
    #[Url(as: 'marca', except: '')]
    public string $marca = '';

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    public function updatingBuscar(): void  { $this->resetPage(); }
    public function updatingEstado(): void  { $this->resetPage(); }
    public function updatingCiclo(): void   { $this->resetPage(); }
    public function updatingCliente(): void { $this->resetPage(); }
    public function updatingMarca(): void   { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'estado', 'ciclo', 'cliente', 'marca']);
        $this->resetPage();
    }

    public function getHayFiltrosProperty(): bool
    {
        return filled($this->buscar) || $this->estado !== 'active' || filled($this->ciclo)
            || filled($this->cliente) || filled($this->marca);
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        $base = fn () => Rental::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id));

        $rentas = $base()
            ->with(['customer:id,name'])
            ->withCount(['periods as periodos_vencidos' => fn ($q) => $q
                ->where('status', 'pending')
                ->whereDate('due_date', '<', now())])

            ->when($this->buscar, function (Builder $q) {
                $t = '%'.$this->buscar.'%';
                $q->where(function (Builder $qq) use ($t) {
                    $qq->where('contract_number', 'like', $t)
                       ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $t));
                });
            })

            ->when($this->estado,  fn ($q) => $q->where('status', $this->estado))
            ->when($this->ciclo,   fn ($q) => $q->where('billing_cycle', $this->ciclo))
            ->when($this->cliente, fn ($q) => $q->where('customer_id', $this->cliente))

            ->when($this->marca === 'vencidos', fn ($q) => $q
                ->whereHas('periods', fn ($p) => $p
                    ->where('status', 'pending')
                    ->whereDate('due_date', '<', now())))

            /*
             | SIN PERÍODO GENERADO: contratos activos que no tienen ni un
             | período. Son los que están entregados pero nunca se
             | facturaron, o sea dinero parado sin que nadie lo vea.
             */
            ->when($this->marca === 'sin_periodo', fn ($q) => $q
                ->where('status', RentalStatus::Active)
                ->whereDoesntHave('periods'))

            ->orderByDesc('id')
            ->paginate(25);

        /*
         | El ingreso mensual fijo.
         |
         | Solo cuenta las mensuales activas: una renta de yarda se cobra
         | por día y sumarla acá daría un número que no significa nada.
         */
        $mensualFijo = (float) $base()
            ->where('status', RentalStatus::Active)
            ->where('billing_cycle', BillingCycle::Monthly)
            ->sum('monthly_rate');

        return view('livewire.rentals.index', [
            'rentas' => $rentas,

            'kpis' => [
                'activas' => $base()->where('status', RentalStatus::Active)->count(),

                'mensualFijo' => $mensualFijo,

                'vencidas' => $base()
                    ->whereHas('periods', fn ($p) => $p
                        ->where('status', 'pending')
                        ->whereDate('due_date', '<', now()))
                    ->count(),

                'sinPeriodo' => $base()
                    ->where('status', RentalStatus::Active)
                    ->whereDoesntHave('periods')
                    ->count(),
            ],

            'estados'  => RentalStatus::options(),
            'ciclos'   => BillingCycle::options(),
            'clientes' => Customer::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
