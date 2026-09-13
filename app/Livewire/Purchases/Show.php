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
 * ── EL PICKUP VA ENTERO A CADA UNIDAD ──
 *
 * Y no dividido, que es como lo tenía yo al principio. Me corrigió la
 * hoja COMPRAS del Excel: ahí cada renglón es UN contenedor y tiene su
 * propia columna PICK UP —$50 para un 20FT de Touax, $100 para un 40FT
 * de Grand Pacific— y el TOTAL de ese renglón es PRECIO + PICK UP.
 *
 * O sea que el pickup se cobra por caja, no por viaje. Dividirlo entre
 * las unidades habría hecho que el costo de cada contenedor no cuadrara
 * con el que la empresa lleva calculando desde siempre.
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
     | EL FORMULARIO DE RECIBIR
     * ================================================================== */

    public ?int $itemRecibiendo = null;

    public ?string $numeroUnidad   = null;
    public ?string $codigoUnidad   = null;
    public ?int    $ubicacionId    = null;
    public ?string $notaUnidad     = null;

    public function mount(Purchase $purchase): void
    {
        $this->exigirPermiso('view');

        $this->purchase = $purchase;
    }

    public function abrirRecibir(int $itemId): void
    {
        $this->exigirPermiso('update');

        $this->resetValidation();

        $this->itemRecibiendo = $itemId;
        $this->numeroUnidad   = null;
        $this->codigoUnidad   = null;
        $this->notaUnidad     = null;
    }

    public function cerrarRecibir(): void
    {
        $this->itemRecibiendo = null;
        $this->resetValidation();
    }

    /**
     * Registra UNA unidad que acaba de llegar.
     *
     * De una en una a propósito: cada contenedor tiene su número, y
     * escribirlos de siete en siete en un solo formulario es la forma más
     * rápida de meter el número equivocado en la unidad equivocada.
     */
    public function recibirUnidad(): void
    {
        $this->exigirPermiso('update');

        $item = PurchaseItem::find($this->itemRecibiendo);

        if (! $item || $item->purchase_id !== $this->purchase->id) {
            $this->cerrarRecibir();

            return;
        }

        $this->validate([
            'numeroUnidad' => ['nullable', 'string', 'max:15',
                              'unique:containers,container_number'],
            'codigoUnidad' => ['nullable', 'string', 'max:20'],
            'ubicacionId'  => ['nullable', 'exists:locations,id'],
            'notaUnidad'   => ['nullable', 'string', 'max:500'],
        ], [
            'numeroUnidad.unique' => 'Ya hay una unidad registrada con ese número. '
                                    .'Un contenedor no puede estar dos veces en el inventario.',
        ], [
            'numeroUnidad' => 'el número del contenedor',
            'codigoUnidad' => 'el código interno',
        ]);

        if (blank($this->numeroUnidad) && blank($this->codigoUnidad)) {
            $this->addError('numeroUnidad',
                'Escriba el número pintado en la unidad o, si no se lee, un código interno '
                .'con el que la yarda pueda pedirla.');

            return;
        }

        if ($item->received_quantity >= $item->quantity) {
            $this->addError('numeroUnidad',
                'Este renglón ya tiene sus '.$item->quantity.' unidades recibidas. '
                .'Si llegó una de más, corrija la compra.');

            return;
        }

        DB::transaction(function () use ($item) {

            /* -------------------------------------------------------------
             | EL COSTO DE ESTA UNIDAD
             |
             | Lo que costó más el pickup, ENTERO. En el Excel el pickup
             | es una columna por contenedor y el total del renglón es
             | precio + pickup, así que se replica igual.
             * ---------------------------------------------------------- */
            $pickupDeLaUnidad = (float) $this->purchase->pickup_fee;

            $unidad = Container::create([
                'container_number' => $this->numeroUnidad ?: null,
                'internal_code'    => $this->codigoUnidad ?: null,

                'container_type_id'      => $item->container_type_id,
                'container_size_id'      => $item->container_size_id,
                'container_condition_id' => $item->container_condition_id,
                'container_grade_id'     => $item->container_grade_id,

                'status'      => ContainerStatus::InYard->value,
                'location_id' => $this->ubicacionId ?: null,
                'received_at' => now()->toDateString(),

                'acquisition_cost' => $item->unit_cost,
                'pickup_cost'      => $pickupDeLaUnidad,

                /*
                 | De dónde salió.
                 |
                 | La columna apunta al RENGLÓN, no a la compra. Es más
                 | preciso: del renglón se llega a la compra, y además se
                 | sabe de qué lote exacto salió esta unidad — que es lo
                 | que dice su costo.
                 */
                'purchase_item_id' => $item->id,

                'owner_company_id'   => $this->purchase->company_id,
                'billing_company_id' => $this->purchase->company_id,

                'condition_notes' => $this->notaUnidad ?: null,
                'created_by'      => auth()->id(),
            ]);

            $unidad->movements()->create([
                'type'           => MovementType::Receipt->value,
                'to_location_id' => $unidad->location_id,
                'status_after'   => ContainerStatus::InYard->value,
                'moved_at'       => now(),
                'notes'          => 'Recibida del depósito · compra '.$this->purchase->purchase_number,
                'created_by'     => auth()->id(),
            ]);

            $item->increment('received_quantity');

            // Vuelve a mirar el total recibido y ajusta el estado de la compra.
            $this->purchase->refreshStatus();
        });

        $this->purchase->refresh();

        $identificador = $this->numeroUnidad ?: $this->codigoUnidad;

        $this->numeroUnidad = null;
        $this->codigoUnidad = null;
        $this->notaUnidad   = null;

        session()->flash('exito', 'Unidad '.$identificador.' recibida y dada de alta en el inventario.');
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
        ]);
    }
}
