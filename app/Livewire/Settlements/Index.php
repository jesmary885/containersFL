<?php

namespace App\Livewire\Settlements;

use App\Enums\SettlementStatus;
use App\Enums\TripStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\DriverSettlementItem;
use App\Models\Trip;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LIQUIDACIÓN DE CHOFERES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE QUIÉN ES ESTO ──
 *
 * De la TRANSPORTISTA, no de la de contenedores. Quedó aclarado en la
 * reunión del 16 de septiembre: los choferes son de RST y es RST quien les
 * liquida.
 *
 * Por eso la pantalla trabaja siempre sobre la compañía activa: si está
 * elegida la de contenedores, no va a encontrar viajes que liquidar, y eso
 * es correcto.
 *
 * ── CÓMO FUNCIONA ──
 *
 * Se elige un chofer y un período. Salen sus viajes completados que todavía
 * no se le pagaron, con el pago que quedó pactado en cada uno. Se genera la
 * liquidación y queda en borrador.
 *
 * Aprobarla es un segundo acto: ahí se cierra y los viajes quedan marcados
 * como liquidados. Antes de eso se puede tirar a la basura.
 *
 * ── POR QUÉ NO PAGA ──
 *
 * Aprobar no es pagar. La liquidación dice cuánto se le debe; el pago tiene
 * su fecha, su método y su referencia, y ocurre después. Mezclarlos haría
 * imposible responder "¿cuánto le debo a Juan hoy?".
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess, WithPagination;

    protected string $permisoBase = 'settlements';

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    /* =====================================================================
     | EL PANEL DE GENERAR
     * ================================================================== */

    public bool $generando = false;

    public ?int $driver_id = null;
    public ?string $desde = null;
    public ?string $hasta = null;

    /** id del viaje => true. Lo que entra en la liquidación. */
    public array $elegidos = [];

    public function mount(): void
    {
        $this->exigirPermiso('view');

        $this->desde = now()->subWeek()->startOfWeek()->toDateString();
        $this->hasta = now()->subWeek()->endOfWeek()->toDateString();
    }

    public function updatingEstado(): void { $this->resetPage(); }

    public function updated(string $campo): void
    {
        /*
         | Cambió el chofer o el rango → la selección anterior ya no vale.
         | Dejarla pegada liquidaría viajes que ya no están en pantalla.
         */
        if (in_array($campo, ['driver_id', 'desde', 'hasta'], true)) {
            $this->elegidos = [];
        }
    }

    public function abrirGenerar(): void
    {
        $this->exigirPermiso('create');

        $this->elegidos = [];
        $this->resetValidation();

        $this->generando = true;
    }

    public function cerrar(): void
    {
        $this->generando = false;
        $this->resetValidation();
    }

    /* =====================================================================
     | LOS VIAJES QUE SE LE DEBEN
     * ================================================================== */

    public function getViajesProperty()
    {
        if (! $this->driver_id || ! $this->desde || ! $this->hasta) {
            return collect();
        }

        $empresa = app(CompanyContext::class)->get();

        return Trip::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id))
            ->where('driver_id', $this->driver_id)
            ->where('status', TripStatus::Completed)
            ->where('driver_pay', '>', 0)

            /*
             | Todavía sin liquidar. Es lo que impide pagarle dos veces el
             | mismo viaje, que con períodos semanales es fácil: basta con
             | solapar los rangos por un día.
             */
            ->whereNull('driver_settlement_id')
            ->where('driver_payment_status', 'pending')

            ->whereBetween('completed_at', [
                Carbon::parse($this->desde)->startOfDay(),
                Carbon::parse($this->hasta)->endOfDay(),
            ])

            ->with('customer:id,name')
            ->orderBy('completed_at')
            ->get();
    }

    public function getTotalProperty(): float
    {
        return (float) $this->viajes
            ->filter(fn ($v) => ! empty($this->elegidos[$v->id]))
            ->sum('driver_pay');
    }

    public function marcarTodos(): void
    {
        foreach ($this->viajes as $v) {
            $this->elegidos[$v->id] = true;
        }
    }

    /* =====================================================================
     | GENERAR
     * ================================================================== */

    public function generar(): void
    {
        $this->exigirPermiso('create');

        $ids = collect($this->elegidos)->filter()->keys()->all();

        if (empty($ids)) {
            $this->addError('elegidos', 'Marque al menos un viaje.');

            return;
        }

        $empresa = app(CompanyContext::class)->get();

        $liq = DB::transaction(function () use ($ids, $empresa) {

            /*
             | Se releen con candado: entre cargar la pantalla y pulsar el
             | botón, otra persona pudo liquidar estos mismos viajes.
             */
            $viajes = Trip::whereIn('id', $ids)
                ->where('status', TripStatus::Completed)
                ->whereNull('driver_settlement_id')
                ->lockForUpdate()
                ->orderBy('completed_at')
                ->get();

            if ($viajes->isEmpty()) {
                return null;
            }

            $liq = new DriverSettlement();
            $liq->company_id        = $empresa?->id;
            $liq->driver_id         = $this->driver_id;
            $liq->settlement_number = $this->siguienteNumero($empresa?->id);
            $liq->period_start      = $this->desde;
            $liq->period_end        = $this->hasta;
            $liq->status            = SettlementStatus::Draft;
            $liq->created_by        = auth()->id();
            $liq->save();

            foreach ($viajes as $v) {

                $destino = $v->destination_address['city']
                    ?? $v->destination_zip
                    ?? 'destino sin indicar';

                DriverSettlementItem::create([
                    'driver_settlement_id' => $liq->id,
                    'trip_id'              => $v->id,
                    'type'                 => 'trip_pay',
                    'description'          => $v->trip_number.' · '.$destino
                                              .($v->customer ? ' · '.$v->customer->name : ''),
                    'item_date'            => $v->completed_at?->toDateString(),
                    'amount'               => (float) $v->driver_pay,
                ]);

                $v->driver_settlement_id = $liq->id;
                $v->save();
            }

            $liq->recalculateTotals();

            return $liq;
        });

        if (! $liq) {
            $this->addError('elegidos',
                'Esos viajes ya se liquidaron desde otra pantalla. Cierre y vuelva a abrir.');

            $this->elegidos = [];

            return;
        }

        $this->generando = false;
        $this->elegidos  = [];

        session()->flash('exito',
            'Liquidación '.$liq->settlement_number.' creada en borrador por $'
            .number_format((float) $liq->net_amount, 2).'.');
    }

    /* =====================================================================
     | APROBAR
     * ================================================================== */

    public function aprobar(int $id): void
    {
        $this->exigirPermiso('update');

        $liq = DriverSettlement::findOrFail($id);

        if (! $liq->isEditable()) {
            $this->addError('elegidos', 'Esa liquidación ya está cerrada.');

            return;
        }

        $liq->approve(auth()->user());

        session()->flash('exito',
            'Liquidación '.$liq->settlement_number.' aprobada. Los viajes quedaron liquidados.');
    }

    protected function siguienteNumero(?int $companyId): string
    {
        $prefijo = 'SET-'.now()->format('y').'-';

        $ultimo = DriverSettlement::where('company_id', $companyId)
            ->where('settlement_number', 'like', $prefijo.'%')
            ->orderByDesc('settlement_number')
            ->value('settlement_number');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        do {
            $numero = $prefijo.str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
            $siguiente++;
        } while (DriverSettlement::where('company_id', $companyId)
                                 ->where('settlement_number', $numero)->exists());

        return $numero;
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        $liquidaciones = DriverSettlement::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id))
            ->when($this->estado, fn ($q) => $q->where('status', $this->estado))
            ->with(['driver:id,first_name,last_name'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(20);

        /*
         | Lo que se les debe a todos, sin liquidar todavía. Es el número
         | que contesta "¿cuánto tengo que sacar esta semana?".
         */
        $pendienteTotal = (float) Trip::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id))
            ->where('status', TripStatus::Completed)
            ->whereNull('driver_settlement_id')
            ->where('driver_payment_status', 'pending')
            ->sum('driver_pay');

        return view('livewire.settlements.index', [
            'liquidaciones'  => $liquidaciones,
            'pendienteTotal' => $pendienteTotal,
            'estados'        => SettlementStatus::options(),
            'viajes'         => $this->viajes,
            'total'          => $this->total,

            'choferes' => Driver::where('is_active', true)
                ->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }
}
