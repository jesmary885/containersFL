<?php

namespace App\Models;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Cuánto de UN pago se aplicó a UNA factura.
 *
 * Existe porque un cheque de 5,000 puede cubrir tres facturas, y una
 * factura de 8,000 puede pagarse con dos transferencias. Sin esta tabla
 * no se puede saber qué pagó qué.*/

class PaymentAllocation extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'allocated_at' => 'datetime',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function payment()     { return $this->belongsTo(Payment::class); }
    public function invoice()     { return $this->belongsTo(Invoice::class); }
    public function allocatedBy() { return $this->belongsTo(User::class, 'allocated_by'); }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Deshace la aplicación y devuelve el saldo a la factura y al pago.
     * Se usa cuando se aplicó a la factura equivocada.
     */
    public function reverse(): void
    {
        DB::transaction(function () {
            $invoice = $this->invoice;
            $payment = $this->payment;

            $invoice->amount_paid = (float) $invoice->amount_paid - (float) $this->amount;
            $invoice->balance_due = (float) $invoice->total - (float) $invoice->amount_paid;

            $invoice->status = $invoice->amount_paid > 0
                ? InvoiceStatus::Partial
                : InvoiceStatus::Sent;

            $invoice->paid_at = null;
            $invoice->saveQuietly();

            $this->delete();

            $payment->unapplied_amount = (float) $payment->amount - $payment->allocated_amount;
            $payment->saveQuietly();
        });
    }
}
