<?php

namespace App\Livewire\Purchases;

use App\Enums\PurchaseStatus;
use App\Enums\PurchaseType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\ContainerCondition;
use App\Models\ContainerGrade;
use App\Models\ContainerSize;
use App\Models\ContainerType;
use App\Models\Depot;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * REGISTRAR UNA COMPRA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── AQUÍ NO SE REGISTRAN CONTENEDORES ──
 *
 * Y es lo más importante de entender de esta pantalla.
 *
 * Una compra dice "pagué siete unidades de 20ft Cargo Worthy a $1,800
 * cada una". No dice cuáles: todavía no se sabe, están en el patio del
 * proveedor y nadie ha leído sus números.
 *
 * Los contenedores se registran uno por uno cuando LLEGAN, desde la
 * ficha de la compra. Ahí sí se sabe el número que viene pintado.
 *
 * Hacerlo al revés —dar de alta siete contenedores al comprar— es
 * exactamente lo que hace el Excel, y es la razón por la que dice 416
 * unidades en stock que físicamente no están.
 *
 * ── UNA COMPRA O UN RELEASE ──
 *
 * SIMPLE    se paga y se retira de una vez. Sin plazo.
 * RELEASE   se paga un lote y se retira por partes, con fecha límite.
 *           Pasada esa fecha el depósito cobra almacenaje por día.
 *
 * El tipo decide si se piden el depósito y el plazo. Un release sin
 * depósito no significa nada: el papel autoriza a retirar DE algún
 * sitio.
 *
 * ── EL DEPÓSITO TRAE SUS NÚMEROS ──
 *
 * Al elegirlo se copian su costo de pickup, su cargo por día y sus días
 * libres, y con los días libres se calcula la fecha límite. Son datos
 * que ya están fichados y que nadie recuerda de memoria.
 *
 * Todos quedan editables: lo que se pactó en ESTA compra manda sobre lo
 * habitual del depósito.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'purchases';

    public ?int $purchaseId = null;

    public string $numero = '';

    /* ── CABECERA ── */
    public ?int $supplier_id = null;
    public string $type      = 'single';
    public ?string $reference = null;
    public string $purchase_date = '';

    /* ── DÓNDE ESTÁN Y HASTA CUÁNDO ── */
    public ?int $depot_id = null;
    public ?string $pickup_fee         = '0';
    public ?string $daily_late_fee     = null;
    public ?string $pickup_deadline_at = null;

    /* ── DINERO ── */
    public ?string $tax_amount = '0';
    public ?string $notes      = null;

    /* ── LO QUE SE COMPRÓ ── */
    public array $lineas = [];

    public function mount(?Purchase $purchase = null)
    {
        if ($purchase && $purchase->exists) {
            $this->exigirPermiso('update');
            $this->cargarDesde($purchase);

            return null;
        }

        $this->exigirPermiso('create');

        $this->purchase_date = now()->toDateString();
        $this->lineas        = [$this->lineaVacia()];

        return null;
    }

    protected function cargarDesde(Purchase $p): void
    {
        $p->load('items');

        $this->purchaseId = $p->id;
        $this->numero     = $p->purchase_number;

        $this->supplier_id   = $p->supplier_id;
        $this->type          = $p->type?->value ?? 'single';
        $this->reference     = $p->reference;
        $this->purchase_date = $p->purchase_date?->toDateString() ?? now()->toDateString();

        $this->depot_id           = $p->depot_id;
        $this->pickup_fee         = $p->pickup_fee;
        $this->daily_late_fee     = $p->daily_late_fee;
        $this->pickup_deadline_at = $p->pickup_deadline_at?->toDateString();

        $this->tax_amount = $p->tax_amount;
        $this->notes      = $p->notes;

        $this->lineas = $p->items->map(fn ($i) => [
            'id'                     => $i->id,
            'container_type_id'      => $i->container_type_id,
            'container_size_id'      => $i->container_size_id,
            'container_condition_id' => $i->container_condition_id,
            'container_grade_id'     => $i->container_grade_id,
            'quantity'               => $i->quantity,
            'received_quantity'      => $i->received_quantity,
            'unit_cost'              => $i->unit_cost,
            'notes'                  => $i->notes,
        ])->all();

        if (empty($this->lineas)) {
            $this->lineas = [$this->lineaVacia()];
        }
    }

    protected function lineaVacia(): array
    {
        return [
            'id'                     => null,
            'container_type_id'      => null,
            'container_size_id'      => null,
            'container_condition_id' => null,
            'container_grade_id'     => null,
            'quantity'               => 1,
            'received_quantity'      => 0,
            'unit_cost'              => null,
            'notes'                  => null,
        ];
    }

    public function agregarLinea(): void
    {
        $this->lineas[] = $this->lineaVacia();
    }

    public function quitarLinea(int $i): void
    {
        /*
         | Un renglón con unidades ya recibidas no se borra: esas unidades
         | están registradas en el inventario y apuntan a esta compra.
         | Borrarlo dejaría contenedores sin origen.
         */
        if ((int) ($this->lineas[$i]['received_quantity'] ?? 0) > 0) {
            $this->addError('lineas',
                'Ese renglón ya tiene unidades recibidas. No se puede quitar: '
                .'hay contenedores en el inventario que vienen de él.');

            return;
        }

        unset($this->lineas[$i]);
        $this->lineas = array_values($this->lineas);

        if (empty($this->lineas)) {
            $this->lineas = [$this->lineaVacia()];
        }
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    public function updated(string $campo): void
    {
        /* -----------------------------------------------------------------
         | CAMBIÓ EL DEPÓSITO
         |
         | Se copian sus números y se calcula la fecha límite. Solo se
         | rellenan los que estén vacíos o en cero: lo que ya se escribió
         | a mano manda.
         * -------------------------------------------------------------- */
        if ($campo === 'depot_id' && $this->depot_id) {

            $deposito = Depot::find($this->depot_id);

            if ($deposito) {
                if (blank($this->pickup_fee) || (float) $this->pickup_fee === 0.0) {
                    $this->pickup_fee = $deposito->default_pickup_fee;
                }

                $this->daily_late_fee ??= $deposito->daily_late_fee;

                if (blank($this->pickup_deadline_at) && $deposito->default_pickup_days) {
                    $this->pickup_deadline_at = \Carbon\Carbon::parse($this->purchase_date ?: now())
                        ->addDays($deposito->default_pickup_days)
                        ->toDateString();
                }
            }
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL TIPO
         |
         | Una compra simple no tiene plazo: se paga y se lleva. Dejar la
         | fecha puesta haría que apareciera en el contador de "plazo por
         | acabarse" una compra que ya está cerrada.
         * -------------------------------------------------------------- */
        if ($campo === 'type' && $this->type === PurchaseType::Single->value) {
            $this->pickup_deadline_at = null;
        }
    }

    /* =====================================================================
     | LAS CUENTAS
     * ================================================================== */

    public function getSubtotalProperty(): float
    {
        return round(collect($this->lineas)->sum(
            fn ($l) => (float) ($l['quantity'] ?? 0) * (float) ($l['unit_cost'] ?? 0),
        ), 2);
    }

    /**
     * Lo que cuesta el pickup de toda la compra.
     *
     * El campo se guarda POR UNIDAD, como en el Excel: ahí cada renglón
     * es un contenedor y tiene su columna PICK UP. Aquí se multiplica
     * para enseñar el total del lote.
     */
    public function getPickupTotalProperty(): float
    {
        return round((float) ($this->pickup_fee ?: 0) * $this->unidades, 2);
    }

    /**
     * El total.
     *
     * Incluye el pickup, igual que el Excel: ahí el TOTAL de cada renglón
     * es PRECIO + PICK UP. Es el costo de la mercancía puesta en la
     * yarda, que es el número con el que esta empresa trabaja.
     */
    public function getTotalProperty(): float
    {
        return round($this->subtotal + $this->pickupTotal, 2);
    }

    public function getUnidadesProperty(): int
    {
        return (int) collect($this->lineas)->sum(fn ($l) => (int) ($l['quantity'] ?? 0));
    }

    /* =====================================================================
     | REGLAS
     * ================================================================== */

    protected function rules(): array
    {
        return [
            'supplier_id'   => ['required', 'exists:suppliers,id'],
            'type'          => ['required', Rule::in(PurchaseType::values())],
            'reference'     => ['nullable', 'string', 'max:50'],
            'purchase_date' => ['required', 'date'],

            /*
             | Un release SIN depósito no significa nada: el papel autoriza
             | a retirar de algún sitio. Una compra simple puede no tener.
             */
            'depot_id' => ['nullable', 'exists:depots,id',
                Rule::requiredIf(fn () => $this->type === PurchaseType::Release->value)],

            'pickup_fee'     => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'daily_late_fee' => ['nullable', 'numeric', 'min:0', 'max:9999'],

            'pickup_deadline_at' => ['nullable', 'date', 'after_or_equal:purchase_date',
                Rule::requiredIf(fn () => $this->type === PurchaseType::Release->value)],

            'notes'      => ['nullable', 'string', 'max:2000'],

            'lineas'                          => ['required', 'array', 'min:1'],
            'lineas.*.container_size_id'      => ['required', 'exists:container_sizes,id'],
            'lineas.*.container_type_id'      => ['nullable', 'exists:container_types,id'],
            'lineas.*.container_condition_id' => ['nullable', 'exists:container_conditions,id'],
            'lineas.*.container_grade_id'     => ['nullable', 'exists:container_grades,id'],
            'lineas.*.quantity'               => ['required', 'integer', 'min:1', 'max:500'],
            'lineas.*.unit_cost'              => ['required', 'numeric', 'min:0', 'max:999999'],
            'lineas.*.notes'                  => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'supplier_id'                => 'el proveedor',
            'depot_id'                   => 'el depósito',
            'purchase_date'              => 'la fecha',
            'pickup_deadline_at'         => 'el plazo de retiro',
            'lineas.*.container_size_id' => 'la medida',
            'lineas.*.quantity'          => 'la cantidad',
            'lineas.*.unit_cost'         => 'el costo por unidad',
        ];
    }

    protected function messages(): array
    {
        return [
            'supplier_id.required' => 'Elija a quién se le compró.',
            'depot_id.required'    => 'Un release se retira de un depósito. Elija cuál.',
            'pickup_deadline_at.required' => 'Un release tiene fecha límite. Sin ella no se puede '
                                            .'avisar cuándo empieza a cobrar almacenaje.',
            'pickup_deadline_at.after_or_equal' => 'El plazo no puede ser anterior a la compra.',
            'lineas.*.container_size_id.required' => 'Cada renglón necesita la medida.',
            'lineas.*.quantity.min' => 'La cantidad tiene que ser al menos 1.',
        ];
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    public function guardar()
    {
        $this->exigirPermiso($this->purchaseId ? 'update' : 'create');

        $this->validate();

        $empresa = app(CompanyContext::class)->get();

        $compra = DB::transaction(function () use ($empresa) {

            $compra = $this->purchaseId
                ? Purchase::findOrFail($this->purchaseId)
                : new Purchase();

            if (! $compra->exists) {
                $compra->company_id      = $empresa?->id;
                $compra->purchase_number = $this->siguienteNumero($empresa?->id);
                $compra->created_by      = auth()->id();
                $compra->status          = PurchaseStatus::Open->value;

                /*
                 | La fecha límite original se guarda aparte y no se vuelve
                 | a tocar. Si después se negocia una prórroga, el sistema
                 | sigue sabiendo cuál era el plazo de verdad — que es lo
                 | que hace falta para saber si el retraso fue nuestro.
                 */
                $compra->original_deadline_at = $this->pickup_deadline_at ?: null;
            }

            $compra->fill([
                'supplier_id'   => $this->supplier_id,
                'type'          => $this->type,
                'reference'     => $this->reference ?: null,
                'purchase_date' => $this->purchase_date,

                'depot_id'           => $this->depot_id ?: null,
                'pickup_fee'         => (float) ($this->pickup_fee ?: 0),
                'daily_late_fee'     => $this->daily_late_fee !== '' ? $this->daily_late_fee : null,
                'pickup_deadline_at' => $this->pickup_deadline_at ?: null,

                'subtotal'   => $this->subtotal,

                /*
                 | `tax_amount` se deja en cero.
                 |
                 | La hoja COMPRAS del Excel no tiene columna de impuesto:
                 | sus columnas son PRECIO, PICK UP y TOTAL, y el total es
                 | la suma de los dos. La columna existe en la base por si
                 | algún día hace falta, pero pedirla en pantalla sería
                 | inventar un dato que nadie lleva.
                 */
                'tax_amount' => 0,

                'total'      => $this->total,

                'notes' => $this->notes ?: null,
            ])->save();

            /* -------------------------------------------------------------
             | LOS RENGLONES
             |
             | Los que ya no están se borran, salvo que tengan unidades
             | recibidas: esos no pueden desaparecer porque hay
             | contenedores en el inventario que vienen de ellos.
             * ---------------------------------------------------------- */
            $idsQueSiguen = collect($this->lineas)->pluck('id')->filter()->all();

            $compra->items()
                ->when($idsQueSiguen, fn ($q) => $q->whereNotIn('id', $idsQueSiguen))
                ->where('received_quantity', 0)
                ->delete();

            foreach ($this->lineas as $linea) {

                $atributos = [
                    'container_type_id'      => $linea['container_type_id'] ?: null,
                    'container_size_id'      => $linea['container_size_id'],
                    'container_condition_id' => $linea['container_condition_id'] ?: null,
                    'container_grade_id'     => $linea['container_grade_id'] ?: null,
                    'quantity'               => (int) $linea['quantity'],
                    'unit_cost'              => (float) $linea['unit_cost'],
                    'total_cost'             => round((int) $linea['quantity'] * (float) $linea['unit_cost'], 2),
                    'notes'                  => $linea['notes'] ?: null,
                ];

                if (! empty($linea['id'])) {
                    $fila = $compra->items()->whereKey($linea['id'])->first();

                    $fila ? $fila->fill($atributos)->save()
                          : $compra->items()->create($atributos);
                } else {
                    $compra->items()->create($atributos);
                }
            }

            // Vuelve a mirar cuánto se recibió y ajusta el estado.
            $compra->refreshStatus();

            return $compra;
        });

        session()->flash('exito', $this->purchaseId
            ? 'Compra '.$compra->purchase_number.' actualizada.'
            : 'Compra '.$compra->purchase_number.' registrada. '
              .'Las unidades se dan de alta desde aquí a medida que lleguen.');

        return redirect()->route('compras.compras.show', $compra);
    }

    /** La secuencia es por empresa: FLCHR y RST numeran aparte. */
    protected function siguienteNumero(?int $companyId): string
    {
        $prefijo = 'PO-'.now()->format('y').'-';

        $ultimo = Purchase::where('company_id', $companyId)
            ->where('purchase_number', 'like', $prefijo.'%')
            ->orderByDesc('purchase_number')
            ->value('purchase_number');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        do {
            $numero = $prefijo.str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
            $siguiente++;
        } while (Purchase::where('company_id', $companyId)
                         ->where('purchase_number', $numero)->exists());

        return $numero;
    }

    public function render()
    {
        return view('livewire.purchases.form', [
            'proveedores' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'depositos'   => Depot::where('is_active', true)->orderBy('name')->get(),

            'tipos'       => PurchaseType::options(),
            'medidas'     => ContainerSize::active()->get(),
            'tiposCont'   => ContainerType::active()->get(),
            'condiciones' => ContainerCondition::active()->get(),
            'calidades'   => ContainerGrade::active()->get(),
        ]);
    }
}
