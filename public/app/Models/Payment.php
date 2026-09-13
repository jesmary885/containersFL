<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL PAGO — dinero que ENTRA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * No confundir con ExpensePayment: ese es dinero que SALE (lo que le
 * pagamos a un proveedor). Este es lo que nos paga el cliente, pasa por
 * Square (RB-010) y sí lleva el recargo de tarjeta (RB-009).
 *
 * ── LA DIFERENCIA ENTRE "amount" Y "unapplied_amount" ──
 *
 * Un cheque de $5,000 se recibe como un solo Payment. Puede cubrir tres
 * facturas de $2,000, $2,000 y $1,000 — tres filas en payment_allocations
 * — o quedarse sin aplicar del todo si es un anticipo (is_deposit) antes
 * de que exista la factura. unapplied_amount es justo ese resto: lo que
 * entró al banco pero todavía no se descontó de ninguna deuda.
 *
 * ── LO QUE SE CORRIGIÓ EN ESTA VERSIÓN ──
 *
 *   applyTo()   Antes siempre hacía create(). Con el índice único
 *               (payment_id, invoice_id) de la migración, aplicar el
 *               mismo pago dos veces a la misma factura reventaba con
 *               clave duplicada. Ahora usa updateOrCreate y SUMA el
 *               monto nuevo al que ya hubiera, en vez de reemplazarlo.
 *
 *               Se agregaron también las dos comprobaciones que antes no
 *               existían: que el pago esté confirmado (isSettled()) y
 *               que la factura sea de la misma compañía que el pago. Sin
 *               la segunda, nada impedía —a nivel de código— usar un
 *               pago de FLCHR para saldar una factura de RS Transport, lo
 *               que mezclaría el dinero de las dos entidades legales
 *               (RB-001).
 */
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
            'method'                    => PaymentMethod::class,
            'status'                    => PaymentStatus::class,
            'is_deposit'                => 'boolean',
            'received_at'               => 'datetime',
            'amount'                    => 'decimal:2',
            'fee_amount'                => 'decimal:2',  // lo que retuvo Square
            'net_amount'                => 'decimal:2',  // lo que entró al banco
            'unapplied_amount'          => 'decimal:2',  // saldo sin aplicar a facturas
            'credit_card_authorization_id' => 'integer',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer()    { return $this->belongsTo(Customer::class); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
    public function createdBy()   { return $this->belongsTo(User::class, 'created_by'); }

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
     | LECTURA — filtros del listado
     * ================================================================== */

    /** Busca por número de pago, referencia (# de cheque) o nombre del cliente. */
    public function scopeSearch(Builder $q, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $q;
        }

        $t = '%'.trim($termino).'%';

        return $q->where(function (Builder $q) use ($t) {
            $q->where('payment_number', 'like', $t)
              ->orWhere('reference', 'like', $t)
              ->orWhereHas('customer', fn (Builder $c) => $c
                  ->where('display_name', 'like', $t)
                  ->orWhere('company_name', 'like', $t)
                  ->orWhere('customer_number', 'like', $t));
        });
    }

    public function scopeMethodIs(Builder $q, ?string $metodo): Builder
    {
        return blank($metodo) ? $q : $q->where('method', $metodo);
    }

    public function scopeStatusIs(Builder $q, ?string $estado): Builder
    {
        return blank($estado) ? $q : $q->where('status', $estado);
    }

    public function scopeReceivedBetween(Builder $q, $from, $to): Builder
    {
        return $q->whereBetween('received_at', [$from, $to]);
    }

    /** Pagos que todavía tienen dinero sin aplicar a ninguna factura. */
    public function scopeWithUnapplied(Builder $q): Builder
    {
        return $q->where('unapplied_amount', '>', 0.001)
            ->where('status', PaymentStatus::Completed->value);
    }

    /* =====================================================================
     | LECTURA — preguntas que hace la pantalla
     * ================================================================== */

    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function getAvailableAmountAttribute(): float
    {
        return round((float) $this->amount - $this->allocated_amount, 2);
    }

    public function getIsFullyAppliedAttribute(): bool
    {
        return $this->available_amount <= 0.001;
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Aplica el pago (o parte) a una factura.
     *
     * Se puede llamar varias veces sobre la MISMA factura: la segunda
     * llamada SUMA al monto que ya estaba aplicado, no lo reemplaza. Es
     * lo que permite, por ejemplo, completar hoy con $300 lo que ayer se
     * dejó a medias con $500 del mismo cheque.
     *
     * Todo dentro de una transacción con bloqueo de filas: o se actualiza
     * la asignación, la factura y el saldo del pago los tres a la vez, o
     * no pasa nada. Un pago aplicado sin actualizar el saldo de la
     * factura es un cliente al que le vas a cobrar dos veces.
     */
    public function applyTo(Invoice $invoice, ?float $amount = null): PaymentAllocation
    {
        return DB::transaction(function () use ($invoice, $amount) {

            // Se relee con lock: si dos personas aplican el mismo pago al
            // mismo tiempo, la segunda espera a que la primera termine.
            /** @var self $pago */
            $pago = self::query()->allCompanies()->lockForUpdate()->findOrFail($this->id);
            $factura = Invoice::query()->allCompanies()->lockForUpdate()->findOrFail($invoice->id);

            throw_unless(
                $pago->status->isSettled(),
                new \RuntimeException('Solo se puede aplicar un pago confirmado (status: completed).'),
            );

            throw_unless(
                (int) $factura->company_id === (int) $pago->company_id,
                new \RuntimeException('El pago y la factura pertenecen a compañías distintas.'),
            );

            throw_if(
                $factura->status === InvoiceStatus::Void,
                new \RuntimeException('No se puede aplicar un pago a una factura anulada.'),
            );

            throw_if(
                $factura->status === InvoiceStatus::Paid,
                new \RuntimeException('La factura '.$factura->invoice_number.' ya está pagada por completo.'),
            );

            $yaAplicado = (float) $pago->allocations()->where('invoice_id', $factura->id)->value('amount');
            $disponible = round((float) $pago->amount - (float) $pago->allocations()->sum('amount'), 2);

            // Por defecto: lo menor entre lo que queda del pago y lo que debe la factura.
            $monto = $amount ?? min($disponible, (float) $factura->balance_due);
            $monto = round($monto, 2);

            throw_if($monto <= 0, new \RuntimeException('No hay saldo disponible en el pago.'));
            throw_if($monto > $disponible + 0.001, new \RuntimeException('El monto excede el saldo del pago.'));
            throw_if($monto > (float) $factura->balance_due + 0.001, new \RuntimeException('El monto excede el saldo de la factura.'));

            $allocation = $pago->allocations()->updateOrCreate(
                ['invoice_id' => $factura->id],
                [
                    'amount'       => round($yaAplicado + $monto, 2),
                    'allocated_at' => now(),
                    'allocated_by' => auth()->id(),
                ],
            );

            $factura->amount_paid = round((float) $factura->amount_paid + $monto, 2);
            $factura->balance_due = round((float) $factura->total - $factura->amount_paid, 2);

            if ($factura->balance_due <= 0.001) {
                $factura->status  = InvoiceStatus::Paid;
                $factura->paid_at = now();
            } elseif ($factura->amount_paid > 0) {
                $factura->status = InvoiceStatus::Partial;
            }

            $factura->saveQuietly();

            $pago->unapplied_amount = round((float) $pago->amount - (float) $pago->allocations()->sum('amount'), 2);
            $pago->saveQuietly();

            // El objeto en memoria también se refresca, por si el que
            // llamó a applyTo() sigue usando $this después.
            $this->unsetRelation('allocations')->refresh();

            return $allocation;
        });
    }

    /**
     * Revierte TODAS las asignaciones de este pago.
     *
     * Se usa cuando el pago entero deja de ser válido: un cheque que
     * rebotó, una tarjeta que se disputó, un reembolso completo. Cada
     * asignación se deshace una por una con PaymentAllocation::reverse(),
     * que ya sabe devolver el saldo a la factura correspondiente.
     *
     * No se llama a mano desde la pantalla: la dispara el PaymentObserver
     * cuando el estado del pago cambia a uno de los que
     * PaymentStatus::reversesAllocations() marca como tal.
     */
    public function reverseAllAllocations(): void
    {
        DB::transaction(function () {
            $this->allocations()->get()->each->reverse();
        });
    }
}
