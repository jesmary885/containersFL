<?php

namespace App\Livewire\Trips;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Trip;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LOS VIAJES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── LO QUE TIENE QUE CONTESTAR ESTA PANTALLA ──
 *
 * Tres preguntas, y las tres son de dinero:
 *
 *   ¿Qué hay por facturar?    viajes completados que todavía no están en
 *                             ninguna factura. Es plata trabajada sin cobrar.
 *
 *   ¿Qué le debo a los        viajes completados con el pago del chofer
 *   choferes?                 pendiente.
 *
 *   ¿Qué hay para hoy?        lo programado, para saber si sale.
 *
 * Los contadores van arriba porque son la razón de entrar acá. La lista es
 * el detalle de lo que dicen.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess, WithPagination;

    protected string $permisoBase = 'trips';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipo = '';

    #[Url(as: 'cliente', except: '')]
    public string $cliente = '';

    #[Url(as: 'chofer', except: '')]
    public string $chofer = '';

    /** '' todos · 'por_facturar' · 'por_pagar' */
    #[Url(as: 'marca', except: '')]
    public string $marca = '';

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    public function updatingBuscar(): void  { $this->resetPage(); }
    public function updatingEstado(): void  { $this->resetPage(); }
    public function updatingTipo(): void    { $this->resetPage(); }
    public function updatingCliente(): void { $this->resetPage(); }
    public function updatingChofer(): void  { $this->resetPage(); }
    public function updatingMarca(): void   { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'estado', 'tipo', 'cliente', 'chofer', 'marca']);
        $this->resetPage();
    }

    public function getHayFiltrosProperty(): bool
    {
        return filled($this->buscar) || filled($this->estado) || filled($this->tipo)
            || filled($this->cliente) || filled($this->chofer) || filled($this->marca);
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        /*
         | La consulta base, reutilizable.
         |
         | Se devuelve como closure y no como query para que cada contador
         | arranque de cero: si compartieran la misma instancia, el primer
         | ->where() se quedaría pegado en todos los demás.
         */
        $base = fn () => Trip::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id));

        $viajes = $base()
            ->with(['customer:id,name', 'driver:id,first_name,last_name', 'container:id,container_number,internal_code'])

            ->when($this->buscar, function (Builder $q) {
                $t = '%'.$this->buscar.'%';
                $q->where(function (Builder $qq) use ($t) {
                    $qq->where('trip_number', 'like', $t)
                       ->orWhere('destination_zip', 'like', $t)
                       ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $t));
                });
            })

            ->when($this->estado,  fn ($q) => $q->where('status', $this->estado))
            ->when($this->tipo,    fn ($q) => $q->where('type', $this->tipo))
            ->when($this->cliente, fn ($q) => $q->where('customer_id', $this->cliente))
            ->when($this->chofer,  fn ($q) => $q->where('driver_id', $this->chofer))

            /*
             | POR FACTURAR: completado, se cobra, y sin factura todavía.
             |
             | El `customer_price > 0` es importante: un reposicionamiento
             | completado no está "por facturar", es que no se factura.
             */
            ->when($this->marca === 'por_facturar', fn ($q) => $q
                ->where('status', TripStatus::Completed)
                ->where('customer_price', '>', 0)
                ->whereNull('intercompany_invoice_id')
                ->where('trip_payment_status', '!=', 'paid'))

            ->when($this->marca === 'por_pagar', fn ($q) => $q
                ->where('status', TripStatus::Completed)
                ->where('driver_pay', '>', 0)
                ->where('driver_payment_status', 'pending'))

            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.trips.index', [
            'viajes' => $viajes,

            'kpis' => [
                'programados' => $base()->where('status', TripStatus::Scheduled)->count(),

                'porFacturar' => $base()
                    ->where('status', TripStatus::Completed)
                    ->where('customer_price', '>', 0)
                    ->whereNull('intercompany_invoice_id')
                    ->where('trip_payment_status', '!=', 'paid')
                    ->count(),

                'montoPorFacturar' => (float) $base()
                    ->where('status', TripStatus::Completed)
                    ->where('customer_price', '>', 0)
                    ->whereNull('intercompany_invoice_id')
                    ->where('trip_payment_status', '!=', 'paid')
                    ->sum('customer_price'),

                'porPagarChofer' => (float) $base()
                    ->where('status', TripStatus::Completed)
                    ->where('driver_payment_status', 'pending')
                    ->sum('driver_pay'),
            ],

            'estados'  => TripStatus::options(),
            'tipos'    => TripType::options(),
            'clientes' => Customer::where('is_active', true)->orderBy('name')->get(),
            'choferes' => Driver::where('is_active', true)
                ->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }
}
