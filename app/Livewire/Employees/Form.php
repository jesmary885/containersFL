<?php

namespace App\Livewire\Employees;

use App\Enums\CommissionBase;
use App\Enums\CommissionMode;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL TRABAJADOR
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Una sola pantalla. Ocho campos.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'users';

    public ?int $employeeId = null;

    public string $first_name = '';
    public ?string $last_name = null;

    public string $role = 'vendedor';

    public ?string $phone = null;
    public ?string $email = null;

    public ?int $company_id = null;
    public ?string $hired_at = null;

    /**
     * Si este trabajador cobra comisión.
     *
     * Nace encendido: el caso mas frecuente es dar de alta a un vendedor,
     * y llegar con los campos ya visibles ahorra un clic en la mayoria de
     * las altas.
     *
     * Para los demas roles se apaga y los dos campos desaparecen. Un
     * campo que no aplica ensucia el formulario y hace dudar de si hay
     * que llenarlo.
     */
    public bool $cobraComision = true;

    public $default_commission_amount  = null;
    public $default_commission_percent = null;

    /* ---------------------------------------------------------------------
     | EL VENDEDOR CON CONDICIONES ESPECIALES — REUNIÓN 16-09
     |
     | Denisse: la mayoría maneja montos fijos, salvo un vendedor principal
     | que recibe un porcentaje sobre unidades de venta directa.
     |
     | Antes esto no se podía guardar: el formulario prohibía llenar los dos
     | campos. Ahora se pueden llenar los dos y estas dos preguntas resuelven
     | la ambigüedad que motivaba la prohibición.
     * ------------------------------------------------------------------ */

    /** 'fixed' | 'percent' — cuál se propone al facturar. */
    public ?string $default_commission_mode = null;

    /** 'subtotal' | 'containers' — sobre qué se calcula el porcentaje. */
    public string $default_commission_base = 'subtotal';

    public bool $is_active = true;
    public ?string $notes  = null;

    /* =====================================================================
     | SOLO SI MANEJA
     |
     | ── POR QUE SIGUE EXISTIENDO LA TABLA `drivers` ──
     |
     | Porque los viajes y las liquidaciones apuntan ahi. Borrarla
     | dejaria sin dueno cada viaje registrado.
     |
     | Pero el usuario no tiene que saber que son dos tablas. Registra a
     | la persona una vez, dice que es chofer, y el sistema mantiene sola
     | la ficha de chofer con sus papeles.
     |
     | Un chofer deja de ser chofer y su ficha se desactiva, no se borra:
     | los viajes que hizo siguen siendo suyos.
     * ================================================================== */

    public ?string $license_number     = null;
    public ?string $license_expires_at = null;
    public ?string $medical_expires_at = null;
    public $default_pay_percent = null;
    public $default_pay_amount  = null;

    public function mount(?Employee $employee = null)
    {
        if ($employee && $employee->exists) {
            $this->exigirPermiso('update');

            $this->employeeId = $employee->id;
            $this->first_name = $employee->first_name;
            $this->last_name  = $employee->last_name;
            $this->role       = $employee->role;
            $this->phone      = $employee->phone;
            $this->email      = $employee->email;
            $this->company_id = $employee->company_id;
            $this->hired_at   = $employee->hired_at?->toDateString();

            $this->default_commission_amount  = $employee->default_commission_amount;
            $this->default_commission_percent = $employee->default_commission_percent;
            $this->default_commission_mode    = $employee->default_commission_mode?->value;
            $this->default_commission_base    = $employee->default_commission_base?->value ?: 'subtotal';

            $this->cobraComision = $employee->default_commission_amount !== null
                || $employee->default_commission_percent !== null;

            $this->is_active = (bool) $employee->is_active;
            $this->notes     = $employee->notes;

            if ($employee->driver) {
                $this->license_number      = $employee->driver->license_number;
                $this->license_expires_at  = $employee->driver->license_expires_at?->toDateString();
                $this->medical_expires_at  = $employee->driver->medical_expires_at?->toDateString();
                $this->default_pay_percent = $employee->driver->default_pay_percent;
                $this->default_pay_amount  = $employee->driver->default_pay_amount;
            }

            return null;
        }

        $this->exigirPermiso('create');

        return null;
    }

    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],

            'role' => ['required', Rule::in(array_keys(Employee::ROLES))],

            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],

            'company_id' => ['nullable', 'exists:companies,id'],
            'hired_at'   => ['nullable', 'date'],

            'default_commission_amount'  => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'default_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_commission_mode'    => ['nullable', Rule::in(CommissionMode::values())],
            'default_commission_base'    => ['required', Rule::in(CommissionBase::values())],

            'notes' => ['nullable', 'string', 'max:2000'],

            'license_number'     => ['nullable', 'string', 'max:50'],
            'license_expires_at' => ['nullable', 'date'],
            'medical_expires_at' => ['nullable', 'date'],
            'default_pay_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_pay_amount'  => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'first_name' => 'el nombre',
            'role'       => 'el rol',
            'default_commission_amount'  => 'el monto de comisión',
            'default_commission_percent' => 'el porcentaje de comisión',
        ];
    }

    protected function messages(): array
    {
        return [
            'first_name.required' => 'Escriba el nombre.',
        ];
    }

    public function guardar()
    {
        $this->exigirPermiso($this->employeeId ? 'update' : 'create');

        $this->validate();

        /* -----------------------------------------------------------------
         | LAS DOS FORMAS PUEDEN CONVIVIR — CAMBIADO 25-09
         |
         | ── QUÉ HABÍA ANTES ──
         |
         | El formulario PROHIBÍA llenar monto y porcentaje a la vez, con un
         | argumento razonable: con los dos llenos, el sistema no sabría cuál
         | usar y la decisión la tomaría el código.
         |
         | ── POR QUÉ CAMBIÓ ──
         |
         | Porque en la reunión del 16-09 apareció el caso que la regla no
         | contemplaba: un vendedor principal que cobra porcentaje en venta
         | directa de unidades, y monto en lo demás. Con la prohibición
         | puesta, ese vendedor no se podía guardar.
         |
         | ── POR QUÉ YA NO HACE FALTA LA PROHIBICIÓN ──
         |
         | Porque ahora hay a quién preguntarle: `default_commission_mode`
         | dice cuál se propone. Con un solo valor cargado no se pregunta
         | nada, se usa ese.
         |
         | Y sobre todo: lo que decide de verdad es la FACTURA, donde queda
         | congelado lo pactado (RB-058). Esto es una sugerencia para no
         | teclear el caso normal.
         * -------------------------------------------------------------- */

        /* Sin comisión, todo lo de comisión se descarta. */
        if (! $this->cobraComision) {
            $this->default_commission_amount  = null;
            $this->default_commission_percent = null;
            $this->default_commission_mode    = null;
        }

        /*
         | Con los dos valores cargados hace falta saber cuál se propone.
         | Es la única pregunta que reemplaza a la antigua prohibición.
         */
        if (filled($this->default_commission_amount)
            && filled($this->default_commission_percent)
            && blank($this->default_commission_mode)) {

            $this->addError('default_commission_mode',
                'Tiene cargados monto y porcentaje. Indique cuál se propone al facturar; '
                .'el otro sigue disponible y se elige con un clic en cada factura.');

            return null;
        }

        /*
         | Con un solo valor cargado, el modo se deduce. Guardarlo explícito
         | evita que el formulario de la factura tenga que adivinar.
         */
        if (blank($this->default_commission_mode)) {
            $this->default_commission_mode = match (true) {
                filled($this->default_commission_percent) => CommissionMode::Percent->value,
                filled($this->default_commission_amount)  => CommissionMode::Fixed->value,
                default                                   => null,
            };
        }

        $trabajador = $this->employeeId
            ? Employee::findOrFail($this->employeeId)
            : new Employee();

        $trabajador->fill([
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name ?: null,
            'role'       => $this->role,
            'phone'      => $this->phone ?: null,
            'email'      => $this->email ?: null,
            'company_id' => $this->company_id ?: null,
            'hired_at'   => $this->hired_at ?: null,

            'default_commission_amount'  => $this->default_commission_amount !== ''
                ? $this->default_commission_amount : null,
            'default_commission_percent' => $this->default_commission_percent !== ''
                ? $this->default_commission_percent : null,

            'default_commission_mode'    => $this->default_commission_mode ?: null,
            'default_commission_base'    => $this->default_commission_base ?: 'subtotal',

            'is_active' => $this->is_active,
            'notes'     => $this->notes ?: null,
        ])->save();

        $this->sincronizarChofer($trabajador);

        session()->flash('exito', $this->employeeId
            ? $trabajador->name.' actualizado.'
            : $trabajador->name.' registrado.');

        return redirect()->route('sistema.trabajadores.index');
    }

    /**
     * Mantiene al dia la ficha de chofer.
     *
     * Si es chofer, se crea o se actualiza. Si dejo de serlo, la ficha se
     * DESACTIVA, no se borra: los viajes que hizo siguen apuntando ahi, y
     * borrarla los dejaria sin dueno.
     */
    protected function sincronizarChofer(\App\Models\Employee $trabajador): void
    {
        if ($trabajador->role !== 'chofer') {

            if ($trabajador->driver) {
                $trabajador->driver->update(['is_active' => false]);
            }

            return;
        }

        $datos = [
            'company_id' => $trabajador->company_id,
            'first_name' => $trabajador->first_name,
            'last_name'  => $trabajador->last_name ?: $trabajador->first_name,
            'phone'      => $trabajador->phone,
            'email'      => $trabajador->email,
            'hired_at'   => $trabajador->hired_at,

            'license_number'     => $this->license_number ?: null,
            'license_expires_at' => $this->license_expires_at ?: null,
            'medical_expires_at' => $this->medical_expires_at ?: null,

            'default_pay_percent' => $this->default_pay_percent !== ''
                ? $this->default_pay_percent : null,
            'default_pay_amount'  => $this->default_pay_amount !== ''
                ? $this->default_pay_amount : null,

            'is_active' => $trabajador->is_active,
        ];

        if ($trabajador->driver) {
            $trabajador->driver->update($datos);

            return;
        }

        $chofer = \App\Models\Driver::create($datos);

        $trabajador->update(['driver_id' => $chofer->id]);
    }

    public function render()
    {
        return view('livewire.employees.form', [
            'roles'    => Employee::ROLES,
            'empresas' => Company::where('is_active', true)->orderBy('name')->get(),
            'modos'    => CommissionMode::options(),
            'bases'    => CommissionBase::cases(),
        ]);
    }
}
