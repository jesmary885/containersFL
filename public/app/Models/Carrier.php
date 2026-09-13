<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transportistas.
 *
 * Hay dos clases y la diferencia importa mucho:
 *
 *   - INTERNO  (is_internal = true, company_id lleno): es RS Transport.
 *              Sus viajes para FLCHR se acumulan y generan la factura
 *              intercompañía de cada semana (RB-002, RB-003).
 *
 *   - EXTERNO  (company_id vacío): una empresa de fuera que se contrata
 *              puntualmente. Sus viajes se pagan como gasto normal.
 */

class Carrier extends Model
{
     use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            /*
             | CAMBIO 1 — se quitó 'address' => 'array'.
             |
             | La tabla carriers no tiene columna de dirección y no hace
             | falta: al transportista no se le emite ningún documento
             | fiscal desde aquí. Si algún día se necesita para el 1099,
             | se agrega la columna Y el cast, los dos a la vez.
             |
             | Un cast a una columna que no existe no da error al arrancar,
             | pero devuelve null siempre y esconde el problema.
             */

            'is_internal'           => 'boolean',
            'is_active'             => 'boolean',
            'default_rate_per_mile' => 'decimal:2',

            /*
             | CAMBIO 2 — 'insurance_expires_at' SÍ se queda, pero hay
             | que agregar la columna a la migración de carriers.
             |
             | Motivo: a un transportista externo no se le puede dar un
             | viaje con el seguro vencido. Si pasa algo en ruta, la
             | responsabilidad vuelve a la empresa que lo contrató.
             | El método insuranceIsExpired() de abajo depende de esto.
             */
            'insurance_expires_at'  => 'date',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /** Solo si es la transportista propia (RS Transport). */
    public function company()  { return $this->belongsTo(Company::class); }

    public function drivers()  { return $this->hasMany(Driver::class); }
    public function vehicles() { return $this->hasMany(Vehicle::class); }
    public function trips()    { return $this->hasMany(Trip::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Los viajes de estos generan factura intercompañía (RB-003). */
    public function scopeInternal(Builder $q): Builder
    {
        return $q->where('is_internal', true);
    }

    /**
     * El seguro venció.
     *
     * El `?? false` cubre el caso de que la fecha esté vacía: sin dato
     * no podemos afirmar que venció, así que se responde "no".
     */
    public function insuranceIsExpired(): bool
    {
        return $this->insurance_expires_at?->isPast() ?? false;
    }
}
