<?php

namespace App\Models;


use App\Enums\CommissionStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
     use HasFactory, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'      => CommissionStatus::class,
            'mode'        => \App\Enums\CommissionMode::class,
            'base_type'   => \App\Enums\CommissionBase::class,
            'base_amount' => 'decimal:2',   // sobre qué monto se calculó
            'percent'     => 'decimal:2',
            'amount'      => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance'     => 'decimal:2',
            'sale_date'   => 'date',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function sale()        { return $this->belongsTo(Sale::class); }
    public function invoice()     { return $this->belongsTo(Invoice::class); }
    public function payments()    { return $this->hasMany(CommissionPayment::class); }
    public function salesperson() { return $this->belongsTo(User::class, 'salesperson_id'); }

    /*
     | EL VENDEDOR DE VERDAD — CORREGIDO 25-09
     |
     | salesperson() apunta a un USUARIO del sistema, y los vendedores no
     | tienen cuenta. Miguelito vende desde 2024 y probablemente nunca ha
     | abierto el sistema.
     |
     | Esta es la relación que hay que usar. La otra se queda por las filas
     | viejas y porque a veces quien registra sí es un usuario.
     */
    public function employee() { return $this->belongsTo(Employee::class, 'employee_id'); }
    public function container() { return $this->belongsTo(Container::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeUnpaid(Builder $q): Builder
    {
        return $q->whereIn('status', ['pending', 'partial'])->where('balance', '>', 0);
    }

    public function scopeForSalesperson(Builder $q, int $userId): Builder
    {
        return $q->where('salesperson_id', $userId);
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /** Mismo patrón que Expense. Lo dispara el observer de los abonos. */
    public function recalculateBalance(): static
    {
        $this->paid_amount = (float) $this->payments()->sum('amount');
        $this->balance     = (float) $this->amount - (float) $this->paid_amount;

        $this->status = match (true) {
            $this->balance <= 0.001        => CommissionStatus::Paid,
            (float) $this->paid_amount > 0 => CommissionStatus::Partial,
            default                        => $this->status,
        };

        $this->saveQuietly();

        return $this;
    }
}
