<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Enums\CommissionMode;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LA FACTURA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * SIN SoftDeletes a propósito: una factura no se borra, se anula con
 * status void. Es un documento fiscal.
 *
 * ── LA DIFERENCIA CON EL PRESUPUESTO, EN UNA FRASE ──
 *
 * Un presupuesto es una oferta: se puede rehacer, borrar y volver a
 * mandar. Una factura es un hecho: se emitió, consumió un número, y ese
 * número tiene que poder explicarse siempre.
 *
 * Por eso el presupuesto tiene un botón "Eliminar" y la factura tiene uno
 * de "Anular". Anular deja la factura ahí, marcada, con su motivo escrito
 * y su número gastado. Un hueco en la numeración es exactamente lo que
 * busca una auditoría del estado.
 *
 * ── QUÉ SE AGREGÓ EN ESTA VERSIÓN ──
 *
 *   scopeSearch()      buscar por número, cliente o texto de una línea
 *   scopeStatusIs()    filtro del listado, tratando "vencida" aparte
 *   isEditable()       la pregunta que hace la pantalla
 *   isOverdue()        y los días de atraso
 *   markAsSent()       dejar constancia del envío
 *   void()             anular con motivo obligatorio
 *   attachments()      los adjuntos que viajan con la factura (RB-034)
 *   printableLines()   ahora conserva el desglose interno, igual que el
 *                      presupuesto, para poder mostrarlo en pantalla
 * ═══════════════════════════════════════════════════════════════════════════
 */
class Invoice extends Model
{
    use HasFactory, \App\Models\Concerns\HasDocumentAddresses, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type'                    => InvoiceType::class,
            'status'                  => InvoiceStatus::class,

            /*
             | LA COMISION PACTADA, CONGELADA EN EL DOCUMENTO
             |
             | Igual que las direcciones (RB-058): si mañana cambia el
             | porcentaje del vendedor, esta factura conserva el que se
             | acordo el dia que se emitio.
             |
             | commission_mode en null significa "esta factura no genera
             | comision". Una renta que se factura sola cada mes no la
             | cierra nadie.
             */
            'commission_mode'         => CommissionMode::class,
            'commission_percent'      => 'decimal:2',
            'commission_amount'       => 'decimal:2',
            'expected_payment_method' => PaymentMethod::class,
            'bill_to'                 => 'array',
            'ship_to'                 => 'array',
            'tax_exempt'              => 'boolean',
            'issue_date'              => 'date',
            'due_date'                => 'date',
            'service_period_start'    => 'date',
            'service_period_end'      => 'date',
            'subtotal'                => 'decimal:2',
            'discount_amount'         => 'decimal:2',
            'taxable_base'            => 'decimal:2',
            'tax_rate'                => 'decimal:2',
            'tax_amount'              => 'decimal:2',
            'deposit_applied'         => 'decimal:2',
            'credit_card_fee_percent' => 'decimal:2',
            'credit_card_fee'         => 'decimal:2',
            'total'                   => 'decimal:2',
            'amount_paid'             => 'decimal:2',
            'balance_due'             => 'decimal:2',
            'sent_at'                 => 'datetime',
            'viewed_at'               => 'datetime',
            'paid_at'                 => 'datetime',
            'voided_at'               => 'datetime',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /**
     * Quien CERRO la venta. No es lo mismo que created_by.
     *
     * created_by es quien tecleo el documento; puede ser la
     * administradora. salesperson_id es quien cerro el negocio y cobra
     * la comision. Denisse registra, Miguelito vendio.
     */
    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    public function customer()     { return $this->belongsTo(Customer::class); }
    public function items()        { return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id'); }
    public function sale()         { return $this->belongsTo(Sale::class); }
    public function rental()       { return $this->belongsTo(Rental::class); }
    public function rentalPeriod() { return $this->belongsTo(RentalPeriod::class); }
    public function estimate()     { return $this->belongsTo(Estimate::class); }
    public function documents()    { return $this->morphMany(Document::class, 'documentable'); }
    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * Los adjuntos marcados para viajar CON la factura (RB-034).
     *
     * No todos los documentos colgados de una factura se le mandan al
     * cliente. La autorización de tarjeta firmada, por ejemplo, es
     * interna: lleva los datos de la tarjeta y no sale de la oficina.
     *
     * La columna attach_to_invoice es la que separa unos de otros.
     */
    public function attachments()
    {
        return $this->morphMany(Document::class, 'documentable')
            ->where('attach_to_invoice', true);
    }

    /** Prueba guardada de por qué no se cobró impuesto (RB-015). */
    public function exemptionCertificate()
    {
        return $this->belongsTo(TaxExemptionCertificate::class, 'tax_exemption_certificate_id');
    }

    /** Viajes cubiertos por esta factura intercompañía (RB-003). */
    public function trips()
    {
        return $this->hasMany(Trip::class, 'intercompany_invoice_id');
    }

    /** Un pago puede repartirse entre varias facturas; el pivot lleva el monto. */
    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot('amount', 'allocated_at')
            ->withTimestamps();
    }

