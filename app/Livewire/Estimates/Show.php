<?php

namespace App\Livewire\Estimates;

use App\Enums\EstimateStatus;
use App\Models\Estimate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL PRESUPUESTO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * La pantalla donde se ve el presupuesto terminado, tal como lo va a
 * recibir el cliente, y desde donde se mueve por sus estados.
 *
 * ── EL RECORRIDO NORMAL ──
 *
 *   Borrador  →  Enviada  →  Aceptada  →  Convertida a factura
 *                   │
 *                   └──→  Rechazada  /  Vencida
 *
 * ── DÓNDE VIVEN LAS REGLAS ──
 *
 * En el enum EstimateStatus y en el modelo, no aquí. Esta pantalla solo
 * pregunta: canConvert(), isClosed(), isEditable().
 *
 * Es a propósito. Una regla escrita en la pantalla se salta llamando al
 * modelo desde otro sitio; una regla escrita en el modelo no se salta
 * nunca. Los botones que se ven acá son comodidad; la protección está
 * más abajo.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Estimate $estimate;

    /** Qué acción está esperando confirmación: 'convertir' | 'rechazar' | null */
    public ?string $confirmando = null;

    /**
     * El presupuesto llega ya resuelto por la ruta.
     *
     * Y llega filtrado por compañía: si alguien escribe a mano el id de
     * un presupuesto de la otra empresa, Laravel responde 404 antes de
     * llegar hasta aquí.
     */
    public function mount(Estimate $estimate): void
    {
        $this->estimate = $estimate->load([
            'items.product',
            'items.container.size',
            'customer',
            'salesperson',
            'invoice',
        ]);
    }

    /* =====================================================================
     | CAMBIOS DE ESTADO
     * ================================================================== */

    /**
     * Marcarlo como enviado.
     *
     * Todavía no manda el correo: solo deja constancia de que salió. El
     * envío real lo hará el módulo de notificaciones, y cuando exista
     * llamará a este mismo método.
     */
    public function marcarEnviado(): void
    {
        if ($this->estimate->status !== EstimateStatus::Draft) {
            return;
        }

        $this->estimate->markAsSent();
        $this->estimate->refresh();

        session()->flash('exito', 'Presupuesto marcado como enviado.');
    }

    /** El cliente dijo que sí. */
    public function marcarAceptado(): void
    {
        if ($this->estimate->status !== EstimateStatus::Sent) {
            return;
        }

        $this->estimate->update(['status' => EstimateStatus::Accepted]);
        $this->estimate->refresh();

        session()->flash('exito', 'Presupuesto aceptado. Ya se puede convertir en factura.');
    }

    /** El cliente dijo que no. */
    public function marcarRechazado(): void
    {
        if ($this->estimate->status->isClosed()) {
            return;
        }

        $this->estimate->update(['status' => EstimateStatus::Rejected]);
        $this->estimate->refresh();

        $this->confirmando = null;

        session()->flash('exito', 'Presupuesto marcado como rechazado.');
    }

    /**
     * Reabrir uno rechazado o vencido.
     *
     * Pasa seguido: el cliente vuelve a los dos meses. Se devuelve a
     * borrador para poder actualizarle los precios antes de reenviarlo,
     * porque el precio de venta varía por temporada (RB-029).
     */
    public function reabrir(): void
    {
        if (! $this->estimate->status->is(EstimateStatus::Rejected, EstimateStatus::Expired)) {
            return;
        }

        $this->estimate->update(['status' => EstimateStatus::Draft]);
        $this->estimate->refresh();

        session()->flash('exito', 'Presupuesto reabierto como borrador. Revise los precios antes de reenviarlo.');
    }

    /* =====================================================================
     | LA CONVERSIÓN A FACTURA (RB-033)
     * ================================================================== */

    public function confirmar(string $accion): void
    {
        $this->confirmando = $accion;
    }

    public function cancelarConfirmacion(): void
    {
        $this->confirmando = null;
    }

    /**
     * Convertir el presupuesto en factura.
     *
     * ── LO QUE PASA POR DENTRO ──
     *
     * Todo el trabajo está en Estimate::convertToInvoice(), no aquí. Ese
     * método toma el siguiente número de la secuencia de FACTURAS —no de
     * presupuestos, son numeraciones distintas—, copia las líneas con su
     * agrupación de impresión, busca el certificado de exención vigente
     * si el cliente está exento, y marca el presupuesto como convertido.
     *
     * Todo dentro de una transacción: o pasa entero, o no pasa nada.
     *
     * ── POR QUÉ EL try/catch ──
     *
     * El modelo lanza avisos con texto en español cuando algo no cuadra:
     * que ya se convirtió antes, que está en borrador, que no tiene
     * líneas. Aquí se atrapan y se le muestran al usuario tal cual, en vez
     * de una pantalla de error de Laravel que no le dice nada.
     */
    public function convertirEnFactura()
    {
        try {
            $factura = $this->estimate->convertToInvoice();

            $this->estimate->refresh();
            $this->confirmando = null;

            session()->flash('exito',
                'Se emitió la factura '.$factura->invoice_number.' a partir de este presupuesto. '
                .'Quedó en borrador: revísela antes de enviarla al cliente.');

        } catch (\Throwable $e) {
            $this->confirmando = null;

            session()->flash('error', $e->getMessage());
        }

        return null;
    }

    /* =====================================================================
     | DUPLICAR
     * ================================================================== */

    public function duplicar()
    {
        try {
            $copia = $this->estimate->duplicate();

            session()->flash('exito', 'Se creó el presupuesto '.$copia->estimate_number.'.');

            return redirect()->route('comercial.presupuestos.edit', $copia);
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo duplicar: '.$e->getMessage());

            return null;
        }
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        return view('livewire.estimates.show', [
            /*
             | Las líneas ya agrupadas, tal como las va a ver el cliente
             | (RB-007). El desglose interno viaja dentro de cada renglón,
             | en la clave 'detalle', para poder mostrarlo al usuario del
             | sistema sin que salga impreso.
             */
            'renglones' => $this->estimate->printableLines(),
        ]);
    }
}
