<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \App\Enums\LocationType;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Yardas propias, puertos y sitios de cliente.
 * Los depósitos de proveedor NO van acá, van en Depot.
 */

class Location extends Model
{
    use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type'              => LocationType::class,
            'address'           => 'array',
            'is_active'         => 'boolean',
            'latitude'          => 'decimal:7',
            'longitude'         => 'decimal:7',

            /*
             | Estos dos faltaban y son los que hacen falta para RB-021:
             | el almacenaje en yarda desde el tercer día.
             */
            'daily_storage_fee' => 'decimal:2',
            'free_storage_days' => 'integer',

            /*
             | SE QUITARON 'is_yard' y 'capacity'.
             |
             | 'is_yard' era redundante: la columna 'type' ya distingue
             | yard / customer_site / port / other, y ahora además está
             | casteada al enum LocationType. Tener las dos cosas es
             | pedir que algún día se contradigan.
             |
             | 'capacity' no existe en la tabla. Si más adelante se
             | quiere controlar el cupo de la yarda, se agrega la
             | columna Y el cast a la vez, y vuelve el método hasSpace().
             */
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function containers() { return $this->hasMany(Container::class); }
    public function company()    { return $this->belongsTo(Company::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('name');
    }

    /**
     * Solo yardas propias.
     *
     * Es EL scope crítico del inventario: RB-019 dice que el stock
     * disponible solo cuenta unidades físicamente en yarda. Antes
     * preguntaba por una columna 'is_yard' que no existe, así que
     * lanzaba error de SQL cada vez que se abría el inventario.
     */
    public function scopeYards(Builder $q): Builder
    {
        return $q->where('type', LocationType::Yard);
    }

    /** Cuántas unidades hay ahora mismo. Se cuenta, no se guarda. */
    public function getOccupancyAttribute(): int
    {
        return $this->containers()->inYard()->count();
    }

    /**
     * Cobra almacenaje esta ubicación (RB-021).
     *
     * Solo tiene sentido en yardas propias y solo si le pusieron
     * tarifa. Un puerto o el terreno del cliente no cobran nada.
     */
    public function chargesStorage(): bool
    {
        return $this->type === LocationType::Yard
            && $this->daily_storage_fee > 0;
    }
}
