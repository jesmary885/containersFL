<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
     use HasFactory, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'method'           => PaymentMethod::class,
            'status'           => PaymentStatus::class,
            'is_deposit'       => 'boolean',
            'received_at'      => 'datetime',
            'amount'           => 'decimal:2',
            'fee_amount'       => 'decimal:2',  // lo que retuvo Square
            'net_amount'       => 'decimal:2',  // lo que entró al banco
            'unapplied_amount' => 'decimal:2',  // saldo sin aplicar a facturas
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer()    { return $this->belongsTo(Customer::class); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }

    public function cardAuth()
    {
        return $this->belongsTo(CreditCardAuthorization::class, 'credit_card_authorization_id');
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')
            ->withPivot('amount', 'allocated_at')
            ->withTimestamps();
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function getAvailableAmountAttribute(): float
    {
        return (float) $this->amount - $this->allocated_amount;
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Aplica el pago (o parte) a una factura.
     *
     * Todo dentro de una transacción: o se crea la asignación Y se
     * actualiza la factura Y se actualiza el saldo del pago, o no pasa nada.
     * Un pago aplicado sin actualizar el saldo de la factura es un cliente
     * al que le vas a cobrar dos veces.
     *
     * El margen de 0.001 absorbe la imprecisión de los decimales.
     */
    public function applyTo(Invoice $invoice, ?float $amount = null): PaymentAllocation
    {
        return DB::transaction(function () use ($invoice, $amount) {
            $available = $this->available_amount;

            // Por defecto: lo menor entre lo que queda del pago y lo que debe la factura.
            $amount ??= min($available, (float) $invoice->balance_due);

            throw_if($amount <= 0, new \RuntimeException('No hay saldo disponible en el pago.'));
            throw_if($amount > $available + 0.001, new \RuntimeException('El monto excede el saldo del pago.'));

            $allocation = $this->allocations()->create([
                'invoice_id'   => $invoice->id,
                'amount'       => $amount,
                'allocated_at' => now(),
                'allocated_by' => auth()->id(),
            ]);

            $invoice->amount_paid = (float) $invoice->amount_paid + $amount;
            $invoice->balance_due = (float) $invoice->total - (float) $invoice->amount_paid;

            if ($invoice->balance_due <= 0.001) {
                $invoice->status  = InvoiceStatus::Paid;
                $invoice->paid_at = now();
            } elseif ($invoice->amount_paid > 0) {
                $invoice->status = InvoiceStatus::Partial;
            }

            $invoice->saveQuietly();

            $this->unapplied_amount = (float) $this->amount - $this->allocated_amount;
            $this->saveQuietly();

            return $allocation;
        });
    }
}
