<?php

namespace App\Livewire\Trips;

use App\Enums\TripStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Trip;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL DETALLE DE UN VIAJE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Una pantalla de lectura con dos acciones que se usan todos los días y
 * que, puestas acá, ahorran entrar a editar:
 *
 *   Marcar completado     es lo que hace que el viaje entre en la factura
 *                         semanal. Hasta que no se marca, no se cobra.
 *
 *   Marcar pagado al      cierra la deuda con el chofer.
 *   chofer
 *
 * Las dos son de un clic porque se hacen en el teléfono, de pie en la yarda.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'trips';

    public Trip $trip;

    public function mount(Trip $trip): void
    {
        $this->exigirPermiso('view');

        $this->trip = $trip;
    }

    /**
     * Marcar el viaje como completado.
     *
     * Se pone la fecha si no la hay: sin fecha, la factura semanal no sabe
     * en qué semana cae y el viaje se queda fuera de todas.
     */
    public function completar(): void
    {
        $this->exigirPermiso('update');

        if ($this->trip->status === TripStatus::Completed) {
            return;
        }

        $this->trip->status = TripStatus::Completed;
        $this->trip->completed_at = $this->trip->completed_at ?: now();
        $this->trip->save();

        session()->flash('exito', 'Viaje marcado como completado. Ya entra en la factura semanal.');
    }

    /**
     * Cerrar la deuda con el chofer.
     *
     * No registra un pago con su método y su referencia: eso es la
     * liquidación de choferes, que es otro módulo. Esto solo marca que ya
     * se le pagó, para que deje de aparecer en el contador de pendientes.
     */
    public function marcarPagadoChofer(): void
    {
        $this->exigirPermiso('update');

        $this->trip->driver_payment_status = 'paid';
        $this->trip->save();

        session()->flash('exito', 'Pago al chofer marcado como hecho.');
    }

    public function render()
    {
        return view('livewire.trips.show', [
            'viaje' => $this->trip->load([
                'customer', 'driver', 'vehicle', 'carrier',
                'container', 'depot', 'intercompanyInvoice',
            ]),
        ]);
    }
}
