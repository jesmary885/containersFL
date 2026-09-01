<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Invoice extends Model
{
     // SIN SoftDeletes a propósito: una factura no se borra, se anula
    // con status void. Es un documento fiscal.
    use HasFactory, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type'                    => InvoiceType::class,
            'status'                  => InvoiceStatus::class,
            'expected_payment_method' => \App\Enums\PaymentMethod::class,
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

    public function customer()     { return $this->belongsTo(Customer::class); }
    public function items()        { return $this->hasMany(InvoiceItem::class)->orderBy('sort_order'); }
    public function sale()         { return $this->belongsTo(Sale::class); }
    public function rental()       { return $this->belongsTo(Rental::class); }
    public function rentalPeriod() { return $this->belongsTo(RentalPeriod::class); }
    public function estimate()     { return $this->belongsTo(Estimate::class); }
    public function documents()    { return $this->morphMany(Document::class, 'documentable'); }

    /** Prueba guardada de por qué no se cobró impuesto. */
    public function exemptionCertificate()
    {
        return $this->belongsTo(TaxExemptionCertificate::class, 'tax_exemption_certificate_id');
    }

    /** Viajes cubiertos por esta factura intercompañía. */
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
     | LECTURA — scopes
     * ================================================================== */

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['paid', 'void'])
            ->whereDate('due_date', '<', now())
            ->where('balance_due', '>', 0);
    }

    public function scopeUnpaid(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['paid', 'void'])
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
            ->where('status', '!=', 'void')
            ->where('tax_amount', '>', 0);
    }

    /* =====================================================================
     | LECTURA — presentación
     * ================================================================== */

    /**
     * Agrupa las líneas por bundle_key para imprimir UN precio al cliente,
     * conservando el desglose interno que necesita el cálculo del impuesto.
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
                    'quantity'   => $first->quantity,
                    'unit_price' => $isBundle ? $group->sum('amount') : $first->unit_price,
                    'amount'     => $group->sum('amount'),
                    'container'  => $first->container,
                ];
            })
            ->values();
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
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
}
