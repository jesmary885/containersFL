<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\InvoiceCalculator;
use App\Support\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * QUÉ VIGILA: la factura.
 * QUÉ HACE:   la numera al nacer e impide tocarla una vez cerrada.
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Una factura es un documento fiscal: una vez pagada o anulada, no se
 * toca. Esto no es una preferencia de diseño, es un requisito legal.
 *
 * El botón "editar" puede estar oculto en la pantalla, pero la protección
 * de verdad va acá, donde no depende de la interfaz. Una regla escrita
 * solo en la pantalla se salta llamando al modelo desde otro lado.
 *
 * ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──
 *
 *   1. Ahora asigna el número de factura. Antes solo lo hacía
 *      Estimate::convertToInvoice(), así que una factura creada
 *      directamente desde la pantalla nacía sin número.
 *
 *   2. Deja pasar la anulación. Antes el candado de updating() también
 *      bloqueaba el propio acto de anular una factura pagada: nos
 *      estábamos bloqueando a nosotros mismos.
 *
 *   3. Los términos de pago salen de la configuración, no solo del
 *      valor que traiga la factura.
 */
class InvoiceObserver
{
    /* =====================================================================
     | ANTES DE CREAR
     * ================================================================== */

    public function creating(Invoice $invoice): void
    {
        /* -----------------------------------------------------------------
         | LA COMPAÑÍA
         |
         | ── OJO CON EL ORDEN, QUE TIENE TRAMPA ──
         |
         | El trait BelongsToCompany también tiene un "creating" que
         | rellena company_id con la compañía activa. Pero los dos
         | vigilantes se registran en momentos distintos y el de este
         | archivo corre PRIMERO, cuando company_id todavía está vacío.
         |
         | Como aquí hace falta la compañía para pedirle el número, no se
         | puede confiar en que ya esté puesta: se busca por cuenta propia.
         * -------------------------------------------------------------- */
        $empresa = $invoice->company_id
            ? Company::find($invoice->company_id)
            : app(CompanyContext::class)->get();

        if (! $empresa) {
            throw new \RuntimeException(
                'No se puede emitir una factura sin saber de qué empresa es. '
                .'Vuelva a iniciar sesión y seleccione una empresa.',
            );
        }

        $invoice->company_id ??= $empresa->id;

        /* -----------------------------------------------------------------
         | EL NÚMERO
         |
         | Sale de la secuencia 'invoice' de ESA compañía. FLCHR y RS
         | Transport llevan contadores separados y no se cruzan.
         |
         | nextNumber() bloquea la fila del contador mientras la lee, así
         | que si dos personas emiten factura en el mismo segundo, la
         | segunda espera. Sin eso saldrían dos facturas con el mismo
         | número, que es de los errores más caros de arreglar después.
         |
         | ── POR QUÉ EL "if" ──
         |
         | Estimate::convertToInvoice() ya trae el número puesto, y una
         | importación del Excel viejo traería los suyos. En esos casos se
         | respeta lo que venga y no se gasta un número de más.
         * -------------------------------------------------------------- */
        if (blank($invoice->invoice_number)) {
            $invoice->invoice_number = $empresa->nextNumber('invoice');
        }

        // La fecha de emisión, si nadie la puso, es hoy.
        $invoice->issue_date ??= now()->toDateString();

        /* -----------------------------------------------------------------
         | LOS TÉRMINOS Y LA FECHA DE VENCIMIENTO
         |
         | El vencimiento sale de los términos: "Net 30" son 30 días desde
         | la emisión; "Due on receipt" vence el mismo día.
         |
         | Se calcula acá y se GUARDA. No se calcula al mostrarlo: si
         | mañana cambian los términos por defecto, esta factura tiene que
         | seguir venciendo el día que se le dijo al cliente. Un dato
         | calculado al vuelo cambiaría solo, y el cliente vería una fecha
         | distinta a la del papel que recibió.
         * -------------------------------------------------------------- */
        if (blank($invoice->terms)) {
            $invoice->terms = app(InvoiceCalculator::class)->defaultTerms($empresa);
        }

        if (! $invoice->due_date) {
            $dias = $this->daysFromTerms($invoice->terms);

            $invoice->due_date = \Carbon\Carbon::parse($invoice->issue_date)
                ->addDays($dias)
                ->toDateString();
        }

        // Quién la emitió. Si viene de un comando programado no hay
        // usuario logueado, y queda en null a propósito.
        $invoice->created_by ??= auth()->id();

        $invoice->status ??= InvoiceStatus::Draft;
    }

