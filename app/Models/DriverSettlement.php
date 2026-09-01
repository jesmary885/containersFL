<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Liquidación de un chofer por un período: lo que ganó por sus viajes
 * menos las deducciones (adelantos, daños, combustible).
 */

class DriverSettlement extends Model
{
     use HasFactory, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'            => SettlementStatus::class,
            'period_start'      => 'date',
            'period_end'        => 'date',
            'paid_at'           => 'date',
            'approved_at'       => 'datetime',
            'gross_amount'      => 'decimal:2',
            'deductions_amount' => 'decimal:2',
            'net_amount'        => 'decimal:2',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function driver()     { return $this->belongsTo(Driver::class); }
    public function items()      { return $this->hasMany(DriverSettlementItem::class); }
    public function trips()      { return $this->hasMany(Trip::class, 'driver_settlement_id'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
    public function documents()  { return $this->morphMany(Document::class, 'documentable'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('status', ['draft', 'approved']);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /** Los positivos son pagos, los negativos deducciones. */
    public function recalculateTotals(): static
    {
        $this->gross_amount      = (float) $this->items()->where('amount', '>', 0)->sum('amount');
        $this->deductions_amount = abs((float) $this->items()->where('amount', '<', 0)->sum('amount'));
        $this->net_amount        = $this->gross_amount - $this->deductions_amount;

        $this->saveQuietly();

        return $this;
    }

    /**
     * Cierra la liquidación y marca los viajes como pagados.
     * A partir de acá no se pueden agregar más viajes.
     */
    public function approve(User $user): static
    {
        $this->recalculateTotals();

        $this->status      = SettlementStatus::Approved;
        $this->approved_by = $user->id;
        $this->approved_at = now();
        $this->save();

        $this->trips()->update(['driver_payment_status' => 'settled']);

        return $this;
    }
}
