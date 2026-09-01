<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Depot extends Model
{
    use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'address'            => 'array',
            'is_active'          => 'boolean',
            'default_pickup_fee' => 'decimal:2',
            'daily_late_fee'     => 'decimal:2',
            'default_miles'      => 'decimal:2',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function supplier()   { return $this->belongsTo(Supplier::class); }
    public function purchases()  { return $this->hasMany(Purchase::class); }
    public function trips()      { return $this->hasMany(Trip::class); }
    public function sales()      { return $this->hasMany(Sale::class); }
    public function rentals()    { return $this->hasMany(Rental::class); }
    public function prices()     { return $this->hasMany(SupplierPrice::class); }
    public function containers() { return $this->hasMany(Container::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Montos con los que se precarga el formulario.
     *
     * IMPORTANTE: todo lo que devuelve es EDITABLE en la operación.
     * Una vez guardado el registro, el sistema lee de la tabla
     * transaccional y nunca vuelve acá. Así, si mañana suben el fee,
     * las operaciones viejas conservan lo que se cobró ese día.
     */
    public function defaults(): array
    {
        return [
            'pickup_fee'     => (float) ($this->default_pickup_fee ?? 0),
            'daily_late_fee' => $this->daily_late_fee !== null
                ? (float) $this->daily_late_fee
                : null,
            'pickup_days'    => $this->default_pickup_days,
            'miles'          => $this->default_miles !== null
                ? (float) $this->default_miles
                : null,
        ];
    }
}
