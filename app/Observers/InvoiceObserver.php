<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Auth\Access\AuthorizationException;

class InvoiceObserver
{
    /**
     * **Qué vigila:** la factura.
        **Qué hace:** impide modificar o borrar documentos fiscales ya cerrados.
     */
   /**
 * Protege la integridad de las facturas.
 *
 * Una factura es un documento fiscal: una vez pagada o anulada, no se
 * toca. Esto no es una preferencia de diseño, es un requisito legal.
 * El botón "editar" puede estar oculto en la pantalla, pero la
 * protección de verdad va acá, donde no depende de la interfaz.
 */

    /**
     * Antes de guardar una factura nueva: completar lo que falte.
     *
     * Sirve para cuando la factura se crea desde un comando o una
     * importación, donde no hay formulario que rellene estos campos.
     */
    public function creating(Invoice $invoice): void
    {
        // La fecha de emisión, si nadie la puso, es hoy.
        $invoice->issue_date ??= now()->toDateString();

        /* -----------------------------------------------------------
         | FECHA DE VENCIMIENTO
         |
         | Sale de los términos: "Net 30" son 30 días desde la emisión.
         | "Due on receipt" vence el mismo día.
         |
         | Se calcula acá y se GUARDA. No se calcula al mostrar: si
         | mañana cambian los términos por defecto, esta factura debe
         | seguir venciendo el día que se le dijo al cliente.
         * -------------------------------------------------------- */
        if (! $invoice->due_date) {
            $days = $this->daysFromTerms($invoice->terms);
            $invoice->due_date = \Carbon\Carbon::parse($invoice->issue_date)
                ->addDays($days)
                ->toDateString();
        }

        // Quién la emitió. Si viene de un comando programado no hay
        // usuario logueado, y queda en null a propósito.
        $invoice->created_by ??= auth()->id();
    }

    /**
     * Antes de MODIFICAR una factura existente.
     *
     * Acá está el candado del sistema.
     */
    public function updating(Invoice $invoice): void
    {
        /* -----------------------------------------------------------
         | ¿Estaba bloqueada ANTES de este cambio?
         |
         | getOriginal() devuelve el valor que tenía la columna cuando
         | se leyó de la base, antes de que nadie la tocara.
         |
         | Usamos el valor original y no el actual porque si alguien
         | está justamente CAMBIANDO el estado (por ejemplo, anulando
         | la factura), el estado nuevo ya sería 'void' y nos
         | bloquearíamos a nosotros mismos.
         * -------------------------------------------------------- */
        $originalStatus = InvoiceStatus::tryFrom(
            (string) $invoice->getRawOriginal('status'),
        );

        if (! $originalStatus?->isLocked()) {
            return;   // no estaba bloqueada: puede editarse libremente
        }

        /* -----------------------------------------------------------
         | Sí estaba bloqueada. Solo dejamos pasar los campos que NO
         | alteran el documento en sí.
         |
         | 'sent_at' y 'viewed_at' son trazas de envío: reenviar una
         | factura pagada al cliente es legítimo.
         |
         | 'notes' son notas internas: no salen impresas.
         |
         | Cualquier otra cosa —montos, líneas, cliente, fechas— se
         | rechaza.
         * -------------------------------------------------------- */
        $camposPermitidos = ['sent_at', 'viewed_at', 'notes', 'updated_at'];

        $camposTocados = array_keys($invoice->getDirty());

        $prohibidos = array_diff($camposTocados, $camposPermitidos);

        if (! empty($prohibidos)) {
            throw new AuthorizationException(
                'La factura '.$invoice->invoice_number.' está '.$originalStatus->label().' y no puede modificarse. '.'Para corregirla, anúlela y emita una nueva.',
            );
        }
    }

    /**
     * Antes de BORRAR: nunca.
     *
     * Las facturas no tienen SoftDeletes a propósito. Una factura
     * equivocada se ANULA (status void), lo cual deja rastro del
     * número consumido. Borrarla dejaría un hueco en la numeración,
     * que es exactamente lo que Hacienda busca en una auditoría.
     */
    public function deleting(Invoice $invoice): void
    {
        throw new AuthorizationException(
            'Las facturas no se borran. Use "Anular" para dejar constancia '
            .'de que el número '.$invoice->invoice_number.' fue emitido y cancelado.',
        );
    }

    /**
     * Traduce los términos comerciales a días.
     *
     * Está acá y no en la base de datos porque son cuatro valores
     * estándar del sector. Si el cliente pide términos personalizados,
     * esto se mueve a la tabla settings.
     */
    protected function daysFromTerms(?string $terms): int
    {
        return match (strtolower(trim((string) $terms))) {
            'net 15'          => 15,
            'net 30'          => 30,
            'net 45'          => 45,
            'net 60'          => 60,
            'due on receipt'  => 0,
            default           => 0,
        };
    }
}
