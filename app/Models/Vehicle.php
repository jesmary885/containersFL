<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Los camiones, chasis y montacargas.
 *
 * Se controlan porque un vehículo con el registro o el seguro vencido
 * no puede salir a ruta, y porque su mantenimiento es un gasto que hay
 * que poder atribuir a la unidad correcta.
 */

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active'               => 'boolean',
            'year'                    => 'integer',
            'registration_expires_at' => 'date',
            'insurance_expires_at'    => 'date',
       
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

      /** La compañía dueña del vehículo. La tabla tiene company_id. */
    public function company()   { return $this->belongsTo(Company::class); }

    public function carrier()   { return $this->belongsTo(Carrier::class); }
    public function trips()     { return $this->hasMany(Trip::class); }
    public function expenses()  { return $this->hasMany(Expense::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** "2019 Freightliner Cascadia · ABC-1234"
     * * El filter() quita los valores vacíos antes de unir, para que un
     * camión sin año no salga como " Freightliner Cascadia" con un
     * espacio de más al principio.
     */

    public function getLabelAttribute(): string
    {
        $descripcion = collect([$this->year, $this->make, $this->model])
            ->filter()
            ->implode(' ');

        return $descripcion . ($this->plate_number ? ' · ' . $this->plate_number : '');
    }

    /**
     * Vehículos con papeles por vencer dentro de los próximos N días.
     *
     * El where() con una función adentro envuelve las dos condiciones
     * en paréntesis. Sin eso, el OR se saldría del filtro y devolvería
     * también los vehículos que están perfectamente al día.
     */
    public function scopeNeedsAttention(Builder $q, int $days = 30): Builder
    {
        return $q->where(fn (Builder $s) => $s
            ->whereDate('registration_expires_at', '<=', now()->addDays($days))
            ->orWhereDate('insurance_expires_at', '<=', now()->addDays($days)));
    }
}
