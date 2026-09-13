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

        $this->container->update([
            'status'      => $this->nuevoEstado,
            'location_id' => $this->nuevaUbicacion,
        ]);

        $this->container->movements()->create([
            'type' => $cambioUbicacion
                ? MovementType::Transfer->value
                : MovementType::StatusChange->value,

            'from_location_id' => $ubicacionAntes,
            'to_location_id'   => $this->nuevaUbicacion,

            'status_before' => $estadoAntes,
            'status_after'  => $this->nuevoEstado,

            'moved_at'   => now(),
            'notes'      => $this->notaMovimiento ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->container->refresh();

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
            ->limit(30)
            ->get();

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

            /*
             | Dónde ha estado vendida o rentada. Con el precio del día,
             | que es el que quedó congelado en el pivot.
             */
            'ventas'  => $this->container->sales()->with('customer')->latest('sale_date')->limit(5)->get(),
            'rentas'  => $this->container->rentals()->with('customer')->latest('start_date')->limit(5)->get(),

            'estados'     => ContainerStatus::options(),
            'ubicaciones' => Location::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
