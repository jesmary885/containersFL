<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LA TARIFA DE ENTREGA, POR RANGO DE MILLAS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Reunión del 16 de septiembre: las tarifas de milla varían según rangos.
 * De 100 a 200 millas, $4.50. De 200 en adelante, $5.00. El primer rango
 * (0 a 100) quedó anotado como "variable" y está pendiente de confirmar.
 *
 * ── LOS LÍMITES ──
 *
 * min_miles entra, max_miles no. 100 millas cae en el rango 100-200.
 * max_miles en null es "de aquí en adelante".
 * ═══════════════════════════════════════════════════════════════════════════
 */
class DeliveryRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'min_miles'     => 'decimal:2',
            'max_miles'     => 'decimal:2',
            'rate_per_mile' => 'decimal:2',
            'is_active'     => 'boolean',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('min_miles');
    }

    /**
     * Los rangos que le tocan a esta compañía.
     *
     * Si la compañía tiene los suyos, se usan los suyos. Si no, los
     * generales (company_id null). Nunca los dos mezclados: mezclarlos
     * daría rangos superpuestos y una cotización distinta cada vez.
     */
    public function scopeForCompany(Builder $q, ?int $companyId): Builder
    {
        $tienePropios = $companyId
            && static::where('company_id', $companyId)->where('is_active', true)->exists();

        return $tienePropios
            ? $q->where('company_id', $companyId)
            : $q->whereNull('company_id');
    }

    /* =====================================================================
     | PRESENTACIÓN
     * ================================================================== */

    /**
     * "0 – 100 mi", "Más de 200 mi".
     *
     * Se usa el nombre que escribió el usuario si lo hay; si no, se arma
     * con los números, que siempre están.
     */
    public function getRangeLabelAttribute(): string
    {
        if ($this->label) {
            return $this->label;
        }

        $min = rtrim(rtrim(number_format((float) $this->min_miles, 1), '0'), '.');

        if ($this->max_miles === null) {
            return __('delivery_rates.over', ['n' => $min]);
        }

        $max = rtrim(rtrim(number_format((float) $this->max_miles, 1), '0'), '.');

        return $min.' – '.$max.' mi';
    }

    /** ¿Estas millas caen en este rango? [min, max) */
    public function covers(float $miles): bool
    {
        if ($miles < (float) $this->min_miles) {
            return false;
        }

        return $this->max_miles === null || $miles < (float) $this->max_miles;
    }
}
