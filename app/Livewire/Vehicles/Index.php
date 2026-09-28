<?php

namespace App\Livewire\Vehicles;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Carrier;
use App\Models\Vehicle;
use App\Support\CompanyContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LOS CAMIONES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Misma forma que Choferes: lista y panel lateral. Son pocos y se editan
 * sobre la marcha.
 *
 * ── LO QUE VIGILA ──
 *
 * Registro y seguro. Un camión con el seguro vencido no sale de la yarda,
 * y enterarse el día que vence es tarde: la lista lo marca 30 días antes.
 *
 * ── EL VIN ES ÚNICO EN TODA LA BASE ──
 *
 * Y lo es a propósito: dos filas con el mismo VIN son el mismo camión
 * cargado dos veces, y a partir de ahí los gastos se reparten entre las
 * dos y ninguna dice la verdad.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess, WithPagination;

    protected string $permisoBase = 'vehicles';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'activos', except: '1')]
    public string $activos = '1';

    /* =====================================================================
     | EL PANEL
     * ================================================================== */

    public bool $editando = false;
    public ?int $vehicleId = null;

    public ?string $plate_number = null;
    public ?string $vin          = null;
    public ?string $type         = null;
    public ?string $make         = null;
    public ?string $model        = null;
    public $year                 = null;

    public ?int $carrier_id = null;

    public ?string $registration_expires_at = null;
    public ?string $insurance_expires_at    = null;

    public bool $is_active = true;

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    public function updatingBuscar(): void  { $this->resetPage(); }
    public function updatingActivos(): void { $this->resetPage(); }

    public function nuevo(): void
    {
        $this->exigirPermiso('create');

        $this->reset([
            'vehicleId', 'plate_number', 'vin', 'type', 'make', 'model', 'year',
            'carrier_id', 'registration_expires_at', 'insurance_expires_at',
        ]);

        $this->is_active = true;
        $this->resetValidation();

        $this->editando = true;
    }

    public function editar(int $id): void
    {
        $this->exigirPermiso('update');

        $v = Vehicle::findOrFail($id);

        $this->vehicleId   = $v->id;
        $this->plate_number = $v->plate_number;
        $this->vin          = $v->vin;
        $this->type         = $v->type;
        $this->make         = $v->make;
        $this->model        = $v->model;
        $this->year         = $v->year;
        $this->carrier_id   = $v->carrier_id;

        $this->registration_expires_at = $v->registration_expires_at?->toDateString();
        $this->insurance_expires_at    = $v->insurance_expires_at?->toDateString();

        $this->is_active = (bool) $v->is_active;

        $this->resetValidation();

        $this->editando = true;
    }

    public function cerrar(): void
    {
        $this->editando = false;
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $this->exigirPermiso($this->vehicleId ? 'update' : 'create');

        $this->validate([
            'plate_number' => ['nullable', 'string', 'max:15'],

            /*
             | El VIN es único en toda la base. La regla ignora la propia
             | fila al editar: sin eso, guardar un camión sin tocarle nada
             | daría "ese VIN ya existe", y existe porque es él mismo.
             */
            'vin' => ['nullable', 'string', 'size:17',
                      \Illuminate\Validation\Rule::unique('vehicles', 'vin')->ignore($this->vehicleId)],

            'type'  => ['nullable', 'string', 'max:30'],
            'make'  => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'year'  => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 2)],

            'carrier_id' => ['nullable', 'exists:carriers,id'],

            'registration_expires_at' => ['nullable', 'date'],
            'insurance_expires_at'    => ['nullable', 'date'],
        ], [
            'vin.size'   => 'El VIN tiene exactamente 17 caracteres.',
            'vin.unique' => 'Ya hay un camión cargado con ese VIN.',
        ], [
            'plate_number' => 'placa',
            'year'         => 'año',
        ]);

        /* -----------------------------------------------------------------
         | ALGO TIENE QUE IDENTIFICARLO
         |
         | Sin placa ni VIN, en la lista de un viaje saldría "#14" y nadie
         | sabría qué camión es.
         * -------------------------------------------------------------- */
        if (blank($this->plate_number) && blank($this->vin)) {
            $this->addError('plate_number', 'Ponga al menos la placa o el VIN, para poder reconocerlo.');

            return;
        }

        $empresa = app(CompanyContext::class)->get();

        $v = $this->vehicleId ? Vehicle::findOrFail($this->vehicleId) : new Vehicle();

        if (! $this->vehicleId) {
            $v->company_id = $empresa?->id;
        }

        $v->fill([
            'plate_number' => $this->plate_number ?: null,
            'vin'          => $this->vin ? strtoupper($this->vin) : null,
            'type'         => $this->type ?: null,
            'make'         => $this->make ?: null,
            'model'        => $this->model ?: null,
            'year'         => $this->year !== '' ? $this->year : null,
            'carrier_id'   => $this->carrier_id ?: null,

            'registration_expires_at' => $this->registration_expires_at ?: null,
            'insurance_expires_at'    => $this->insurance_expires_at ?: null,

            'is_active' => $this->is_active,
        ])->save();

        $this->editando = false;

        session()->flash('exito', 'Camión '.($v->plate_number ?: $v->vin).' guardado.');
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        $camiones = Vehicle::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id))

            ->when($this->buscar, function ($q) {
                $t = '%'.$this->buscar.'%';
                $q->where(fn ($qq) => $qq
                    ->where('plate_number', 'like', $t)
                    ->orWhere('vin', 'like', $t)
                    ->orWhere('make', 'like', $t)
                    ->orWhere('model', 'like', $t));
            })

            ->when($this->activos === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->activos === '0', fn ($q) => $q->where('is_active', false))

            ->with('carrier:id,name')
            ->orderBy('plate_number')
            ->paginate(25);

        return view('livewire.vehicles.index', [
            'camiones'       => $camiones,
            'transportistas' => Carrier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
