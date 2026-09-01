<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'             => ExpenseStatus::class,
            'expense_date'       => 'date',
            'due_date'           => 'date',
            'paid_at'            => 'date',
            'amount'             => 'decimal:2',
            'paid_amount'        => 'decimal:2',
            'balance'            => 'decimal:2',
            'is_billable'        => 'boolean',
            'is_1099_reportable' => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     |
     | Un gasto puede colgar de muchas cosas distintas: de un contenedor
     | (reacondicionamiento), de un viaje (peaje), de una compra (fee por
     | pasarse del plazo), de un camión (mantenimiento). Todos opcionales.
     * ================================================================== */

    public function category()  { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function supplier()  { return $this->belongsTo(Supplier::class); }
    public function depot()     { return $this->belongsTo(Depot::class); }
    public function container() { return $this->belongsTo(Container::class); }
    public function trip()      { return $this->belongsTo(Trip::class); }
    public function vehicle()   { return $this->belongsTo(Vehicle::class); }
    public function purchase()  { return $this->belongsTo(Purchase::class); }
    public function driver()    { return $this->belongsTo(Driver::class); }
    public function payments()  { return $this->hasMany(ExpensePayment::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Cuentas por pagar: comprometido y no saldado. */
    public function scopePayable(Builder $q): Builder
    {
        return $q->whereIn('status', ['pending', 'approved', 'partial'])
            ->where('balance', '>', 0);
    }

    /** Base del 1099 anual de proveedores y choferes. */
    public function scopeReportable1099(Builder $q, int $year): Builder
    {
        return $q->where('is_1099_reportable', true)
            ->whereYear('expense_date', $year);
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Recalcula saldo y estado a partir de los abonos.
     * Lo dispara el observer de ExpensePayment; no se llama a mano.
     */
    public function recalculateBalance(): static
    {
        $this->paid_amount = (float) $this->payments()->sum('amount');
        $this->balance     = (float) $this->amount - (float) $this->paid_amount;

        $this->status = match (true) {
            $this->balance <= 0.001        => ExpenseStatus::Paid,
            (float) $this->paid_amount > 0 => ExpenseStatus::Partial,
            default                        => $this->status,
        };

        if ($this->status === ExpenseStatus::Paid && ! $this->paid_at) {
            $this->paid_at = $this->payments()->max('paid_at') ?? now();
        }

        $this->saveQuietly();

        return $this;
    }
}
