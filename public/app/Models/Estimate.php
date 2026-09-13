<?php

namespace App\Models;

use App\Enums\EstimateStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\UseType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL PRESUPUESTO (ESTIMATE)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Es la cotización que se le manda al cliente antes de vender. Tiene las
 * mismas columnas de dinero que la factura, en el mismo orden, y eso es a
 * propósito: convertirlo en factura es copiar valores uno a uno, sin
 * traducir nada (RB-033).
 *
 * ── DIFERENCIA CON LA FACTURA ──
 *
 * Un presupuesto SÍ se puede borrar, editar y rehacer. No compromete a
 * nadie. Una factura emitida no se borra jamás: se anula, y su número
 * queda consumido para siempre.
 *
 * Por eso empezamos el sistema por aquí: es el mismo formulario pero sin
 * consecuencias. Es mejor equivocarse en el documento barato.
 *
 * ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──
 *
 *   1. Se completaron los castes. Faltaban NUEVE columnas de dinero
 *      (subtotal, taxable_base, tax_amount, credit_card_fee, delivery_amount,
 *      pickup_fee, miles, rate_per_mile, discount_amount) y sent_at.
 *
 *      Sin el caste, PHP recibe el número como texto: "2400.00" en vez de
 *      2400.00. Sumar textos funciona por casualidad en PHP, hasta que un
 *      día no funciona.
 *
 *   2. Se agregó recalculate(), igual que el de Invoice.
 *   3. Se agregó printableLines(), para imprimir agrupado (RB-007).
 *   4. convertToInvoice() ahora verifica antes de convertir, copia el
 *      certificado de exención y no deja la factura sin BILL TO.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class Estimate extends Model
{
    use HasFactory, \App\Models\Concerns\HasDocumentAddresses, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'   => EstimateStatus::class,
            'use_type' => UseType::class,

            /*
             | Las direcciones van como arreglo porque en la base están
             | guardadas en JSON. Es una COPIA congelada del día en que se
             | hizo la cotización: si el cliente se muda antes de aceptar,
             | el documento sigue mostrando la dirección con la que se le
             | cotizó.
             */
            'bill_to' => 'array',
            'ship_to' => 'array',

            'issue_date'  => 'date',
            'valid_until' => 'date',
            'sent_at'     => 'datetime',

            /*
             | ── LAS COLUMNAS DE DINERO ──
             |
             | 'decimal:2' significa: al leerlas, entrégamelas siempre con
             | dos decimales exactos.
             |
             | Faltaban casi todas. El efecto no era un error visible: era
             | que los importes llegaban como texto y las comparaciones
             | daban resultados raros de vez en cuando.
             */
            
        
            'delivery_amount'         => 'decimal:2',
            'subtotal'                => 'decimal:2',
            'discount_amount'         => 'decimal:2',
            'taxable_base'            => 'decimal:2',
            'tax_rate'                => 'decimal:2',
            'tax_amount'              => 'decimal:2',
            'credit_card_fee_percent' => 'decimal:2',
            'credit_card_fee'         => 'decimal:2',
            'total'                   => 'decimal:2',

            'tax_exempt' => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer() { return $this->belongsTo(Customer::class); }
    /*
     | El deposito de donde se retira el contenedor. Ver la nota en
     | Sale::depot(): la columna vuelve a existir porque el levantamiento
     | la exige.
     */
    public function depot()    { return $this->belongsTo(Depot::class); }
    public function sale()     { return $this->hasOne(Sale::class); }

    /** Las líneas, siempre en el orden en que el usuario las acomodó. */
    public function items()
    {
        return $this->hasMany(EstimateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Quién cotizó. De aquí sale la comisión si la venta se concreta (RB-030). */
    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** La factura en la que se convirtió, si se convirtió. */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    /* =====================================================================
     | LECTURA — filtros para el listado
     * ================================================================== */

    /**
     * Busca por número, por nombre del cliente o por el texto de las
     * líneas.
     *
     * Lo de buscar dentro de las líneas no es un capricho: es normal que
     * llamen diciendo "el presupuesto del 40 alto para Homestead" y nadie
     * se acuerde del número.
     */
    public function scopeSearch(Builder $q, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $q;
        }

        $t = '%'.trim($termino).'%';

        return $q->where(function (Builder $q) use ($t) {
            $q->where('estimate_number', 'like', $t)
              ->orWhereHas('customer', fn (Builder $c) => $c
                  ->where('display_name', 'like', $t)
                  ->orWhere('company_name', 'like', $t)
                  ->orWhere('customer_number', 'like', $t))
              ->orWhereHas('items', fn (Builder $i) => $i
                  ->where('description', 'like', $t));
        });
    }

    /** Los que siguen vivos: ni rechazados, ni vencidos, ni convertidos. */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            EstimateStatus::Draft->value,
            EstimateStatus::Processed->value,
            EstimateStatus::Sent->value,
            EstimateStatus::Accepted->value,
        ]);
    }

    /**
     * Los que ya pasaron su fecha de validez pero nadie ha marcado como
     * vencidos todavía.
     *
     * Se usa desde la pantalla para pintarlos en rojo, y más adelante
     * desde un comando diario que les cambie el estado solo.
     */
    public function scopeExpiredByDate(Builder $q): Builder
    {
        return $q->whereIn('status', [
                EstimateStatus::Draft->value,
                EstimateStatus::Processed->value,
                EstimateStatus::Sent->value,
            ])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now());
    }

    /* =====================================================================
     | LECTURA — preguntas que hace la pantalla
     * ================================================================== */

    /**
     * ¿Todavía se puede editar?
     *
     * Un presupuesto convertido en factura NO se toca: si se cambiara,
     * la factura emitida dejaría de coincidir con el papel que firmó el
     * cliente.
     */
    public function isEditable(): bool
    {
        return ! $this->status->isClosed();
    }

    /** ¿Se le pasó la fecha de validez? */
    public function isExpired(): bool
    {
        return $this->valid_until
            && $this->valid_until->endOfDay()->isPast()
            && ! $this->status->isClosed();
    }

    /**
     * Cuántos días le quedan de vigencia. Negativo si ya venció.
     * Null si el presupuesto no tiene fecha límite.
     */
    public function getDaysLeftAttribute(): ?int
    {
        if (! $this->valid_until) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->valid_until, false);
    }

    /**
     * Las líneas tal como se le imprimen al cliente (RB-007).
     *
     * ── QUÉ HACE, EN SIMPLE ──
     *
     * Por dentro el presupuesto tiene dos líneas:
     *
     *     Contenedor 40HC .......... 2,400.00   (paga impuesto)
     *     Delivery a Homestead .....   350.00   (no paga)
     *
     * Al cliente se le imprime UNA:
     *
     *     Contenedor 40HC entregado  2,750.00
     *
     * Las líneas que comparten el mismo 'bundle_key' se suman y se
     * muestran con el texto de 'bundle_description'. El desglose queda
     * guardado, pero es interno.
     *
     * Esto es lo que permite cumplir RB-006 y RB-007 al mismo tiempo:
     * un precio consolidado en el papel, y el 7% cobrado solo sobre los
     * 2,400 del contenedor.
     *
     * Es la misma lógica que Invoice::printableLines(). Está repetida a
     * propósito y no en un trait compartido: cuando lleguemos al PDF de
     * la factura van a divergir (la factura muestra número de línea y
     * fecha de servicio; el presupuesto no).
     */
    public function printableLines(): Collection
    {
        return $this->items
            ->groupBy(fn (EstimateItem $i) => $i->bundle_key ?: 'linea-'.$i->id)
            ->map(function (Collection $grupo) {
                $primera  = $grupo->first();
                $esGrupo  = $grupo->count() > 1;

                /*
                 | EL PLAZO DE LA RENTA, DENTRO DE UN GRUPO
                 |
                 | Se busca la linea que lo tenga y se saca de ahi tambien
                 | la mensualidad. No sirve el unit_price del renglon
                 | impreso: en un grupo ese numero es la SUMA de todo
                 | (renta + entrega), y multiplicarlo por los meses daria
                 | un compromiso con seis entregas dentro.
                 */
                $renta = $grupo->first(fn (EstimateItem $i) => $i->rental_months !== null);

                return (object) [
                    'description' => $esGrupo
                        ? ($primera->bundle_description ?: $primera->description)
                        : $primera->description,
                    'quantity'   => $primera->quantity,
                    'unit_price' => $esGrupo ? $grupo->sum('amount') : $primera->unit_price,
                    'amount'     => $grupo->sum('amount'),
                    'container'  => $primera->container,
                    'detalle'    => $esGrupo ? $grupo : null,   // el desglose interno

                    'rental_months'  => $renta?->rental_months,
                    'monthly_rate'   => $renta ? (float) $renta->unit_price : null,
                    'rental_taxable' => (bool) ($renta?->taxable ?? false),

                    /*
                     | El detalle de la modificacion. Se junta el de todas
                     | las lineas del grupo: un grupo puede llevar el
                     | contenedor y su modificacion en un solo renglon
                     | impreso, y el detalle no puede perderse por eso.
                     */
                    'work_details' => $grupo->pluck('work_details')->filter()->implode("\n") ?: null,
                ];
            })
            ->values();
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Vuelve a sumar todo.
     *
     * Igual que en Invoice: la aritmética vive en InvoiceCalculator y no
     * se copia en Livewire ni en Blade.
     *
     * saveQuietly() guarda SIN disparar los observers otra vez. Sin eso,
     * el observer de las líneas volvería a llamar a este método y
     * entraríamos en un bucle sin final.
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
     * Marca el presupuesto como enviado al cliente.
     *
     * No manda el correo: solo deja constancia. El envío real lo hará el
     * módulo de notificaciones, y cuando exista, llamará a este método.
     */
    /**
     * Marca el presupuesto como armado y listo para revisar.
     *
     * No manda nada. Solo dice "esto ya no es un borrador a medias", que
     * es lo que habilita la pantalla de revision.
     */
    public function markAsProcessed(): static
    {
        if ($this->status === EstimateStatus::Draft) {
            $this->status = EstimateStatus::Processed;
            $this->save();
        }

        return $this;
    }

    public function markAsSent(): static
    {
        $this->status  = EstimateStatus::Sent;
        $this->sent_at = now();
        $this->save();

        return $this;
    }

    /**
     * ═════════════════════════════════════════════════════════════════
     * CONVERTIR EL PRESUPUESTO EN FACTURA (RB-033)
     * ═════════════════════════════════════════════════════════════════
     *
     * El número de la factura sale de la secuencia de INVOICES, no de la
     * de estimates: son dos numeraciones distintas y no deben cruzarse.
     * El presupuesto EST-0042 puede convertirse en la factura 1358.
     *
     * Todo va dentro de una transacción: o se crea la factura Y se copian
     * las líneas Y se marca el presupuesto, o no pasa nada. Sin eso, un
     * fallo a mitad dejaría una factura vacía con número ya consumido.
     *
     * ── LAS CUATRO PROTECCIONES QUE SE AGREGARON ──
     */
    public function convertToInvoice(): Invoice
    {
        /* -----------------------------------------------------------------
         | 1 · ¿Ya se convirtió antes?
         |
         | Sin esta línea, dos clics seguidos en el botón "Convertir"
         | producían DOS facturas por la misma venta, cada una con su
         | número. Y ninguna de las dos es fácil de descubrir después.
         * -------------------------------------------------------------- */
        if ($this->converted_invoice_id) {
            throw new \RuntimeException(
                'El presupuesto '.$this->estimate_number.' ya se convirtió en la '
                .'factura '.$this->invoice?->invoice_number.'.',
            );
        }

        /* -----------------------------------------------------------------
         | 2 · ¿Está en un estado que permita convertir?
         |
         | canConvert() del enum dice que solo los enviados o aceptados.
         | Un borrador no se factura: todavía no se le mostró al cliente.
         | Un rechazado tampoco.
         * -------------------------------------------------------------- */
        if (! $this->status->canConvert()) {
            throw new \RuntimeException(
                'Un presupuesto en estado "'.$this->status->label().'" no se puede '
                .'facturar. Antes hay que enviárselo al cliente.',
            );
        }

        /* -----------------------------------------------------------------
         | 3 · ¿Tiene líneas?
         * -------------------------------------------------------------- */
        if ($this->items()->count() === 0) {
            throw new \RuntimeException(
                'El presupuesto '.$this->estimate_number.' no tiene ninguna línea.',
            );
        }

        return DB::transaction(function () {

            /* -------------------------------------------------------------
             | 4 · EL BILL TO NO PUEDE IR VACÍO
             |
             | La columna invoices.bill_to es obligatoria en la base: toda
             | factura tiene que decir a nombre de quién se emitió.
             |
             | Si el presupuesto se guardó sin dirección (pasa: el cliente
             | pide precio por teléfono y todavía no dio dirección fiscal),
             | copiarlo tal cual haría fallar el guardado con un error de
             | SQL que no le dice nada al usuario.
             |
             | Así que si viene vacío, se arma uno mínimo con el nombre del
             | cliente. Es poco, pero es un documento válido.
             * ---------------------------------------------------------- */
            $billTo = $this->bill_to ?: [
                'label' => $this->customer?->name,
                'line1' => null,
            ];

            /* -------------------------------------------------------------
             | 5 · EL CERTIFICADO DE EXENCIÓN (RB-015)
             |
             | Si el cliente está exento, hay que guardar CUÁL certificado
             | lo justifica, no solo la marca.
             |
             | Se busca el vigente a la fecha de emisión. Si mañana se
             | vence, esta factura conserva la prueba de que el día que se
             | emitió el cliente sí estaba exento. Eso es exactamente lo
             | que pide una auditoría del estado.
             * ---------------------------------------------------------- */
            $certificado = $this->tax_exempt
                ? $this->customer?->activeExemptionCertificate(now())
                : null;

            $invoice = Invoice::create([
                'company_id'     => $this->company_id,
                'customer_id'    => $this->customer_id,
                'invoice_number' => $this->company->nextNumber('invoice'),

                'type'   => InvoiceType::Sale,
                'status' => InvoiceStatus::Draft,

                'estimate_id' => $this->id,
                'issue_date'  => now()->toDateString(),
                'terms'       => $this->terms,

                'bill_to' => $billTo,
                'ship_to' => $this->ship_to,

                /* -------------------------------------------------------------
                 | PARA QUÉ SE USA EL CONTENEDOR (RB-056)
                 |
                 | Se perdía acá. Una exportación cotizada llegaba a la
                 | factura como una venta normal, y el certificado CSC
                 | —que va INCLUIDO en el precio de exportación— quedaba
                 | sin señal de que ya estaba cobrado. Cobrarlo aparte es
                 | cobrarlo dos veces.
                 * ---------------------------------------------------------- */
                'use_type' => $this->use_type,

                /* -------------------------------------------------------------
                 | CON QUÉ DIJO EL CLIENTE QUE IBA A PAGAR
                 |
                 | Se copiaba el recargo de 3.5% pero NO el método que lo
                 | justifica. La factura mostraba un cargo por tarjeta sin
                 | decir en ninguna parte que el cliente eligió tarjeta, y
                 | cuando llamaba a preguntar no había qué contestarle.
                 * ---------------------------------------------------------- */
                'expected_payment_method' => $this->expected_payment_method,

                /* -------------------------------------------------------------
                 | DE DÓNDE SALE EL CONTENEDOR (RB-031)
                 |
                 | La recogida es depósito → yarda y la paga FLCHR: no se
                 | le cotiza al cliente, pero es un costo real de esta
                 | operación. Sin él en la factura, el margen de la venta
                 | sale inflado por el importe de la recogida.
                 * ---------------------------------------------------------- */
                'depot_id'   => $this->depot_id,
                'pickup_fee' => $this->pickup_fee,

                /* -------------------------------------------------------------
                 | EL TRANSPORTE COBRADO (RB-030)
                 |
                 | Lo vuelve a calcular recalculate() desde las líneas,
                 | pero se copia igual para que la factura sea correcta
                 | aunque nadie la recalcule nunca.
                 * ---------------------------------------------------------- */
                'delivery_amount' => $this->delivery_amount,

                'discount_amount' => $this->discount_amount,
                'tax_rate'        => $this->tax_rate,
                'tax_exempt'      => $this->tax_exempt,

                'tax_exemption_certificate_id' => $certificado?->id,

                /*
                 | El recargo de tarjeta SÍ se copia.
                 |
                 | Si al cotizar el cliente dijo que iba a pagar con
                 | tarjeta, el presupuesto ya le mostró el 3.5% y ese es el
                 | número que él aprobó. Ponerlo en cero acá haría que la
                 | factura saliera por menos que el presupuesto, y alguien
                 | tendría que acordarse de volver a subirlo.
                 |
                 | Si al final paga con cheque, se baja a cero en la
                 | factura y los totales se recalculan solos.
                 */
                'credit_card_fee_percent' => $this->credit_card_fee_percent,

                'notes'        => $this->notes,
                'footer_terms' => $this->footer_terms,

                /* -------------------------------------------------------------
                 | QUIEN CERRO LA VENTA VIAJA A LA FACTURA
                 |
                 | Antes se perdia acá. convertToInvoice() copiaba las
                 | direcciones, el tax, el recargo de tarjeta y hasta el
                 | pie de pagina, pero no el vendedor — porque invoices no
                 | tenia dónde ponerlo.
                 |
                 | Y la factura es el documento que se cobra: si el
                 | vendedor no está ahí, la comision no tiene de dónde
                 | colgarse. El caso real: Denisse registra el presupuesto
                 | y la factura, pero la venta la cerro Miguelito. El
                 | sistema guardaba a Denisse en las dos y a Miguelito en
                 | ninguna.
                 |
                 | La comision no se calcula acá. La factura nace en
                 | borrador y todavia puede cambiar de monto; se resuelve
                 | al emitirla (ver CommissionResolver).
                 * ---------------------------------------------------------- */
                'salesperson_id'     => $this->salesperson_id,
                'commission_mode'    => $this->salesperson_id
                    ? ($this->company?->setting('commissions', 'default_mode', 'percent'))
                    : null,
                'commission_percent' => $this->salesperson_id
                    ? ($this->company?->setting('commissions', 'default_percent', 0))
                    : null,

                'created_by'   => auth()->id(),
            ]);

            /* -------------------------------------------------------------
             | LAS LÍNEAS
             |
             | Se copian una a una, incluyendo bundle_key y
             | bundle_description: la agrupación de impresión tiene que
             | verse igual en la factura que en el presupuesto que aprobó
             | el cliente.
             * ---------------------------------------------------------- */
            foreach ($this->items as $linea) {
                $invoice->items()->create([
                    'line_number'        => $linea->line_number,
                    'product_id'         => $linea->product_id,
                    'container_id'       => $linea->container_id,
                    'description'        => $linea->description,
                    'quantity'           => $linea->quantity,
                    'unit_price'         => $linea->unit_price,
                    'taxable'            => $linea->taxable,

                    /* ---------------------------------------------------------
                     | EL DETALLE DEL TRANSPORTE (RB-049)
                     |
                     | Las tres columnas ya existían en invoice_items y
                     | nadie las llenaba. El importe sobrevivía —es
                     | cantidad × precio— pero el CÓMO se llegó a él se
                     | perdía: 45 millas a $7.80.
                     |
                     | Sin eso, una factura de transporte no se puede
                     | auditar contra el viaje, y la liquidación del
                     | chofer no tiene contra qué compararse.
                     * ------------------------------------------------------ */
                    'delivery_zip'  => $linea->delivery_zip,
                    'miles'         => $linea->miles,
                    'rate_per_mile' => $linea->rate_per_mile,

                    /* ---------------------------------------------------------
                     | EL PLAZO Y EL TRABAJO
                     |
                     | Columnas nuevas (ver la migración del 11-sep). El
                     | cliente aprobaba un presupuesto que decía "cambio
                     | de pisos y pintura" y recibía una factura que decía
                     | "reparación" a secas.
                     * ------------------------------------------------------ */
                    'rental_months' => $linea->rental_months,
                    'work_details'  => $linea->work_details,

                    'bundle_key'         => $linea->bundle_key,
                    'bundle_description' => $linea->bundle_description,
                    'sort_order'         => $linea->sort_order,
                ]);
            }

            $invoice->load('items')->recalculate();

            $this->update([
                'status'               => EstimateStatus::Converted,
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    /**
     * Hace una copia del presupuesto como borrador nuevo.
     *
     * Sirve para dos cosas de todos los días: el cliente pide "lo mismo
     * pero con dos contenedores", o el presupuesto se venció y hay que
     * rehacerlo con precios de hoy.
     *
     * La copia NO hereda el estado ni el número: nace como borrador y
     * toma el siguiente número de la secuencia.
     */
    public function duplicate(): static
    {
        return DB::transaction(function () {
            $copia = static::create([
                'company_id'      => $this->company_id,
                'customer_id'     => $this->customer_id,
                'status'          => EstimateStatus::Draft,
                'use_type'        => $this->use_type,
                'terms'           => $this->terms,
                'bill_to'         => $this->bill_to,
                'ship_to'         => $this->ship_to,
                
                'delivery_amount' => $this->delivery_amount,

                'expected_payment_method' => $this->expected_payment_method,
                
                'discount_amount' => $this->discount_amount,
                'tax_rate'        => $this->tax_rate,
                'tax_exempt'      => $this->tax_exempt,
                'credit_card_fee_percent' => $this->credit_card_fee_percent,
                'salesperson_id'  => $this->salesperson_id,
                'notes'           => $this->notes,
                'footer_terms'    => $this->footer_terms,
            ]);

            foreach ($this->items as $linea) {
                $copia->items()->create($linea->only([
                    'line_number', 'product_id', 'container_id', 'description',
                    'quantity', 'unit_price', 'taxable',
                    'bundle_key', 'bundle_description', 'sort_order',
                ]));
            }

            return $copia->load('items')->recalculate();
        });
    }
}
