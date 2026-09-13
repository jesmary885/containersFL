<?php

namespace App\Models;

use App\Enums\ContainerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una línea de compra: "10 contenedores 40HC usados a 1,850".
 * Los contenedores físicos se van creando a medida que se retiran
 * del depósito, no al momento de comprar.
 */

class PurchaseItem extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'          => 'integer',
            'received_quantity' => 'integer',
            'unit_cost'         => 'decimal:2',
             'total_cost'        => 'decimal:2',
        ];
    }

    /* =====================================================================
     | EVENTOS
     * ================================================================== */

    protected static function booted(): void
    {
        static::saving(function (PurchaseItem $item) {
            $item->total_cost = round(
                (float) $item->quantity * (float) $item->unit_cost,
                2,
            );
        });
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function purchase()  { return $this->belongsTo(Purchase::class); }
    public function type()      { return $this->belongsTo(ContainerType::class, 'container_type_id'); }
    public function size()      { return $this->belongsTo(ContainerSize::class, 'container_size_id'); }
    public function condition() { return $this->belongsTo(ContainerCondition::class, 'container_condition_id'); }
    public function grade()     { return $this->belongsTo(ContainerGrade::class, 'container_grade_id'); }

    /** Los contenedores que ya se retiraron de esta línea. */
    public function containers() { return $this->hasMany(Container::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Unidades que todavía están en el depósito. */
    public function getPendingQuantityAttribute(): int
    {
        return max($this->quantity - $this->received_quantity, 0);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereColumn('received_quantity', '<', 'quantity');
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Registra el retiro de N unidades y crea los contenedores.
     * El contador se DERIVA de los contenedores creados, nunca se
     * incrementa a mano: así el conteo no se puede desincronizar.
     */
    public function receive(int $quantity, array $attributes = []): void
    {
        throw_if(
            $quantity > $this->pending_quantity,
            new \RuntimeException('Excede las unidades pendientes de esta línea.'),
        );

        for ($i = 0; $i < $quantity; $i++) {
            $this->containers()->create(array_merge([
                'container_type_id'      => $this->container_type_id,
                'container_size_id'      => $this->container_size_id,
                'container_condition_id' => $this->container_condition_id,
                'container_grade_id'     => $this->container_grade_id,
                'depot_id'               => $this->purchase->depot_id,
                'acquisition_cost'       => $this->unit_cost,
                'status'                 => ContainerStatus::InYard,
                'received_at'            => now()->toDateString(),
            ], $attributes));
        }

        $this->received_quantity = $this->containers()->count();
        $this->save();
    }
}
