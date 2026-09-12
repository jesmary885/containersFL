<?php

namespace App\Livewire\Estimates;

use App\Enums\EstimateStatus;
use App\Models\Estimate;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\AuthorizesAccess;
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

    protected string $permisoBase = 'estimates';
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
        $this->exigirPermiso('view');

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
    /**
     * El envío de verdad, ya con el documento revisado.
     *
     * Acepta tanto Draft (guardado suelto, sin pasar por Procesar) como
     * Processed. markAsSent() del modelo no discrimina, así que basta con
     * no bloquearlo acá.
     */
    public function marcarEnviado()
    {
        $this->exigirPermiso('send');

        // Antes solo dejaba pasar Draft. Con el paso de revisión, el
        // camino normal llega acá como Processed y quedaba bloqueado
        // en silencio: el botón no hacía nada.
        if (! in_array($this->estimate->status, [EstimateStatus::Draft, EstimateStatus::Processed], true)) {
            return null;
        }

        $this->estimate->markAsSent();

        session()->flash('exito',
            'Presupuesto '.$this->estimate->estimate_number.' enviado.');

        /* -----------------------------------------------------------------
         | AL ENVIAR, AL LISTADO
         |
         | Enviar cierra la tarea. Dejar al usuario mirando el mismo
         | documento que acaba de mandar le da una pantalla sin nada que
         | hacer, y lo que quiere ver a continuación es el listado con el
         | presupuesto nuevo dentro.
         *
         | Los botones de respuesta del cliente se fueron de esta
         | pantalla por lo mismo: si el cliente acepta o rechaza no se
         | sabe en el mismo segundo del envío. Eso se registra días
         | después, desde el listado, cuando llama.
         * ---------------------------------------------------------- */
        return $this->redirect(route('comercial.presupuestos.index'), navigate: true);
    }

    /** El cliente dijo que sí. */
    public function marcarAceptado(): void
    {
        $this->exigirPermiso('update');

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
        $this->exigirPermiso('update');

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
        $this->exigirPermiso('update');

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
        /* -----------------------------------------------------------------
         | EL PERMISO ES DE FACTURAS, NO DE PRESUPUESTOS
         |
         | El boton esta en la pantalla del presupuesto, pero lo que hace
         | es EMITIR UNA FACTURA: consume un numero de la secuencia de
         | facturas y crea un documento que se cobra.
         |
         | Un vendedor con `estimates.*` completo y sin `invoices.create`
         | puede cotizar todo lo que quiera y no puede facturar. Es
         | exactamente el reparto que trae el RoleSeeder para el rol de
         | ventas, y sin esta linea se lo saltaba entero.
         * -------------------------------------------------------------- */
        $this->exigirPermiso('invoices.create');

        try {
            $factura = $this->estimate->convertToInvoice();

            $this->estimate->refresh();
            $this->confirmando = null;

            session()->flash('exito',
                'Se emitió la factura '.$factura->invoice_number.' a partir de este presupuesto. '
                .'Quedó en borrador: revísela antes de enviarla al cliente.');

            /* -------------------------------------------------------------
             | Llevar al usuario a la factura recién creada.
             |
             | Antes se quedaba en el presupuesto con un mensaje verde. Si
             | el mensaje pasaba desapercibido —y pasa— la sensación era
             | que el botón no había hecho nada.
             |
             | Y como el listado de facturas abre filtrado por "con saldo",
             | ir a buscarla ahí tampoco era evidente. Mejor no obligar a
             | buscarla: mostrarla.
             * ---------------------------------------------------------- */
            return redirect()->route('finanzas.facturacion.show', $factura);

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
        // Duplicar CREA un presupuesto nuevo, no edita este.
        $this->exigirPermiso('create');

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
