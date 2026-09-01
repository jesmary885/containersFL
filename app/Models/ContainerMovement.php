<?php

namespace App\Models;

use App\Enums\ContainerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \App\Enums\MovementType;

/**
 * Historial de cada cambio de estado o ubicación de un contenedor.
 *
 * Es solo lectura: se escribe una vez y nunca se edita ni se borra.
 * Sirve para responder "¿dónde estaba esta unidad en marzo?".**/

class ContainerMovement extends Model
{
   use HasFactory;

    protected $guarded = ['id'];

    // Un movimiento no se modifica nunca, así que no tiene sentido
    // guardar "cuándo se modificó". Ver nota de migración más abajo.
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type'          => MovementType::class,
            'status_before' => ContainerStatus::class,
            'status_after'  => ContainerStatus::class,
            'moved_at'      => 'datetime',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function container()    { return $this->belongsTo(Container::class); }
    public function fromLocation() { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function toLocation()   { return $this->belongsTo(Location::class, 'to_location_id'); }
    public function fromDepot()    { return $this->belongsTo(Depot::class, 'from_depot_id'); }
    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * Qué originó el movimiento: puede ser una venta, un viaje, una
     * compra... El morph evita tener una columna por cada caso.
     */
    public function reference() { return $this->morphTo(); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** "En yarda → Vendido", para pintar la línea de tiempo. */
    public function getDescriptionAttribute(): string
    {
        return ($this->status_before?->label() ?? 'Registro inicial')
            .' → '
            .($this->status_after?->label() ?? '—');
    }
}
