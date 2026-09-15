<?php

namespace App\Livewire\Containers;

use App\Enums\ContainerStatus;
use App\Enums\MovementType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Container;
use App\Models\Location;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL CONTENEDOR — lo que se consulta
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Cuatro cosas: qué es, cuánto costó y cuánto vale, dónde ha estado, y
 * qué papeles tiene.
 *
 * ── EL HISTORIAL ES LA MITAD DEL VALOR DE ESTA PANTALLA ──
 *
 * La pregunta que nadie puede contestar hoy es "¿dónde estaba esta
 * unidad en marzo?". El Excel no lo guarda y la memoria de la gente
 * tampoco.
 *
 * Cada alta, cada traslado y cada cambio de estado deja su asiento con
 * fecha y con autor. No es auditoría por gusto: es lo que permite
 * contestar por qué una unidad que figuraba disponible no estaba en la
 * yarda.
 *
 * ── EL MOVIMIENTO RÁPIDO ──
 *
 * Mover una unidad de sitio no debería obligar a abrir el formulario
 * entero. Desde aquí se cambia el estado o la ubicación con dos clics, y
 * el asiento se escribe solo.
 *
 * Es el criterio de Michael, textual de la minuta del 8 de agosto: todo
 * lo que pueda resolverse con un clic, debe resolverse con un clic.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use AuthorizesAccess;

    /*
     | WithPagination pagina el historial de movimientos.
     |
     | Antes se cortaba en 30 con un limit() y el resto no habia forma de
     | verlo: una unidad que lleva dos anos rotando entre patios pasa de
     | 30 asientos y la parte vieja quedaba inalcanzable.
     |
     | Se usa el nombre de pagina 'mov' y no el 'page' de siempre porque
     | esta ficha ya tiene mas listas. Con el nombre por defecto, pasar de
     | pagina en una moveria la otra.
     */
    use WithPagination;

    protected string $permisoBase = 'containers';

    public Container $container;

    /* =====================================================================
     | EL MOVIMIENTO RÁPIDO
     * ================================================================== */

    public bool $modalMover = false;

    public string $nuevoEstado    = '';
    public ?int   $nuevaUbicacion = null;
    public ?string $notaMovimiento = null;

    public function mount(Container $container): void
    {
        $this->exigirPermiso('view');

        $this->container = $container;
    }

    public function abrirMover(): void
    {
        $this->exigirPermiso('update');

        $this->resetValidation();

        $this->nuevoEstado     = $this->container->status?->value ?? ContainerStatus::InYard->value;
        $this->nuevaUbicacion  = $this->container->location_id;
        $this->notaMovimiento  = null;

        $this->modalMover = true;
    }

    public function cerrarMover(): void
    {
        $this->modalMover = false;
        $this->resetValidation();
    }

    public function mover(): void
    {
        $this->exigirPermiso('update');

        $this->validate([
            'nuevoEstado'    => ['required', Rule::in(ContainerStatus::values())],
            'nuevaUbicacion' => ['nullable', 'exists:locations,id'],
            'notaMovimiento' => ['nullable', 'string', 'max:500'],
        ], [], [
            'nuevoEstado'    => 'el estado',
            'nuevaUbicacion' => 'la ubicación',
        ]);

        $estadoAntes    = $this->container->status?->value;
        $ubicacionAntes = $this->container->location_id;

        $cambioEstado    = $estadoAntes !== $this->nuevoEstado;
        $cambioUbicacion = $ubicacionAntes !== $this->nuevaUbicacion;

        /*
         | Sin cambios no se escribe nada.
         |
         | Un historial lleno de asientos que dicen "de en yarda a en
         | yarda" es un historial que nadie lee, y deja de servir para lo
         | único que sirve.
         */
        if (! $cambioEstado && ! $cambioUbicacion) {
            $this->addError('nuevoEstado', 'No cambió nada. Elija otro estado u otra ubicación.');

            return;
        }

        /* -----------------------------------------------------------------
         | AQUI ESTABA EL DUPLICADO
         |
         | Antes esto hacia dos cosas: actualizaba el contenedor Y creaba
         | el movimiento a mano.
         |
         | El problema es que actualizar el contenedor YA dispara al
         | ContainerObserver, que escribe su propio movimiento. Cada clic
         | en "Mover" dejaba dos asientos identicos en el historial, y
         | por eso se veia todo repetido.
         |
         | La regla es que el historial lo escribe SOLO el observer: es
         | lo que garantiza que un movimiento hecho desde una factura, una
         | venta o un comando quede registrado igual que uno hecho desde
         | esta pantalla.
         |
         | Lo unico que el observer no podia saber es el tipo y la nota.
         | Ahora se los dejamos puestos antes de guardar.
         * -------------------------------------------------------------- */
        $this->container->conMovimiento(
            tipo: $cambioUbicacion ? MovementType::Transfer : MovementType::StatusChange,
            nota: $this->notaMovimiento ?: null,
        );

        $this->container->update([
            'status'      => $this->nuevoEstado,
            'location_id' => $this->nuevaUbicacion,
        ]);

        $this->container->refresh();

        // Al listar el historial se vuelve a la primera pagina: el
        // movimiento que se acaba de hacer esta arriba del todo.
        $this->resetPage('mov');

        $this->cerrarMover();

        session()->flash('exito', 'Unidad '.$this->container->full_identifier.' actualizada. '
            .'Queda en el historial con la fecha y quién lo hizo.');
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        $this->container->load([
            'size', 'condition', 'grade', 'type',
            'location', 'depot',
            'ownerCompany', 'billingCompany',
        ]);

        /* -----------------------------------------------------------------
         | EL HISTORIAL
         |
         | Del más reciente al más viejo, que es como se lee: la pregunta
         | casi siempre es "¿qué fue lo último que pasó?".
         * -------------------------------------------------------------- */
        $movimientos = $this->container->movements()
            ->with(['fromLocation', 'toLocation', 'createdBy'])
            ->latest('moved_at')
            /*
             | latest('id') como segundo criterio.
             |
             | Varios movimientos de la misma unidad pueden compartir el
             | mismo moved_at al segundo —una venta que cambia estado y
             | ubicacion a la vez—. Sin un segundo criterio, el orden
             | entre ellos lo decide la base y puede cambiar de una
             | pagina a otra: se veria un asiento repetido en la pagina 1
             | y en la 2, y otro que no aparece en ninguna.
             */
            ->latest('id')
            ->paginate(15, pageName: 'mov');

        /* -----------------------------------------------------------------
         | ¿SE PUEDE VENDER HOY?
         |
         | Es la primera pregunta de un vendedor mirando esta ficha, y no
         | se contesta con el estado: una unidad "en yarda" con una venta
         | encima no está disponible (RB-019).
         |
         | Se pregunta con el mismo scope que usa el inventario, para que
         | la ficha y el listado no puedan contradecirse.
         * -------------------------------------------------------------- */
        $disponible = Container::whereKey($this->container->id)->available()->exists();

        return view('livewire.containers.show', [

            'movimientos' => $movimientos,
            'disponible'  => $disponible,

            'documentos' => $this->container->documents()
                ->orderByRaw('expires_at IS NULL')
                ->orderBy('expires_at')
                ->get(),

            /* -----------------------------------------------------------------
             | EL HISTORIAL COMERCIAL
             |
             | ── POR QUE SALE DE LAS FACTURAS ──
             |
             | Antes esta seccion leia sale_containers y rental_containers,
             | los pivots que escriben los modulos de Ventas y Rentas. Esos
             | modulos todavia no existen, asi que los pivots estan vacios y
             | la ficha decia "nunca se ha rentado" de una unidad que se
             | acababa de rentar.
             |
             | En este sistema la factura ES la venta, y cada renglon de
             | factura guarda su container_id. Ahi esta el dato real: quien,
             | cuando, cuanto, en que documento y si ya pago.
             |
             | El dia que existan Ventas y Rentas como modulos, esta consulta
             | se amplia; no se tira.
             |
             | ── SOLO LAS DE LA EMPRESA ACTIVA ──
             |
             | El filtro de compania se aplica solo en la subconsulta. Es lo
             | correcto: una misma unidad puede facturarse desde FLCHR o
             | desde RST, y cada quien ve lo suyo.
             * -------------------------------------------------------------- */
            'lineasFacturadas' => \App\Models\InvoiceItem::query()
                ->where('container_id', $this->container->id)
                ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))
                ->with([
                    'invoice:id,invoice_number,customer_id,issue_date,status,balance_due,total',
                    'invoice.customer:id,display_name,company_name',
                    'product:id,name,code,type',
                ])
                ->orderByDesc('id')
                ->limit(20)
                ->get(),

            'estados'     => ContainerStatus::options(),
            'ubicaciones' => Location::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
