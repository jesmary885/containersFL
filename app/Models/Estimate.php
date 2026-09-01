<?php

namespace App\Models;

use App\Enums\EstimateStatus;
use \App\Enums\UseType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Estimate extends Model
{
    use HasFactory, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'      => EstimateStatus::class,
            'use_type'    => UseType::class,
            'bill_to'     => 'array',
            'ship_to'     => 'array',
            'issue_date'  => 'date',
            'valid_until' => 'date',
            'tax_exempt'  => 'boolean',
            'tax_rate'    => 'decimal:2',
            'total'       => 'decimal:2',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer() { return $this->belongsTo(Customer::class); }
    public function depot()    { return $this->belongsTo(Depot::class); }
    public function items()    { return $this->hasMany(EstimateItem::class)->orderBy('sort_order'); }
    public function sale()     { return $this->hasOne(Sale::class); }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Convierte el estimate en factura.
     *
     * Toma el siguiente número de la secuencia de INVOICES (no de
     * estimates): son numeraciones distintas y no deben cruzarse.
     *
     * Todo en una transacción: si falla copiando las líneas, no queda
     * una factura vacía con número consumido.
     */
    public function convertToInvoice(): Invoice
    {
        return DB::transaction(function () {
            $invoice = Invoice::create([
                'company_id'              => $this->company_id,
                'customer_id'             => $this->customer_id,
                'invoice_number'          => $this->company->nextNumber('invoice'),
                'type'                    => 'sale',
                'status'                  => 'draft',
                'estimate_id'             => $this->id,
                'issue_date'              => now()->toDateString(),
                'terms'                   => $this->terms,
                'bill_to'                 => $this->bill_to,
                'ship_to'                 => $this->ship_to,
                'tax_rate'                => $this->tax_rate,
                'tax_exempt'              => $this->tax_exempt,
                'credit_card_fee_percent' => 0,   // se define al elegir el método de pago
                'notes'                   => $this->notes,
                'footer_terms'            => $this->footer_terms,
                'created_by'              => auth()->id(),
            ]);

            foreach ($this->items as $item) {
                $invoice->items()->create($item->only([
                    'line_number', 'product_id', 'container_id', 'description',
                    'quantity', 'unit_price', 'amount', 'taxable',
                    'bundle_key', 'bundle_description', 'sort_order',
                ]));
            }

            $invoice->recalculate();

            $this->update([
                'status'               => EstimateStatus::Converted,
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }
}
