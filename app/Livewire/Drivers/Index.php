<?php

namespace App\Livewire\Drivers;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Carrier;
use App\Models\Driver;
use App\Support\CompanyContext;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LOS CHOFERES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── LISTA Y FORMULARIO EN LA MISMA PANTALLA ──
 *
 * Son pocos, se dan de alta una vez y después casi no se tocan. Separar
 * la lista del formulario obligaría a navegar a otra página para corregir
 * un teléfono.
 *
 * El formulario se abre en un panel lateral sobre la misma lista.
 *
 * ── LO QUE VIGILA ──
 *
 * Dos fechas que vencen y dejan al chofer fuera de servicio:
 *
 *   Licencia          sin ella no puede manejar
 *   Examen médico     el DOT medical card es obligatorio en Estados Unidos
 *
 * La lista las marca en rojo cuando ya vencieron y en amarillo cuando
 * quedan menos de 30 días. Enterarse el día que vence es tarde.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess, WithPagination;

    protected string $permisoBase = 'drivers';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'activos', except: '1')]
    public string $activos = '1';

    /* =====================================================================
     | EL PANEL DE EDICIÓN
     * ================================================================== */

    public bool $editando = false;
    public ?int $driverId = null;

    public string $first_name = '';
    public string $last_name  = '';
    public ?string $phone = null;
    public ?string $email = null;

    public ?int $carrier_id = null;

    public ?string $license_number     = null;
    public ?string $license_expires_at = null;
    public ?string $medical_expires_at = null;
    public ?string $hired_at           = null;

    public $default_pay_percent = null;
    public $default_pay_amount  = null;

    public bool $is_1099_reportable = true;
    public bool $is_active          = true;

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    public function updatingBuscar(): void  { $this->resetPage(); }
    public function updatingActivos(): void { $this->resetPage(); }

    /* =====================================================================
     | ABRIR Y CERRAR EL PANEL
     * ================================================================== */

    public function nuevo(): void
    {
        $this->exigirPermiso('create');

        $this->reset([
            'driverId', 'first_name', 'last_name', 'phone', 'email', 'carrier_id',
            'license_number', 'license_expires_at', 'medical_expires_at', 'hired_at',
            'default_pay_percent', 'default_pay_amount',
        ]);

        $this->is_1099_reportable = true;
        $this->is_active = true;

        $this->resetValidation();

        $this->editando = true;
    }

    public function editar(int $id): void
    {
        $this->exigirPermiso('update');

        $d = Driver::findOrFail($id);

        $this->driverId   = $d->id;
        $this->first_name = $d->first_name;
        $this->last_name  = $d->last_name;
        $this->phone      = $d->phone;
        $this->email      = $d->email;
        $this->carrier_id = $d->carrier_id;

        $this->license_number     = $d->license_number;
        $this->license_expires_at = $d->license_expires_at?->toDateString();
        $this->medical_expires_at = $d->medical_expires_at?->toDateString();
        $this->hired_at           = $d->hired_at?->toDateString();

        $this->default_pay_percent = $d->default_pay_percent;
        $this->default_pay_amount  = $d->default_pay_amount;

        $this->is_1099_reportable = (bool) $d->is_1099_reportable;
        $this->is_active          = (bool) $d->is_active;

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
        $this->exigirPermiso($this->driverId ? 'update' : 'create');

        $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:150'],
            'carrier_id' => ['nullable', 'exists:carriers,id'],

            'license_number'     => ['nullable', 'string', 'max:50'],
            'license_expires_at' => ['nullable', 'date'],
            'medical_expires_at' => ['nullable', 'date'],
            'hired_at'           => ['nullable', 'date'],

            'default_pay_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_pay_amount'  => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ], [], [
            'first_name' => 'nombre',
            'last_name'  => 'apellido',
        ]);

        /* -----------------------------------------------------------------
         | PORCENTAJE Y MONTO PUEDEN CONVIVIR
         |
         | Igual que con los vendedores: la mayoría de los choferes cobra
         | un porcentaje del viaje —el 30% que sale del Excel— pero un
         | viaje puntual puede pactarse a monto fijo.
         |
         | Lo que manda es lo que quede escrito en el viaje. Esto es solo
         | lo que se propone.
         * -------------------------------------------------------------- */

        $empresa = app(CompanyContext::class)->get();

        $d = $this->driverId ? Driver::findOrFail($this->driverId) : new Driver();

        if (! $this->driverId) {
            $d->company_id = $empresa?->id;
        }

        $d->fill([
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'phone'      => $this->phone ?: null,
            'email'      => $this->email ?: null,
            'carrier_id' => $this->carrier_id ?: null,

            'license_number'     => $this->license_number ?: null,
            'license_expires_at' => $this->license_expires_at ?: null,
            'medical_expires_at' => $this->medical_expires_at ?: null,
            'hired_at'           => $this->hired_at ?: null,

            'default_pay_percent' => $this->default_pay_percent !== '' ? $this->default_pay_percent : null,
            'default_pay_amount'  => $this->default_pay_amount !== '' ? $this->default_pay_amount : null,

            'is_1099_reportable' => $this->is_1099_reportable,
            'is_active'          => $this->is_active,
        ])->save();

        $this->editando = false;

        session()->flash('exito', 'Chofer '.trim($d->first_name.' '.$d->last_name).' guardado.');
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        $choferes = Driver::query()
            ->when($empresa, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('company_id', $empresa->id)
                ->orWhereNull('company_id')))

            ->when($this->buscar, function ($q) {
                $t = '%'.$this->buscar.'%';
                $q->where(fn ($qq) => $qq
                    ->where('first_name', 'like', $t)
                    ->orWhere('last_name', 'like', $t)
                    ->orWhere('phone', 'like', $t)
                    ->orWhere('license_number', 'like', $t));
            })

            ->when($this->activos === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->activos === '0', fn ($q) => $q->where('is_active', false))

            ->with('carrier:id,name')
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate(25);

        return view('livewire.drivers.index', [
            'choferes'       => $choferes,
            'transportistas' => Carrier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