    /* =====================================================================
     | ANTES DE MODIFICAR — el candado del sistema
     * ================================================================== */

    public function updating(Invoice $invoice): void
    {
        /* -----------------------------------------------------------------
         | ¿Estaba bloqueada ANTES de este cambio?
         |
         | getRawOriginal() devuelve lo que hay literalmente en la base: el
         | texto 'draft', 'paid', 'void'.
         |
         | Se usa el valor ORIGINAL y no el actual porque si alguien está
         | justamente cambiando el estado —por ejemplo anulando— el estado
         | nuevo ya sería 'void' y nos bloquearíamos a nosotros mismos.
         |
         | Y se usa getRawOriginal() y no getOriginal() porque la columna
         | está casteada al enum: getOriginal() devolvería el objeto ya
         | armado, y tryFrom() —que solo acepta texto— lanzaría un error
         | fatal.
         * -------------------------------------------------------------- */
        $estadoAnterior = InvoiceStatus::tryFrom(
            (string) $invoice->getRawOriginal('status'),
        );

        if (! $estadoAnterior?->isLocked()) {
            return;   // no estaba bloqueada: puede editarse libremente
        }

        /* -----------------------------------------------------------------
         | LA EXCEPCIÓN: LA ANULACIÓN
         |
         | Si lo que se está haciendo es anular la factura, se deja pasar
         | aunque estuviera bloqueada. Es el único cambio de estado
         | permitido sobre un documento cerrado.
         |
         | Fíjate que se comprueban DOS cosas: que el destino sea 'void', y
         | que solo se hayan tocado los campos propios de la anulación. Sin
         | la segunda, alguien podría anular y de paso cambiarle el monto a
         | la factura en el mismo movimiento.
         |
         | (El modelo, además, no deja anular una factura con pagos
         | aplicados. Esto es la segunda barrera, no la única.)
         * -------------------------------------------------------------- */
        $camposDeAnulacion = ['status', 'voided_at', 'void_reason', 'balance_due', 'updated_at'];

        $vaAAnularse = $invoice->status === InvoiceStatus::Void
            && empty(array_diff(array_keys($invoice->getDirty()), $camposDeAnulacion));

        if ($vaAAnularse) {
            return;
        }

        /* -----------------------------------------------------------------
         | Fuera de eso, solo pasan los campos que NO alteran el documento.
         |
         | 'sent_at' y 'viewed_at' son trazas de envío: reenviarle al
         | cliente una factura ya pagada es legítimo, pide copias.
         |
         | 'notes' son notas internas: no salen impresas.
         |
         | Cualquier otra cosa —montos, líneas, cliente, fechas— se
         | rechaza.
         * -------------------------------------------------------------- */
        $camposPermitidos = ['sent_at', 'viewed_at', 'notes', 'updated_at'];

        $prohibidos = array_diff(array_keys($invoice->getDirty()), $camposPermitidos);

        if (! empty($prohibidos)) {
            throw new AuthorizationException(
                'La factura '.$invoice->invoice_number.' está '
                .strtolower($estadoAnterior->label()).' y no puede modificarse. '
                .'Para corregirla, anúlela y emita una nueva.',
            );
        }
    }

    /* =====================================================================
     | ANTES DE BORRAR — nunca
     * ================================================================== */

    /**
     * Las facturas no tienen SoftDeletes a propósito.
     *
     * Una factura equivocada se ANULA, lo cual deja rastro del número
     * consumido. Borrarla dejaría un hueco en la numeración, que es
     * exactamente lo que se busca en una auditoría.
     */
    public function deleting(Invoice $invoice): void
    {
        throw new AuthorizationException(
            'Las facturas no se borran. Use "Anular" para dejar constancia '
            .'de que el número '.$invoice->invoice_number.' fue emitido y cancelado.',
        );
    }

    /* =====================================================================
     | AYUDANTE
     * ================================================================== */

    /**
     * Traduce los términos comerciales a días.
     *
     * Está acá y no en la base de datos porque son cuatro valores
     * estándar del sector. Si el cliente pide términos personalizados
     * —"Net 45 desde la entrega", por ejemplo— esto se mueve a settings.
     */
    protected function daysFromTerms(?string $terms): int
    {
        return match (strtolower(trim((string) $terms))) {
            'net 15'         => 15,
            'net 30'         => 30,
            'net 45'         => 45,
            'net 60'         => 60,
            'due on receipt' => 0,
            default          => 0,
        };
    }
}
