<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


/**
 * Los choferes.
 *
 * Casi siempre son contratistas independientes, no empleados: por eso
 * `is_1099_reportable` viene en true por defecto y por eso cobran un
 * porcentaje del viaje en vez de un sueldo (RB-032).
 */

class Driver extends Model
{
     use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'is_1099_reportable'  => 'boolean',
            'default_pay_percent' => 'decimal:2',
            'default_pay_amount'  => 'decimal:2',   // faltaba: también es dinero

            'license_expires_at'  => 'date',

            /*
             | Estos dos SE QUEDAN, pero hay que agregar las columnas a
             | la migración de drivers. No son un capricho:
             |
             |   medical_expires_at — el DOT medical card es obligatorio
             |   para conducir comercial en Florida. Un chofer con el
             |   certificado vencido no puede salir, y el scope de abajo
             |   (withExpiredDocs) es el que lo detecta antes de asignarle
             |   un viaje.
             |
             |   hired_at — la fecha de alta. Se necesita para el 1099 y
             |   para saber la antigüedad al calcular liquidaciones.
             */
            'medical_expires_at'  => 'date',
            'hired_at'            => 'date',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function carrier()     { return $this->belongsTo(Carrier::class); }
    public function user()        { return $this->belongsTo(User::class); }
    public function trips()       { return $this->hasMany(Trip::class); }
    public function settlements() { return $this->hasMany(DriverSettlement::class); }
    public function expenses()    { return $this->hasMany(Expense::class); }
    public function documents()   { return $this->morphMany(Document::class, 'documentable'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Licencia o certificado médico vencido: no debería salir a ruta.
     *
     * El where() con función adentro envuelve las dos condiciones en
     * paréntesis. Sin eso, el OR se saldría del filtro y devolvería
     * también los choferes que están bien.
     */
    public function scopeWithExpiredDocs(Builder $q): Builder
    {
        return $q->where(fn (Builder $s) => $s
            ->whereDate('license_expires_at', '<', now())
            ->orWhereDate('medical_expires_at', '<', now()));
    }

    /** Viajes completados que aún no entraron en una liquidación. */
    public function pendingTrips()
    {
        return $this->trips()->pendingDriverPayment()->get();
    }
}
