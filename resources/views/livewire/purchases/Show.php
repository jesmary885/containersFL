<?php

namespace App\Livewire\Purchases;

use App\Enums\ContainerStatus;
use App\Enums\MovementType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Container;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DE LA COMPRA — y donde se reciben las unidades
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Esta es la pantalla que arregla el problema de los 416.
 *
 * ── RECIBIR ES DAR DE ALTA UN CONTENEDOR ──
 *
 * No es marcar una casilla. Cuando el camión trae una unidad, alguien
 * lee el número pintado en la puerta y lo registra. Eso hace tres cosas
 * a la vez:
 *
 *   1. crea el contenedor en el inventario, con su número real
 *   2. le pone el costo: lo que costó + su parte del pickup
 *   3. sube en uno el "recibido" del renglón de la compra
 *
 * Las tres juntas o ninguna. Si se creara el contenedor y fallara el
 * contador, el inventario y la compra dirían cosas distintas — que es
 * exactamente la enfermedad del Excel.
 *
 * ── EL PICKUP SE PREGUNTA AQUI, NO AL COMPRAR ──
 *
 * El dia que se firma el release nadie sabe cuanto va a costar traer la
 * mercancia: depende de cuantas se traigan por viaje y de quien las
 * traiga.
 *
 * Cada unidad lleva el suyo, como en la hoja COMPRAS del Excel: $50 un
 * 20FT de Touax, $100 un 40FT de Grand Pacific, $0 cuando vino directo.
 * Se propone desde el deposito y se puede cambiar unidad por unidad.
 *
 * ── NO SE PUEDE RECIBIR DE MÁS ──
 *
 * Si el renglón dice siete y ya hay siete, no deja registrar la octava.
 * Llegó algo que no se compró, y eso se arregla corrigiendo la compra,
 * no metiéndolo por la puerta de atrás.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'purchases';

    public Purchase $purchase;

    /* =====================================================================
     | EL RETIRO
     |
     | ── POR QUE ES UNA OPERACION Y NO UNA CASILLA ──
     |
     | Un release de siete no se retira de una vez. Va un camion, trae dos
     | o tres, y vuelve otro dia. Cada viaje tiene su fecha, su costo y su
     | transportista, y son distintos entre si.
     |
     | Por eso el pickup dejo de pedirse al registrar la compra: ese dia
     | nadie sabe cuanto va a costar traerla.
     |
     | Un retiro es: "el martes fue Miguelito, trajo estas tres, y costo
     | $50 cada una". Se marcan las que vienen, se escriben sus numeros y
     | se registra todo de golpe.
     * ================================================================== */

    public bool $modalRetiro = false;

    /** El renglon de la compra del que se esta retirando. */
    public ?int $itemRetirando = null;

    /**
     * Cuantas unidades trae este viaje.
     *
     * ── POR QUE NO ESTA TIPADA COMO int ──
     *
     * Aqui estaba el error que congelaba el modal.
     *
     * El campo es `wire:model.live`. Cuando el usuario borra el numero
     * para escribir otro, el navegador manda una cadena vacia, y Livewire
     * no puede meter '' en una propiedad declarada `int`: revienta la
     * peticion entera.
     *
     * Desde fuera se ve exactamente como lo describiste: el modal deja de
     * responder, no guarda y no cierra, como si esperara algo.
     *
     * Sin tipo, la cadena vacia entra sin problema y se normaliza abajo.
     * Lo mismo con los tres selects: un `<option value="">` manda '' y
     * una propiedad `?int` lo rechaza igual.
     */
    public $cuantas = 1;

    /** Una fila por unidad: numero, codigo y su pickup. */
    public array $unidadesDelRetiro = [];

    /* ── LO QUE COMPARTEN TODAS LAS DEL VIAJE ── */
    public ?string $fechaRetiro = null;
    public string  $quienTrajo  = 'proveedor';   // proveedor | trabajador | transportista
    public ?string $notaRetiro  = null;

    /* Sin tipo, por lo mismo que $cuantas: los selects mandan ''. */
    public $ubicacionId     = null;
    public $empleadoId      = null;
    public $transportistaId = null;

    public function abrirRetiro(int $itemId): void
    {
        $this->exigirPermiso('update');
        $this->resetValidation();

        $item = PurchaseItem::find($itemId);

        if (! $item || $item->purchase_id !== $this->purchase->id) {
            return;
        }

        $this->itemRetirando = $itemId;
        $this->fechaRetiro   = now()->toDateString();
        $this->notaRetiro    = null;

        /*
         | Arranca con UNA unidad, no con todas las pendientes.
         |
         | Lo normal es traer una o dos por viaje. Abrir con siete filas
         | vacias obliga a borrar cinco, y borrar filas es donde la gente
         | se equivoca.
         */
        $this->cuantas = 1;
        $this->rehacerFilas();

        $this->modalRetiro = true;
    }

    public function cerrarRetiro(): void
    {
        $this->modalRetiro       = false;
        $this->itemRetirando     = null;
        $this->unidadesDelRetiro = [];
        $this->resetValidation();
    }

    /**
     * Ajusta las filas al numero de unidades elegido.
     *
     * Se conserva lo ya escrito: si alguien puso dos numeros y despues
     * sube a tres, los dos primeros siguen ahi.
     */
    public function rehacerFilas(): void
    {
        $item = PurchaseItem::find($this->itemRetirando);

        $pendientes = $item ? max(0, $item->quantity - $item->received_quantity) : 0;

        /* La cadena vacia se trata como 1. */
        $pedidas = (int) ($this->cuantas ?: 1);

        $this->cuantas = max(1, min($pedidas, $pendientes ?: 1));

        $sugerido = $this->purchase->depot?->default_pickup_fee;

        $filas = [];

        for ($i = 0; $i < $this->cuantas; $i++) {
            $filas[$i] = $this->unidadesDelRetiro[$i] ?? [
                'numero' => null,
                'codigo' => null,

                /*
                 | El pickup se propone desde el deposito y se puede
                 | cambiar unidad por unidad: en el Excel un 20FT de Touax
                 | son $50 y un 40FT de Grand Pacific son $100, y a veces
                 | en el mismo viaje vienen de los dos.
                 */
                'pickup' => $sugerido,
            ];
        }

        $this->unidadesDelRetiro = $filas;
    }

    public function updatedCuantas(): void
    {
        $this->rehacerFilas();
    }

    /** El costo total de este viaje, mientras se escribe. */
    public function getPickupDelRetiroProperty(): float
    {
        return round(collect($this->unidadesDelRetiro)->sum(fn ($u) => (float) ($u['pickup'] ?? 0)), 2);
    }

    /**
     * Registra el viaje entero: N contenedores de golpe.
     *
     * Todo o nada. Si una de las tres unidades tiene el numero repetido,
     * no entra ninguna: mejor corregir y repetir que quedarse con dos
     * adentro y una fuera sin saber cual.
     */
    public function registrarRetiro(): void
    {
        $this->exigirPermiso('update');

        $item = PurchaseItem::find($this->itemRetirando);

        if (! $item || $item->purchase_id !== $this->purchase->id) {
            $this->cerrarRetiro();

            return;
        }

        /* -----------------------------------------------------------------
         | LAS CADENAS VACIAS SE VUELVEN NULL ANTES DE VALIDAR
         |
         | Un campo de texto que el usuario vacia manda '', no null. Y ''
         | no es null para `nullable`, asi que reglas como `unique` o
         | `distinct` se ejecutaban sobre cadenas vacias y fallaban sin que
         | se entendiera por que.
         * -------------------------------------------------------------- */
        foreach ($this->unidadesDelRetiro as $i => $u) {
            foreach (['numero', 'codigo', 'pickup'] as $campo) {
                if (($u[$campo] ?? null) === '') {
                    $this->unidadesDelRetiro[$i][$campo] = null;
                }
            }
        }

        $this->ubicacionId     = $this->ubicacionId     ?: null;
        $this->empleadoId      = $this->empleadoId      ?: null;
        $this->transportistaId = $this->transportistaId ?: null;

        $this->validate([
            'fechaRetiro'                 => ['required', 'date'],
            'ubicacionId'                 => ['nullable', 'exists:locations,id'],
            'empleadoId'                  => ['nullable', 'exists:employees,id'],
            'transportistaId'             => ['nullable', 'exists:suppliers,id'],
            'notaRetiro'                  => ['nullable', 'string', 'max:500'],
            'unidadesDelRetiro'           => ['required', 'array', 'min:1'],
            'unidadesDelRetiro.*.numero'  => ['nullable', 'string', 'max:15',
                                              'distinct', 'unique:containers,container_number'],
            'unidadesDelRetiro.*.codigo'  => ['nullable', 'string', 'max:20'],
            'unidadesDelRetiro.*.pickup'  => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ], [
            'unidadesDelRetiro.*.numero.unique'   => 'Ese numero ya esta en el inventario. '
                                                    .'Un contenedor no puede estar dos veces.',
            'unidadesDelRetiro.*.numero.distinct' => 'Ese numero esta repetido en este mismo retiro.',
        ], [
            'fechaRetiro' => 'la fecha del retiro',
        ]);

        // Cada unidad necesita como llamarse.
        foreach ($this->unidadesDelRetiro as $i => $u) {
            if (blank($u['numero'] ?? null) && blank($u['codigo'] ?? null)) {
                $this->addError('unidadesDelRetiro.'.$i.'.numero',
                    'Escriba el numero pintado o, si no se lee, un codigo interno.');

                return;
            }
        }

        $pendientes = max(0, $item->quantity - $item->received_quantity);

        if (count($this->unidadesDelRetiro) > $pendientes) {
            $this->addError('cuantas',
                'De este renglon solo faltan '.$pendientes.'. Si llegaron mas, corrija la compra.');

            return;
        }

        DB::transaction(function () use ($item) {

            foreach ($this->unidadesDelRetiro as $u) {

                $unidad = Container::create([
                    'container_number' => $u['numero'] ?: null,
                    'internal_code'    => $u['codigo'] ?: null,

                    'container_type_id'      => $item->container_type_id,
                    'container_size_id'      => $item->container_size_id,
                    'container_condition_id' => $item->container_condition_id,
                    'container_grade_id'     => $item->container_grade_id,

                    'status'      => ContainerStatus::InYard->value,
                    'location_id' => $this->ubicacionId ?: null,
                    'received_at' => $this->fechaRetiro,

                    'acquisition_cost' => $item->unit_cost,
                    'pickup_cost'      => (float) ($u['pickup'] ?: 0),

                    /*
                     | Quien lo trajo. Las dos en null significa que lo
                     | trajo el proveedor, que es el caso de las filas del
                     | Excel con PICK UP en $0.00.
                     */
                    'pickup_by_employee_id' => $this->quienTrajo === 'trabajador'
                        ? $this->empleadoId : null,
                    'pickup_supplier_id'    => $this->quienTrajo === 'transportista'
                        ? $this->transportistaId : null,
                    'picked_up_at'          => $this->fechaRetiro,

                    'purchase_item_id' => $item->id,

                    'owner_company_id'   => $this->purchase->company_id,
                    'billing_company_id' => $this->purchase->company_id,

                    'condition_notes' => $this->notaRetiro ?: null,
                    'created_by'      => auth()->id(),
                ]);

                $unidad->movements()->create([
                    'type'           => MovementType::Receipt->value,
                    'to_location_id' => $unidad->location_id,
                    'status_after'   => ContainerStatus::InYard->value,
                    'moved_at'       => $this->fechaRetiro,
                    'notes'          => 'Retirada del deposito · compra '.$this->purchase->purchase_number,
                    'created_by'     => auth()->id(),
                ]);
            }

            $item->increment('received_quantity', count($this->unidadesDelRetiro));

            /*
             | El pickup de la compra se va acumulando.
             |
             | Asi la ficha puede decir cuanto se lleva gastado en traer
             | ese release, que es un numero que hoy no existe en ninguna
             | parte.
             */
            $this->purchase->increment('pickup_fee', $this->pickupDelRetiro);

            $this->purchase->refreshStatus();
        });

        $cuantas = count($this->unidadesDelRetiro);

        $this->purchase->refresh();
        $this->cerrarRetiro();

        session()->flash('exito', $cuantas === 1
            ? 'Unidad retirada y dada de alta en el inventario.'
            : $cuantas.' unidades retiradas y dadas de alta en el inventario.');
    }

    /* =====================================================================
     | CORREGIR
     * ================================================================== */

    /** La unidad que espera confirmacion para deshacerse. */
    public ?int $unidadPorQuitar = null;

    public function pedirQuitarUnidad(int $containerId): void
    {
        $this->unidadPorQuitar = $containerId;
    }

    public function cancelarQuitarUnidad(): void
    {
        $this->unidadPorQuitar = null;
    }

    /**
     * Deshace el alta de una unidad mal registrada.
     *
     * ── POR QUE HACE FALTA ──
     *
     * Se teclea un numero mal, o se registran tres cuando vinieron dos.
     * Sin esta salida, la unica forma de arreglarlo era editar el
     * contenedor por un lado y el contador de la compra por otro, a mano,
     * y quedaban diciendo cosas distintas.
     *
     * ── LO QUE NO DEJA BORRAR ──
     *
     * Una unidad ya vendida o rentada. Ahi el error dejo de ser un error
     * de tecleo: hay un cliente con un contrato encima. Eso se corrige
     * anulando la venta, no borrando el contenedor.
     *
     * Todo va junto: se borra el contenedor, baja el contador del renglon
     * y se descuenta su pickup del acumulado de la compra. Las tres o
     * ninguna.
     */
    public function quitarUnidad(): void
    {
        $this->exigirPermiso('update');

        $unidad = Container::find($this->unidadPorQuitar);

        if (! $unidad || ! $unidad->purchase_item_id) {
            $this->unidadPorQuitar = null;

            return;
        }

        $item = PurchaseItem::find($unidad->purchase_item_id);

        if (! $item || $item->purchase_id !== $this->purchase->id) {
            $this->unidadPorQuitar = null;

            return;
        }

        if ($unidad->sales()->exists() || $unidad->rentals()->exists()) {
            $this->unidadPorQuitar = null;

            session()->flash('error',
                'Esa unidad ya tiene una venta o una renta encima. Anule primero ese documento: '
                .'borrarla dejaria un contrato apuntando a un contenedor que no existe.');

            return;
        }

        $identificador = $unidad->full_identifier;
        $pickup        = (float) $unidad->pickup_cost;

        DB::transaction(function () use ($unidad, $item, $pickup) {

            $unidad->movements()->delete();
            $unidad->forceDelete();

            if ($item->received_quantity > 0) {
                $item->decrement('received_quantity');
            }

            if ($pickup > 0 && (float) $this->purchase->pickup_fee >= $pickup) {
                $this->purchase->decrement('pickup_fee', $pickup);
            }

            $this->purchase->refreshStatus();
        });

        $this->purchase->refresh();
        $this->unidadPorQuitar = null;

        session()->flash('exito', 'Se deshizo el alta de '.$identificador.'. '
            .'Vuelve a contar como pendiente de retirar.');
    }

    public function render()
    {
        $this->purchase->load([
            'supplier', 'depot', 'createdBy',
            'items.size', 'items.type', 'items.condition', 'items.grade',
        ]);

        $compradas = (int) $this->purchase->items->sum('quantity');
        $recibidas = (int) $this->purchase->items->sum('received_quantity');

        return view('livewire.purchases.show', [
            'compradas' => $compradas,
            'recibidas' => $recibidas,
            'faltan'    => max(0, $compradas - $recibidas),

            /*
             | Las unidades que ya entraron por esta compra. Es la prueba
             | de que el número de "recibidas" no es una casilla: detrás
             | hay contenedores con nombre y apellido.
             */
            'unidades' => Container::whereIn('purchase_item_id', $this->purchase->items->pluck('id'))
                ->with(['size:id,name', 'location:id,name'])
                ->latest('id')
                ->get(),

            'ubicaciones' => Location::where('is_active', true)->orderBy('name')->get(),

            /* Quien puede traer: los de la casa y los de fuera. */
            'empleados' => \App\Models\Employee::active()
                ->forCompany($this->purchase->company_id)
                ->orderBy('first_name')->get(),

            'transportistas' => \App\Models\Supplier::where('is_active', true)
                ->orderBy('name')->get(),
        ]);
    }
}
