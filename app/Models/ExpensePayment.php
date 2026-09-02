<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;


/** Un abono a un gasto. Un gasto puede pagarse en varias partes. 
 * 
 * * Ejemplo real: el depósito manda un invoice de $3,000 por fees de
 * demora (RB-020). Se le pagan $1,000 ahora y el resto el mes que
 * viene. Son dos filas acá, y el saldo del gasto lo mantiene solo el
 * ExpensePaymentObserver.
*/

class ExpensePayment extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'method'  => PaymentMethod::class,
            'amount'  => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function expense()   { return $this->belongsTo(Expense::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Abonos de un período, para el reporte de salidas de caja. */
    public function scopeForPeriod(Builder $q, $from, $to): Builder
    {
        return $q->whereBetween('paid_at', [$from, $to]);
    }
}
