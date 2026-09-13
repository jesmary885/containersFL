<?php

namespace App\Livewire\Payments;


use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\AuthorizesAccess;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL PAGO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Tres cosas se pueden hacer desde aquí, y las tres cambian dinero de
 * sitio, así que las tres piden confirmación:
 *
 *   1. Aplicar el saldo sin asignar a otra factura del mismo cliente.
 *   2. Revertir una asignación puntual (se aplicó a la factura
 *      equivocada).
 *   3. Cambiar el estado del pago. Si pasa a fallido, reembolsado o en
 *      disputa, el PaymentObserver revierte TODO lo que tuviera
 *      aplicado — automáticamente, sin que haya que tocar nada más
 *      aquí.
 *
 * No hay botón de "editar el monto". Un pago ya confirmado es un hecho
 * bancario: si el monto estaba mal, se revierte lo aplicado, se marca
 * como fallido o se anota en las notas, y se registra uno nuevo con el
 * monto correcto. Igual que una factura, no se toca lo que ya pasó.
 */

#[Layout('layouts.app')]
class Show extends Component
{
    use AuthorizesAccess;

    /* =====================================================================
     | LOS PERMISOS
     |
     | El `can:` de la ruta impide ABRIR esta pantalla. No impide llamar
     | a sus metodos: Livewire manda cada clic a /livewire/update, que es
     | otra ruta y no lleva ese `can:` encima.
     |
     | Por eso cada metodo que cambia algo exige el permiso otra vez.
     * ================================================================== */

    protected string $permisoBase = 'payments';
    public Payment $payment;

    /** Qué acción está esperando confirmación. */
    public ?string $confirmando = null;

    /** La asignación que se va a revertir, mientras se confirma. */
    public ?int $allocationIdAConfirmar = null;

    /** El nuevo estado, mientras se confirma el cambio. */
    public ?string $nuevoEstado = null;
    public string $notaCambioEstado = '';

    /** Para aplicar el saldo restante a otra factura. */
    public ?int $facturaParaAplicar = null;
    public float $montoParaAplicar  = 0;

    public function mount(Payment $payment): void
    {
        $this->exigirPermiso('view');

        $this->payment = $payment->load([
            'customer',
            'cardAuth',
            'createdBy',
            'allocations.invoice',
            'allocations.allocatedBy',
        ]);
    }

    /* =====================================================================
     | APLICAR EL SALDO SIN ASIGNAR
     * ================================================================== */

    public function getFacturasPendientesProperty()
    {
        return Invoice::query()
            ->where('customer_id', $this->payment->customer_id)
            ->unpaid()
            ->orderBy('due_date')
            ->get();
    }

    /** Al elegir la factura, se precarga el monto sugerido: lo menor entre lo que queda del pago y lo que debe la factura. */
    public function updatedFacturaParaAplicar($invoiceId): void
    {
        $factura = $invoiceId ? Invoice::find($invoiceId) : null;

        $this->montoParaAplicar = $factura
            ? round(min((float) $this->payment->available_amount, (float) $factura->balance_due), 2)
            : 0;
    }

    public function aplicarSaldo(): void
    {
        // Aplicar mueve dinero de una factura a otra: es edicion.
        $this->exigirPermiso('update');

        $this->validate([
            'facturaParaAplicar' => ['required', 'integer'],
            'montoParaAplicar'   => ['required', 'numeric', 'min:0.01'],
        ], [
            'facturaParaAplicar.required' => 'Elige a qué factura se aplica.',
        ]);

        $factura = Invoice::find($this->facturaParaAplicar);

        if (! $factura) {
            session()->flash('error', 'Esa factura ya no está disponible.');

            return;
        }

        try {
            $this->payment->applyTo($factura, (float) $this->montoParaAplicar);

            $this->payment->refresh()->load('allocations.invoice');
            $this->reset(['facturaParaAplicar', 'montoParaAplicar']);

            session()->flash('exito', 'Se aplicaron $'.number_format((float) $this->montoParaAplicar, 2).' a la factura '.$factura->invoice_number.'.');
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo aplicar: '.$e->getMessage());
        }
    }

    /* =====================================================================
     | REVERTIR UNA ASIGNACIÓN
     * ================================================================== */

    public function pedirReversion(int $allocationId): void
    {
        $this->allocationIdAConfirmar = $allocationId;
        $this->confirmando            = 'revertir';
    }

    public function confirmarReversion(): void
    {
        /*
         | Revertir una aplicacion devuelve saldo a la factura: el cliente
         | vuelve a deber lo que ya se le habia descontado. Es el acto mas
         | delicado de esta pantalla.
         */
        $this->exigirPermiso('update');

        $allocation = PaymentAllocation::where('payment_id', $this->payment->id)
            ->find($this->allocationIdAConfirmar);

        if (! $allocation) {
            $this->cancelar();

            return;
        }

        $factura = $allocation->invoice;
        $monto   = (float) $allocation->amount;

        $allocation->reverse();

        $this->payment->refresh()->load('allocations.invoice');
        $this->cancelar();

        session()->flash('exito', 'Se revirtieron $'.number_format($monto, 2).' aplicados a la factura '.$factura?->invoice_number.'.');
    }

    /* =====================================================================
     | CAMBIAR EL ESTADO DEL PAGO
     * ================================================================== */

    public function pedirCambioEstado(string $estado): void
    {
        $this->nuevoEstado = $estado;
        $this->confirmando = 'estado';
    }

    /**
     * Al confirmar, si el nuevo estado es uno de los que
     * PaymentStatus::reversesAllocations() marca como inválido, el
     * PaymentObserver deshace automáticamente todo lo que este pago
     * tuviera aplicado. No hay que llamar nada más desde aquí: es lo
     * mismo que ya usa Payment::reverseAllAllocations().
     */
    public function confirmarCambioEstado(): void
    {
        $this->exigirPermiso('update');

        $estado = PaymentStatus::tryFrom((string) $this->nuevoEstado);

        if (! $estado) {
            $this->cancelar();

            return;
        }

        if (trim($this->notaCambioEstado) !== '') {
            $sello = now()->format('d/m/Y H:i');
            $this->payment->notes = trim(
                ($this->payment->notes ? $this->payment->notes."\n" : '')
                .'['.$sello.'] Cambio a "'.$estado->label().'": '.trim($this->notaCambioEstado),
            );
        }

        $this->payment->status = $estado;
        $this->payment->save();

        $this->payment->refresh()->load('allocations.invoice');
        $this->cancelar();
        $this->notaCambioEstado = '';

        session()->flash('exito', 'El pago ahora está: '.$estado->label().'.');
    }

    public function cancelar(): void
    {
        $this->confirmando            = null;
        $this->allocationIdAConfirmar = null;
        $this->nuevoEstado            = null;
    }

    public function render()
    {
        return view('livewire.payments.show');
    }
}
