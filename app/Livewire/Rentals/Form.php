<?php

namespace App\Livewire\Rentals;

use App\Enums\BillingCycle;
use App\Enums\RentalStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Depot;
use App\Models\Rental;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UN CONTRATO DE RENTA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── LAS DOS FORMAS, EN UN SOLO FORMULARIO ──
 *
 * La pantalla cambia según el ciclo que se elija, porque lo que hay que
 * preguntar es distinto:
 *
 *   MENSUAL   tarifa por mes, día de anclaje, y qué contenedores nuestros
 *             se lleva el cliente.
 *
 *   DE YARDA  tarifa por día, los cargos de entrada y salida, y cuántos
 *             días ya pagó. El contenedor es del cliente, así que no se
 *             elige del inventario.
 *
 * Se resolvió con un solo formulario y no con dos porque el 80% de los
 * campos son los mismos —cliente, fechas, dirección, mora— y dos pantallas
 * obligarían a mantener ese 80% dos veces.
 *
 * ── EL DÍA DE ANCLAJE ──
 *
 * El detalle que más errores causa. El ciclo NO va del 1 al 30: va del día
 * de la entrega al mismo día del mes siguiente. Entregado el 17 de marzo,
 * los períodos son del 17 al 16.
 *
 * El formulario lo deduce de la fecha de inicio y lo deja editable, porque
 * a veces se pacta otro día.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'rentals';

    public ?int $rentalId = null;
    public ?string $numero = null;

    /* =====================================================================
     | QUIÉN Y QUÉ TIPO
     * ================================================================== */

    public ?int $customer_id = null;

    public string $billing_cycle = 'monthly';
    public string $status        = 'draft';

    /* =====================================================================
     | CUÁNDO
     * ================================================================== */

    public ?string $start_date = null;
    public ?string $end_date   = null;

    public ?int $billing_anchor_day = null;

    /* =====================================================================
     | LOS NÚMEROS
     * ================================================================== */

    public $monthly_rate = null;
    public $daily_rate   = null;
    public $tax_rate     = 0;

    /** Días ya pagados. Solo en renta de yarda. */
    public int $paid_days = 0;

    public $entry_fee  = 0;
    public $exit_fee   = 0;
    public $paint_fee  = 0;
    public $repair_fee = 0;

    /* =====================================================================
     | ENTREGA Y RECOGIDA
     * ================================================================== */

    public $miles           = null;
    public $delivery_amount = 0;
    public ?int $depot_id   = null;
    public $pickup_fee      = 0;

    /* =====================================================================
     | MORA Y AUTOMATISMOS
     * ================================================================== */

    public int $grace_days           = 5;
    public $late_fee_amount          = 100;
    public bool $auto_apply_late_fee = true;
    public bool $auto_invoice        = true;

    public ?string $notes = null;

    /* =====================================================================
     | QUÉ CONTENEDORES SE LLEVA (solo mensual)
     |
     | Un contrato puede llevar varias unidades a distinta tarifa: dos de
     | 20 pies a $150 y una de 40 a $220. Por eso es una lista y no un
     | campo suelto.
     * ================================================================== */

    public array $unidades = [];

    /* =====================================================================
     | CARGA
     * ================================================================== */

    public function mount(?Rental $rental = null)
    {
        if ($rental && $rental->exists) {
            $this->exigirPermiso('update');
            $this->cargarDesde($rental);

            return null;
        }

        $this->exigirPermiso('create');

        $this->start_date = now()->toDateString();
        $this->billing_anchor_day = (int) now()->format('j');

        return null;
    }

    protected function cargarDesde(Rental $r): void
    {
        $this->rentalId = $r->id;
        $this->numero   = $r->contract_number;

        $this->customer_id   = $r->customer_id;
        $this->billing_cycle = $r->billing_cycle?->value ?? 'monthly';
        $this->status        = $r->status?->value ?? 'draft';

        $this->start_date = $r->start_date?->toDateString();
        $this->end_date   = $r->end_date?->toDateString();

        $this->billing_anchor_day = $r->billing_anchor_day;

        $this->monthly_rate = $r->monthly_rate;
        $this->daily_rate   = $r->daily_rate;
        $this->tax_rate     = $r->tax_rate;
        $this->paid_days    = (int) $r->paid_days;

        $this->entry_fee  = $r->entry_fee;
        $this->exit_fee   = $r->exit_fee;
        $this->paint_fee  = $r->paint_fee;
        $this->repair_fee = $r->repair_fee;

        $this->miles           = $r->miles;
        $this->delivery_amount = $r->delivery_amount;
        $this->depot_id        = $r->depot_id;
        $this->pickup_fee      = $r->pickup_fee;

        $this->grace_days          = (int) $r->grace_days;
        $this->late_fee_amount     = $r->late_fee_amount;
        $this->auto_apply_late_fee = (bool) $r->auto_apply_late_fee;
        $this->auto_invoice        = (bool) $r->auto_invoice;

        $this->notes = $r->notes;

        $this->unidades = $r->containers()
            ->get()
            ->map(fn ($c) => [
                'container_id' => $c->id,
                'monthly_rate' => $c->pivot->monthly_rate,
                'from_date'    => $c->pivot->from_date,
                'to_date'      => $c->pivot->to_date,
            ])
            ->all();
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    public function updated(string $campo): void
    {
        /*
         | Cambió la fecha de inicio → se propone el día de anclaje.
         |
         | Solo se propone si no lo tocaron: si alguien puso el 1 a mano
         | porque así se pactó, cambiar la fecha no debe borrarlo.
         */
        if ($campo === 'start_date' && $this->start_date) {
            $this->billing_anchor_day = (int) Carbon::parse($this->start_date)->format('j');
        }

        /*
         | La tarifa del contrato se propone en cada unidad nueva. Así, un
         | contrato de tres contenedores iguales se llena con tres clics.
         */
        if ($campo === 'monthly_rate') {
            foreach ($this->unidades as $i => $u) {
                if (blank($u['monthly_rate'])) {
                    $this->unidades[$i]['monthly_rate'] = $this->monthly_rate;
                }
            }
        }
    }

    public function agregarUnidad(): void
    {
        $this->unidades[] = [
            'container_id' => null,
            'monthly_rate' => $this->monthly_rate,
            'from_date'    => $this->start_date,
            'to_date'      => null,
        ];
    }

    public function quitarUnidad(int $i): void
    {
        unset($this->unidades[$i]);

        $this->unidades = array_values($this->unidades);
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    protected function rules(): array
    {
        return [
            'customer_id'   => ['required', 'exists:customers,id'],
            'billing_cycle' => ['required', Rule::in(BillingCycle::values())],
            'status'        => ['required', Rule::in(RentalStatus::values())],

            'start_date' => ['required', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],

            'billing_anchor_day' => ['required', 'integer', 'min:1', 'max:31'],

            'monthly_rate' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'daily_rate'   => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'tax_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid_days'    => ['nullable', 'integer', 'min:0', 'max:99999'],

            'entry_fee'  => ['nullable', 'numeric', 'min:0'],
            'exit_fee'   => ['nullable', 'numeric', 'min:0'],
            'paint_fee'  => ['nullable', 'numeric', 'min:0'],
            'repair_fee' => ['nullable', 'numeric', 'min:0'],

            'miles'           => ['nullable', 'numeric', 'min:0'],
            'delivery_amount' => ['nullable', 'numeric', 'min:0'],
            'depot_id'        => ['nullable', 'exists:depots,id'],
            'pickup_fee'      => ['nullable', 'numeric', 'min:0'],

            'grace_days'      => ['required', 'integer', 'min:0', 'max:60'],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string', 'max:2000'],

            'unidades'                => ['array'],
            'unidades.*.container_id' => ['required', 'exists:containers,id'],
            'unidades.*.monthly_rate' => ['required', 'numeric', 'min:0'],
            'unidades.*.from_date'    => ['required', 'date'],
            'unidades.*.to_date'      => ['nullable', 'date'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'customer_id'             => 'cliente',
            'billing_anchor_day'      => 'día de cobro',
            'monthly_rate'            => 'tarifa mensual',
            'daily_rate'              => 'tarifa diaria',
            'unidades.*.container_id' => 'contenedor',
            'unidades.*.monthly_rate' => 'tarifa del contenedor',
        ];
    }

    public function guardar()
    {
        $this->exigirPermiso($this->rentalId ? 'update' : 'create');

        $this->validate();

        $esDiaria = $this->billing_cycle === 'daily';

        /* -----------------------------------------------------------------
         | CADA CICLO EXIGE SU TARIFA
         |
         | Una renta mensual sin tarifa mensual no se puede facturar, y una
         | de yarda sin tarifa diaria tampoco. Dejarlo pasar crearía un
         | contrato que parece bien y no cobra nada.
         * -------------------------------------------------------------- */
        if ($esDiaria && blank($this->daily_rate)) {
            $this->addError('daily_rate', 'Una renta de yarda se cobra por día. Ponga la tarifa diaria.');

            return null;
        }

        if (! $esDiaria && blank($this->monthly_rate)) {
            $this->addError('monthly_rate', 'Una renta mensual necesita su tarifa por mes.');

            return null;
        }

        /* -----------------------------------------------------------------
         | NO SE PUEDE HABER PAGADO MÁS DÍAS DE LOS TRANSCURRIDOS
         |
         | Si se acepta, la deuda queda en negativo y el reporte de yarda
         | deja de cuadrar. Casi siempre es un error de dedo.
         * -------------------------------------------------------------- */
        if ($esDiaria && $this->paid_days > 0 && $this->start_date) {

            $transcurridos = Carbon::parse($this->start_date)->diffInDays(
                $this->end_date ? Carbon::parse($this->end_date) : Carbon::today(),
            ) + 1;

            if ($this->paid_days > $transcurridos) {
                $this->addError('paid_days',
                    'Ese contrato lleva '.$transcurridos.' días. No puede tener '
                    .$this->paid_days.' días pagados.');

                return null;
            }
        }

        /* -----------------------------------------------------------------
         | UN CONTENEDOR NO SE RENTA DOS VECES EN EL MISMO CONTRATO
         * -------------------------------------------------------------- */
        $ids = collect($this->unidades)->pluck('container_id')->filter();

        if ($ids->count() !== $ids->unique()->count()) {
            $this->addError('unidades', 'Hay un contenedor repetido en la lista.');

            return null;
        }

        $empresa = app(CompanyContext::class)->get();

        $renta = DB::transaction(function () use ($empresa, $esDiaria) {

            $r = $this->rentalId
                ? Rental::findOrFail($this->rentalId)
                : new Rental();

            if (! $this->rentalId) {
                $r->company_id      = $empresa?->id;
                $r->contract_number = $this->siguienteNumero($empresa?->id);
                $r->created_by      = auth()->id();
            }

            $r->fill([
                'customer_id'   => $this->customer_id,
                'billing_cycle' => $this->billing_cycle,
                'status'        => $this->status,

                'start_date' => $this->start_date,
                'end_date'   => $this->end_date ?: null,

                'billing_anchor_day' => $this->billing_anchor_day,

                /*
                 | La tarifa que no aplica se guarda en null y no en cero.
                 | Cero significa "gratis" y null significa "no aplica":
                 | son cosas distintas y el reporte las lee distinto.
                 */
                'monthly_rate' => $esDiaria ? ($this->monthly_rate ?: 0) : $this->monthly_rate,
                'daily_rate'   => $esDiaria ? $this->daily_rate : null,

                'tax_rate'  => (float) ($this->tax_rate ?: 0),
                'paid_days' => $esDiaria ? (int) $this->paid_days : 0,

                'entry_fee'  => (float) ($this->entry_fee ?: 0),
                'exit_fee'   => (float) ($this->exit_fee ?: 0),
                'paint_fee'  => (float) ($this->paint_fee ?: 0),
                'repair_fee' => (float) ($this->repair_fee ?: 0),

                'miles'           => $this->miles !== '' ? $this->miles : null,
                'delivery_amount' => (float) ($this->delivery_amount ?: 0),
                'depot_id'        => $this->depot_id ?: null,
                'pickup_fee'      => (float) ($this->pickup_fee ?: 0),

                'grace_days'          => (int) $this->grace_days,
                'late_fee_amount'     => (float) ($this->late_fee_amount ?: 0),
                'auto_apply_late_fee' => $this->auto_apply_late_fee,
                'auto_invoice'        => $this->auto_invoice,

                'notes' => $this->notes ?: null,
            ])->save();

            /*
             | Los contenedores se vuelven a escribir enteros.
             |
             | Es una lista corta —dos o tres unidades— y compararlas una
             | por una para decidir cuál cambió costaría más código del que
             | ahorra. sync() con el arreglo completo deja la tabla como se
             | ve en pantalla, que es lo que espera quien guardó.
             */
            if (! $esDiaria) {
                $r->containers()->sync(
                    collect($this->unidades)
                        ->filter(fn ($u) => filled($u['container_id']))
                        ->mapWithKeys(fn ($u) => [
                            $u['container_id'] => [
                                'monthly_rate' => (float) $u['monthly_rate'],
                                'from_date'    => $u['from_date'],
                                'to_date'      => $u['to_date'] ?: null,
                            ],
                        ])
                        ->all(),
                );
            }

            return $r;
        });

        session()->flash('exito', 'Contrato '.$renta->contract_number.' guardado.');

        return redirect()->route('operaciones.rentas.show', $renta);
    }

    protected function siguienteNumero(?int $companyId): string
    {
        $prefijo = 'RNT-'.now()->format('y').'-';

        $ultimo = Rental::where('company_id', $companyId)
            ->where('contract_number', 'like', $prefijo.'%')
            ->orderByDesc('contract_number')
            ->value('contract_number');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        do {
            $numero = $prefijo.str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
            $siguiente++;
        } while (Rental::where('company_id', $companyId)
                       ->where('contract_number', $numero)->exists());

        return $numero;
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        return view('livewire.rentals.form', [
            'clientes'  => Customer::where('is_active', true)->orderBy('name')->get(),
            'depositos' => Depot::where('is_active', true)->orderBy('name')->get(),
            'ciclos'    => BillingCycle::options(),
            'estados'   => RentalStatus::options(),

            'contenedores' => Container::query()
                ->when($empresa, fn ($q) => $q->where('owner_company_id', $empresa->id))
                ->orderByDesc('id')
                ->limit(300)
                ->get(),
        ]);
    }
}
