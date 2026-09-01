<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una línea de la liquidación.
 * Monto positivo = se le paga. Monto negativo = se le descuenta.
 */

class DriverSettlementItem extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function settlement() { return $this->belongsTo(DriverSettlement::class, 'driver_settlement_id'); }
    public function trip()       { return $this->belongsTo(Trip::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function isDeduction(): bool
    {
        return (float) $this->amount < 0;
    }

    public function scopeDeductions(Builder $q): Builder
    {
        return $q->where('amount', '<', 0);
    }
}
