<?php

namespace App\Livewire\Suppliers;

use App\Enums\SupplierType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Supplier;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL PROVEEDOR
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Una sola pantalla. No lleva asistente por pasos y es a propósito: son
 * ocho campos. Partir en tres pasos un formulario que cabe en una
 * pantalla es dos clics de más a cambio de nada.
 *
 * El criterio que venimos usando: el asistente entra cuando el
 * formulario no cabe de un vistazo —cliente, presupuesto, factura—. Aquí
 * cabe.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'suppliers';

    public ?int $supplierId = null;

    public string $numero = '';

    public string $name = '';
    public string $type = 'container_supplier';

    public ?string $contact_name = null;
    public ?string $phone        = null;
    public ?string $email        = null;

    /** La dirección va como arreglo porque en la base es una columna JSON. */
    public array $address = [
        'line1' => '', 'line2' => '', 'city' => '', 'state' => 'FL', 'zip' => '',
    ];

    public bool $is_active          = true;

    public ?string $notes = null;

    public function mount(?Supplier $supplier = null)
    {
        if ($supplier && $supplier->exists) {
            $this->exigirPermiso('update');

            $this->supplierId   = $supplier->id;
            $this->numero       = $supplier->supplier_number;
            $this->name         = $supplier->name;
            $this->type         = $supplier->type?->value ?? 'container_supplier';
            $this->contact_name = $supplier->contact_name;
            $this->phone        = $supplier->phone;
            $this->email        = $supplier->email;

            // El array_merge deja todas las claves aunque en la base esté null.
            $this->address = array_merge($this->address, $supplier->address ?: []);

            $this->is_active          = (bool) $supplier->is_active;
            $this->notes              = $supplier->notes;

            return null;
        }

        $this->exigirPermiso('create');

        return null;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(SupplierType::values())],

            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'email'        => ['nullable', 'email', 'max:150'],

            'address.line1' => ['nullable', 'string', 'max:150'],
            'address.line2' => ['nullable', 'string', 'max:150'],
            'address.city'  => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'size:2'],
            'address.zip'   => ['nullable', 'string', 'max:10'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name'         => 'el nombre',
            'type'         => 'el tipo',
            'contact_name' => 'la persona de contacto',
            'address.state' => 'el estado',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'    => 'Escriba el nombre del proveedor.',
            'address.state.size' => 'El estado va con dos letras: FL, GA, NY.',
        ];
    }

    public function guardar()
    {
        $this->exigirPermiso($this->supplierId ? 'update' : 'create');

        $this->validate();

        $proveedor = $this->supplierId
            ? Supplier::findOrFail($this->supplierId)
            : new Supplier();

        /* -----------------------------------------------------------------
         | EL NÚMERO
         |
         | Se genera aquí solo si viene vacío, y con un candado: si ya
         | existe uno con ese número —porque dos personas dieron de alta a
         | la vez— se prueba el siguiente.
         |
         | No es la forma más elegante de generar una secuencia, pero sí la
         | más honesta: la alternativa sería un contador en otra tabla que
         | hay que mantener sincronizado y que se desincroniza el día que
         | alguien inserta un proveedor desde fuera.
         * -------------------------------------------------------------- */
        if (! $proveedor->exists && blank($proveedor->supplier_number)) {
            $proveedor->supplier_number = $this->siguienteNumero();
        }

        $proveedor->fill([
            'name'         => $this->name,
            'type'         => $this->type,
            'contact_name' => $this->contact_name ?: null,
            'phone'        => $this->phone ?: null,
            'email'        => $this->email ?: null,

            /*
             | Si la dirección quedó entera en blanco se guarda null y no
             | un arreglo de cinco cadenas vacías. La diferencia importa:
             | `filled($proveedor->address)` distingue "no tiene dirección"
             | de "tiene una dirección vacía".
             */
            'address' => collect($this->address)->filter()->isEmpty()
                ? null
                : array_map(fn ($v) => $v ?: null, $this->address),

            'is_active'          => $this->is_active,
            'notes'              => $this->notes ?: null,
        ])->save();

        session()->flash('exito', $this->supplierId
            ? $proveedor->name.' actualizado.'
            : $proveedor->name.' registrado con el número '.$proveedor->supplier_number.'.');

        return redirect()->route('compras.proveedores.index');
    }

    protected function siguienteNumero(): string
    {
        $ultimo = Supplier::withTrashed()
            ->where('supplier_number', 'like', 'SUP-%')
            ->orderByDesc('supplier_number')
            ->value('supplier_number');

        $siguiente = $ultimo
            ? ((int) substr($ultimo, 4)) + 1
            : 1;

        // Si por lo que sea ya existe, se va al siguiente hasta encontrar hueco.
        do {
            $numero = 'SUP-'.str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
            $siguiente++;
        } while (Supplier::withTrashed()->where('supplier_number', $numero)->exists());

        return $numero;
    }

    public function render()
    {
        return view('livewire.suppliers.form', [
            'tipos' => SupplierType::options(),
        ]);
    }
}
