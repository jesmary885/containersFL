<?php

namespace App\Livewire\Depots;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Depot;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL DEPÓSITO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Los datos que se usan después, cada vez que se compra ahí:
 *
 *   · a dónde va el camión y a quién se llama
 *   · cuánto cuesta traer una unidad a la yarda
 *   · cuántos días dan antes de empezar a cobrar almacenaje
 *
 * Los tres últimos no son adorno: se copian solos a cada compra. Es la
 * diferencia entre que el costo del pickup sea un dato y que sea lo que
 * alguien se acuerde.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'depots';

    public ?int $depotId = null;

    public string $name = '';
    public ?string $code = null;
    public ?int $supplier_id = null;

    public ?string $city  = null;
    public ?string $state = 'FL';
    public ?string $zip   = null;
    public array $address = ['line1' => '', 'line2' => ''];

    public ?string $contact_name = null;
    public ?string $phone = null;
    public ?string $email = null;

    public ?string $default_pickup_fee  = null;
    public ?string $daily_late_fee      = null;
    public ?int    $default_pickup_days = 14;
    public ?string $default_miles       = null;

    public bool $is_active = true;
    public ?string $notes  = null;

    public function mount(?Depot $depot = null)
    {
        if ($depot && $depot->exists) {
            $this->exigirPermiso('update');

            $this->depotId     = $depot->id;
            $this->name        = $depot->name;
            $this->code        = $depot->code;
            $this->supplier_id = $depot->supplier_id;
            $this->city        = $depot->city;
            $this->state       = $depot->state ?: 'FL';
            $this->zip         = $depot->zip;
            $this->address     = array_merge($this->address, $depot->address ?: []);

            $this->contact_name = $depot->contact_name;
            $this->phone        = $depot->phone;
            $this->email        = $depot->email;

            $this->default_pickup_fee  = $depot->default_pickup_fee;
            $this->daily_late_fee      = $depot->daily_late_fee;
            $this->default_pickup_days = $depot->default_pickup_days ?? 14;
            $this->default_miles       = $depot->default_miles;

            $this->is_active = (bool) $depot->is_active;
            $this->notes     = $depot->notes;

            return null;
        }

        $this->exigirPermiso('create');

        return null;
    }

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:200'],
            'code'        => ['nullable', 'string', 'max:20'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],

            'city'  => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip'   => ['nullable', 'string', 'max:10'],

            'address.line1' => ['nullable', 'string', 'max:150'],
            'address.line2' => ['nullable', 'string', 'max:150'],

            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'email'        => ['nullable', 'email', 'max:150'],

            'default_pickup_fee'  => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'daily_late_fee'      => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'default_pickup_days' => ['nullable', 'integer', 'min:0', 'max:120'],
            'default_miles'       => ['nullable', 'numeric', 'min:0', 'max:9999'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name'                => 'el nombre',
            'default_pickup_fee'  => 'el costo del pickup',
            'daily_late_fee'      => 'el cargo por día',
            'default_pickup_days' => 'los días libres',
        ];
    }

    public function guardar()
    {
        $this->exigirPermiso($this->depotId ? 'update' : 'create');

        $this->validate();

        $deposito = $this->depotId ? Depot::findOrFail($this->depotId) : new Depot();

        $deposito->fill([
            'name'        => $this->name,
            'code'        => $this->code ?: null,
            'supplier_id' => $this->supplier_id ?: null,

            'city'  => $this->city ?: null,
            'state' => $this->state ? strtoupper($this->state) : null,
            'zip'   => $this->zip ?: null,

            'address' => collect($this->address)->filter()->isEmpty()
                ? null
                : array_map(fn ($v) => $v ?: null, $this->address),

            'contact_name' => $this->contact_name ?: null,
            'phone'        => $this->phone ?: null,
            'email'        => $this->email ?: null,

            /*
             | Estos cuatro van a null cuando están vacíos, no a cero.
             |
             | Un cero es una afirmación: "traer de aquí no cuesta nada".
             | null dice "todavía no lo sabemos", y es lo que hace que el
             | contador del listado los cuente para que alguien lo
             | averigüe.
             */
            'default_pickup_fee'  => $this->default_pickup_fee !== '' ? $this->default_pickup_fee : null,
            'daily_late_fee'      => $this->daily_late_fee !== ''     ? $this->daily_late_fee     : null,
            'default_pickup_days' => $this->default_pickup_days ?: null,
            'default_miles'       => $this->default_miles !== ''      ? $this->default_miles      : null,

            'is_active' => $this->is_active,
            'notes'     => $this->notes ?: null,
        ])->save();

        session()->flash('exito', $this->depotId
            ? $deposito->name.' actualizado.'
            : $deposito->name.' registrado.');

        return redirect()->route('compras.depositos.index');
    }

    public function render()
    {
        return view('livewire.depots.form', [
            'proveedores' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
