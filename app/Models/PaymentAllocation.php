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
 * no se puede saber qué pagó qué.
 */
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
     *
     * Se usa cuando se aplicó a la factura equivocada, o cuando el pago
     * completo deja de ser válido (Payment::reverseAllAllocations() la
     * llama una vez por cada asignación).
     *
     * lockForUpdate() sobre la factura evita que esta reversión choque
     * con un applyTo() que esté corriendo al mismo tiempo sobre la misma
     * factura desde otra pestaña.
     */
    public function reverse(): void
    {
        DB::transaction(function () {
            $invoice = Invoice::query()->allCompanies()->lockForUpdate()->findOrFail($this->invoice_id);
            $payment = Payment::query()->allCompanies()->lockForUpdate()->findOrFail($this->payment_id);

            $invoice->amount_paid = round((float) $invoice->amount_paid - (float) $this->amount, 2);
            $invoice->balance_due = round((float) $invoice->total - $invoice->amount_paid, 2);

            $invoice->status = $invoice->amount_paid > 0.001
                ? InvoiceStatus::Partial
                : InvoiceStatus::Sent;

            $invoice->paid_at = null;
            $invoice->saveQuietly();

            $this->delete();

            $payment->unapplied_amount = round(
                (float) $payment->amount - (float) $payment->allocations()->sum('amount'),
                2,
            );
            $payment->saveQuietly();
        });
    }
}