    /* =====================================================================
     | LECTURA — filtros del listado
     * ================================================================== */

    /**
     * Busca por número, por cliente o por el texto de las líneas.
     *
     * Lo de buscar dentro de las líneas no es capricho: es normal que
     * llamen preguntando por "la factura del 40 alto de Homestead" y
     * nadie tenga el número a mano.
     */
    public function scopeSearch(Builder $q, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $q;
        }

        $t = '%'.trim($termino).'%';

        return $q->where(function (Builder $q) use ($t) {
            $q->where('invoice_number', 'like', $t)
              ->orWhereHas('customer', fn (Builder $c) => $c
                  ->where('display_name', 'like', $t)
                  ->orWhere('company_name', 'like', $t)
                  ->orWhere('customer_number', 'like', $t))
              ->orWhereHas('items', fn (Builder $i) => $i
                  ->where('description', 'like', $t));
        });
    }

    /**
     * El filtro de estado del listado.
     *
     * ── POR QUÉ NO ES UN where() NORMAL ──
     *
     * "Vencida" es un estado raro: en la base casi nunca aparece escrito.
     * Una factura que se envió el día 1 con Net 30 sigue diciendo 'sent'
     * el día 45, porque nadie ha pasado a cambiarle el estado.
     *
     * O sea: vencida no es algo que ESTÁ escrito, es algo que se DEDUCE
     * de la fecha y el saldo. Este scope lo trata aparte para que el
     * filtro devuelva lo que el usuario espera y no una lista vacía.
     */
    public function scopeStatusIs(Builder $q, ?string $estado): Builder
    {
        if (blank($estado)) {
            return $q;
        }

        if ($estado === InvoiceStatus::Overdue->value) {
            return $q->overdue();
        }

        return $q->where('status', $estado);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereNotIn('status', [
                InvoiceStatus::Paid->value,
                InvoiceStatus::Void->value,
            ])
            ->whereDate('due_date', '<', now())
            ->where('balance_due', '>', 0);
    }

    public function scopeUnpaid(Builder $q): Builder
    {
        return $q->whereNotIn('status', [
                InvoiceStatus::Paid->value,
                InvoiceStatus::Void->value,
            ])
            ->where('balance_due', '>', 0);
    }

    public function scopeForPeriod(Builder $q, $from, $to): Builder
    {
        return $q->whereBetween('issue_date', [$from, $to]);
    }

    /** Base del reporte de impuesto a pagar al estado. */
    public function scopeTaxCollected(Builder $q, $from, $to): Builder
    {
        return $q->forPeriod($from, $to)
            ->where('status', '!=', InvoiceStatus::Void->value)
            ->where('tax_amount', '>', 0);
    }

    /* =====================================================================
     | LECTURA — preguntas que hace la pantalla
     * ================================================================== */

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /**
     * ¿Todavía se puede editar?
     *
     * Bloqueadas: las pagadas y las anuladas.
     *
     * ── UNA ACLARACIÓN QUE VALE LA PENA ──
     *
     * Una factura ENVIADA sí se deja editar. Puede sonar mal, pero es lo
     * correcto en la práctica: se manda la factura, el cliente llama a
     * los diez minutos diciendo que el delivery era a otra dirección, y
     * hay que corregirla antes de que pague.
     *
     * La pantalla avisa con un letrero cuando se está editando algo ya
     * enviado, para que quien lo haga sepa que el cliente tiene una copia
     * distinta en su correo. Pero no lo impide.
     *
     * En cuanto entra el primer dólar, se acabó: ahí ya hay un pago
     * contra un monto, y cambiar el monto descuadraría la contabilidad.
     */
    public function isEditable(): bool
    {
        return ! $this->status->isLocked() && (float) $this->amount_paid <= 0;
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->endOfDay()->isPast()
            && (float) $this->balance_due > 0
            && ! $this->status->isLocked();
    }

    /** Días de atraso. Cero si todavía no venció. */
    public function getDaysOverdueAttribute(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_date->endOfDay()->diffInDays(now());
    }

    /**
     * El estado que se MUESTRA, que no siempre es el que está guardado.
     *
     * Si la fecha ya pasó y queda saldo, se pinta "Vencida" aunque en la
     * base siga diciendo "Enviada". Así el listado dice la verdad sin
     * necesidad de un proceso que ande cambiando estados de madrugada.
     *
     * El estado guardado no se toca: cuando el cliente pague, la factura
     * pasará de 'sent' a 'paid' sin haber pasado nunca por 'overdue', y
     * eso está bien. "Vencida" es una circunstancia, no una etapa.
     */
    public function getDisplayStatusAttribute(): InvoiceStatus
    {
        return $this->isOverdue() ? InvoiceStatus::Overdue : $this->status;
    }

    /**
     * Las líneas tal como se le imprimen al cliente (RB-007).
     *
     * Las que comparten 'bundle_key' se suman en un renglón y se muestran
     * con el texto de 'bundle_description'. El desglose queda guardado y
     * viaja en la clave 'detalle', para poder verlo en pantalla sin que
     * salga impreso.
     *
     * Ejemplo: contenedor 2,400 + delivery 350 se imprime como una sola
     * línea de 2,750, pero por dentro solo 2,400 paga tax.
     */
    public function printableLines(): Collection
    {
        return $this->items
            ->groupBy(fn (InvoiceItem $i) => $i->bundle_key ?: 'line-'.$i->id)
            ->map(function (Collection $group) {
                $first    = $group->first();
                $isBundle = $group->count() > 1;

                return (object) [
                    'description' => $isBundle
                        ? ($first->bundle_description ?: $first->description)
                        : $first->description,
                    'quantity'     => $first->quantity,
                    'unit_price'   => $isBundle ? $group->sum('amount') : $first->unit_price,
                    'amount'       => $group->sum('amount'),
                    'container'    => $first->container,
                    'service_date' => $first->service_date,
                    'detalle'      => $isBundle ? $group : null,
                ];
            })
            ->values();
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Única puerta de entrada al cálculo. La aritmética vive en
     * InvoiceCalculator y no se copia en Livewire ni en Blade.
     *
     * saveQuietly() evita disparar los observers otra vez y entrar en bucle.
     */
    public function recalculate(bool $save = true): static
    {
        app(\App\Services\InvoiceCalculator::class)->apply($this);

        if ($save) {
            $this->saveQuietly();
        }

        return $this;
    }

    /**
     * Marca la factura como enviada al cliente.
     *
     * Todavía no manda el correo: solo deja constancia. El envío real lo
     * hará el módulo de notificaciones, y cuando exista llamará a este
     * mismo método.
     *
     * Se permite reenviar una factura ya enviada —o incluso una pagada—
     * porque el cliente pide copias, y cada envío actualiza la fecha.
     */
    public function markAsSent(): static
    {
        if ($this->status === InvoiceStatus::Void) {
            throw new \RuntimeException(
                'La factura '.$this->invoice_number.' está anulada y no debe enviarse.',
            );
        }

        $this->sent_at = now();

        if ($this->status === InvoiceStatus::Draft) {
            $this->status = InvoiceStatus::Sent;
        }

        $this->save();

        /* -----------------------------------------------------------------
         | LA COMISION DEL VENDEDOR
         |
         | Al EMITIR, no al cobrar. El vendedor pregunta todos los dias
         | cuanto va ganando, y esa respuesta no puede depender de que el
         | cliente haya pagado.
         |
         | Nace en 'pending'. Pagarsela es otro acto, con sus abonos
         | parciales, que ya resuelve commission_payments.
         |
         | Es idempotente: reenviar una factura no duplica la deuda con el
         | vendedor. Y si la comision ya tiene abonos, no la recalcula —
         | deja una nota y que lo decida una persona.
         * -------------------------------------------------------------- */
        app(\App\Services\CommissionResolver::class)->syncFromInvoice($this);

        return $this;
    }

    /**
     * ═════════════════════════════════════════════════════════════════
     * ANULAR
     * ═════════════════════════════════════════════════════════════════
     *
     * Lo más parecido a borrar que existe en un documento fiscal.
     *
     * La factura sigue ahí, con su número, sus líneas y sus montos. Lo
     * único que cambia es que queda marcada como anulada, con la fecha y
     * el motivo escritos.
     *
     * ── POR QUÉ EL MOTIVO ES OBLIGATORIO ──
     *
     * Porque dentro de dos años, cuando alguien pregunte por qué el
     * número 1358 no cobró nada, la respuesta tiene que estar en la
     * pantalla y no en la memoria de quien la anuló.
     *
     * "Error en la dirección de entrega, se reemplazó por la 1361" es
     * una respuesta. Un campo vacío no lo es.
     *
     * ── LAS DOS PROTECCIONES ──
     */
    public function void(string $motivo): static
    {
        /* -----------------------------------------------------------------
         | 1 · ¿Ya estaba anulada?
         |
         | Dos clics seguidos en el botón no deben pisar la fecha ni el
         | motivo original.
         * -------------------------------------------------------------- */
        if ($this->status === InvoiceStatus::Void) {
            throw new \RuntimeException(
                'La factura '.$this->invoice_number.' ya estaba anulada desde el '
                .$this->voided_at?->format('d/m/Y').'.',
            );
        }

        /* -----------------------------------------------------------------
         | 2 · ¿Tiene pagos aplicados?
         |
         | Anular una factura con dinero cobrado dejaría ese dinero sin
         | ninguna factura a la que corresponder: aparecería en el banco y
         | en ningún documento. Es un descuadre que el contador va a
         | encontrar y nadie va a saber explicar.
         |
         | El orden correcto es: primero se deshace la aplicación del
         | pago, después se anula la factura. Esa pantalla llega en el
         | paso siguiente.
         * -------------------------------------------------------------- */
        if ((float) $this->amount_paid > 0) {
            throw new \RuntimeException(
                'La factura '.$this->invoice_number.' tiene $'
                .number_format((float) $this->amount_paid, 2).' aplicados. '
                .'Primero hay que revertir esos pagos, y después anularla.',
            );
        }

        if (blank(trim($motivo))) {
            throw new \RuntimeException('Hay que escribir el motivo de la anulación.');
        }

        return DB::transaction(function () use ($motivo) {
            $this->forceFill([
                'status'      => InvoiceStatus::Void,
                'voided_at'   => now(),
                'void_reason' => trim($motivo),
                'balance_due' => 0,
            ])->save();

            /* -------------------------------------------------------------
             | Si venía de un presupuesto, se le devuelve la libertad.
             |
             | El presupuesto quedó marcado como "Convertida" y bloqueado.
             | Si la factura se anula, ese bloqueo ya no tiene sentido: hay
             | que poder corregir el presupuesto y volver a facturarlo.
             |
             | Se devuelve a "Aceptada" y no a "Borrador" porque el cliente
             | ya había dicho que sí. Eso no se deshace porque nos hayamos
             | equivocado tecleando.
             * ---------------------------------------------------------- */
            if ($this->estimate) {
                $this->estimate->update([
                    'status'               => \App\Enums\EstimateStatus::Accepted,
                    'converted_invoice_id' => null,
                ]);
            }

            return $this;
        });
    }

    /**
     * Adjunta un archivo a la factura (RB-034).
     *
     * $viajaConLaFactura decide si el documento se le manda al cliente o
     * se queda como respaldo interno.
     */
    public function attachDocument(
        string $path,
        string $nombre,
        string $disk = 'local',
        DocumentCategory $categoria = DocumentCategory::Other,
        bool $viajaConLaFactura = true,
        ?string $mime = null,
        ?int $bytes = null,
    ): Document {
        return $this->documents()->create([
            'company_id'        => $this->company_id,
            'category'          => $categoria,
            'name'              => $nombre,
            'path'              => $path,
            'disk'              => $disk,
            'mime_type'         => $mime,
            'size_bytes'        => $bytes,
            'attach_to_invoice' => $viajaConLaFactura,
            'uploaded_by'       => auth()->id(),
        ]);
    }
}
