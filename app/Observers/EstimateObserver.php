<?php

namespace App\Observers;

use App\Enums\EstimateStatus;
use App\Models\Company;
use App\Models\Estimate;
use App\Services\InvoiceCalculator;
use App\Support\CompanyContext;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * QUÉ VIGILA: los presupuestos.
 * QUÉ HACE:   les pone número y fechas al crearlos, y protege los cerrados.
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Un observer es un vigilante que se despierta solo cuando algo pasa con
 * un modelo. Este se despierta en tres momentos: justo antes de crear un
 * presupuesto, justo antes de modificarlo, y justo antes de borrarlo.
 *
 * ── POR QUÉ AQUÍ Y NO EN LA PANTALLA ──
 *
 * Porque un presupuesto no siempre nace de la pantalla. Puede nacer de
 * duplicar otro, de una importación del Excel viejo, o de un comando.
 * Si la numeración viviera en el formulario, esos tres caminos crearían
 * presupuestos sin número.
 *
 * Aquí pasa por el mismo sitio venga de donde venga.
 */
class EstimateObserver
{
    /* =====================================================================
     | ANTES DE CREAR
     * ================================================================== */

    public function creating(Estimate $estimate): void
    {
        /* -----------------------------------------------------------------
         | LA COMPAÑÍA
         |
         | ── OJO CON EL ORDEN, QUE TIENE TRAMPA ──
         |
         | El trait BelongsToCompany también tiene un "creating" que rellena
         | company_id con la compañía activa. Pero los dos vigilantes se
         | registran en momentos distintos y el de este archivo corre
         | PRIMERO, cuando company_id todavía está vacío.
         |
         | Como este método necesita la compañía para pedirle el número,
         | no puede confiar en que ya esté puesta. Por eso la busca él
         | mismo: primero mira si el formulario ya la trajo, y si no, la
         | saca de la caja de la compañía activa.
         * -------------------------------------------------------------- */
        $empresa = $estimate->company_id
            ? Company::find($estimate->company_id)
            : app(CompanyContext::class)->get();

        if (! $empresa) {
            throw new \RuntimeException(
                'No se puede crear un presupuesto sin saber de qué empresa es. '
                .'Vuelva a iniciar sesión y seleccione una empresa.',
            );
        }

        // Si el trait todavía no la puso, se pone aquí.
        $estimate->company_id ??= $empresa->id;

        /* -----------------------------------------------------------------
         | EL NÚMERO
         |
         | Sale de la secuencia 'estimate' de ESA compañía. FLCHR y RS
         | Transport llevan contadores separados: las dos pueden tener su
         | EST-0001 sin pisarse.
         |
         | nextNumber() bloquea la fila del contador mientras la lee, así
         | que si dos vendedores guardan al mismo tiempo, el segundo espera
         | y no salen dos presupuestos con el mismo número.
         |
         | El "if" permite que una importación traiga sus propios números,
         | para no renumerar los presupuestos históricos del Excel.
         * -------------------------------------------------------------- */
        if (blank($estimate->estimate_number)) {
            $estimate->estimate_number = $empresa->nextNumber('estimate');
        }

        /* -----------------------------------------------------------------
         | LAS FECHAS
         |
         | La de emisión, si nadie la puso, es hoy.
         * -------------------------------------------------------------- */
        $estimate->issue_date ??= now()->toDateString();

        /* -----------------------------------------------------------------
         | HASTA CUÁNDO VALE (RB-041)
         |
         | Se calcula acá y se GUARDA. No se calcula al mostrarlo.
         |
         | La diferencia importa: si mañana el cliente cambia la vigencia
         | de 3 a 7 días, los presupuestos de hoy tienen que seguir
         | venciendo el día que se le dijo al cliente. Un dato calculado al
         | vuelo cambiaría solo, y el cliente vería una fecha distinta a la
         | del papel que recibió.
         * -------------------------------------------------------------- */
        if (blank($estimate->valid_until)) {
            $dias = app(InvoiceCalculator::class)->defaultEstimateValidDays($empresa);

            $estimate->valid_until = \Carbon\Carbon::parse($estimate->issue_date)
                ->addDays($dias)
                ->toDateString();
        }

        /* -----------------------------------------------------------------
         | LOS TÉRMINOS DE PAGO
         * -------------------------------------------------------------- */
        if (blank($estimate->terms)) {
            $estimate->terms = app(InvoiceCalculator::class)->defaultTerms($empresa);
        }

        /* -----------------------------------------------------------------
         | QUIÉN LO HIZO
         |
         | created_by = quien tecleó.
         | salesperson_id = de quién es la comisión (RB-030).
         |
         | Normalmente son la misma persona, así que se precargan iguales.
         | En la pantalla el vendedor se puede cambiar: pasa cuando la
         | secretaria captura una venta que trajo otro.
         |
         | Si viene de un comando programado no hay nadie logueado, y los
         | dos quedan vacíos a propósito.
         * -------------------------------------------------------------- */
        $estimate->created_by     ??= auth()->id();
        $estimate->salesperson_id ??= auth()->id();

        $estimate->status ??= EstimateStatus::Draft;
    }

    /* =====================================================================
     | ANTES DE MODIFICAR
     * ================================================================== */

    /**
     * Un presupuesto ya convertido en factura no se toca.
     *
     * ── POR QUÉ ──
     *
     * Si se pudiera editar, quedaría un presupuesto que dice $2,750 y una
     * factura emitida que dice $2,400, sin nada que explique la
     * diferencia. Y el cliente tiene el presupuesto viejo en su correo.
     *
     * ── QUÉ SÍ SE DEJA PASAR ──
     *
     * Solo el campo que registra en qué factura se convirtió, porque ese
     * cambio es precisamente el de la conversión: si lo bloqueáramos, nos
     * estaríamos bloqueando a nosotros mismos.
     *
     * getRawOriginal() y no getOriginal(): la columna 'status' está
     * casteada al enum, así que getOriginal() devolvería el objeto ya
     * armado y tryFrom() —que solo acepta texto— lanzaría un error fatal.
     * getRawOriginal() devuelve lo que hay literalmente en la base: el
     * texto 'draft', 'sent', 'converted'.
     */
    public function updating(Estimate $estimate): void
    {
        $estadoAnterior = EstimateStatus::tryFrom(
            (string) $estimate->getRawOriginal('status'),
        );

        if ($estadoAnterior !== EstimateStatus::Converted) {
            return;   // no estaba convertido: se puede editar libremente
        }

        $permitidos = ['status', 'converted_invoice_id', 'updated_at', 'notes'];

        $tocados    = array_keys($estimate->getDirty());
        $prohibidos = array_diff($tocados, $permitidos);

        if (! empty($prohibidos)) {
            throw new \RuntimeException(
                'El presupuesto '.$estimate->estimate_number.' ya se convirtió en '
                .'factura y no puede modificarse. Si hay que corregir algo, se '
                .'corrige en la factura.',
            );
        }
    }

    /* =====================================================================
     | ANTES DE BORRAR
     * ================================================================== */

    /**
     * Los presupuestos SÍ se borran, a diferencia de las facturas. Pero
     * no cualquiera: solo los borradores.
     *
     * Uno que ya se le envió al cliente es parte de la conversación con
     * él. Si el cliente pregunta "¿y el precio que me pasaste la semana
     * pasada?", tiene que estar. Para esos existe el estado "Rechazado".
     */
    public function deleting(Estimate $estimate): void
    {
        if ($estimate->status !== EstimateStatus::Draft) {
            throw new \RuntimeException(
                'Solo se pueden borrar los presupuestos en borrador. El '
                .$estimate->estimate_number.' está en estado "'
                .$estimate->status->label().'": márquelo como rechazado en vez '
                .'de borrarlo, así queda el historial.',
            );
        }
    }
}
