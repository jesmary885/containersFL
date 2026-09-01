<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


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
            'next_service_at'         => 'date',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

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

    /** "2019 Freightliner Cascadia · ABC-1234" */
    public function getLabelAttribute(): string
    {
        return collect([$this->year, $this->make, $this->model])
            ->filter()->implode(' ')
            .($this->plate ? ' · '.$this->plate : '');
    }

    /** Documentos o servicio por vencer. */
    public function scopeNeedsAttention(Builder $q, int $days = 30): Builder
    {
        return $q->where(fn (Builder $s) => $s
            ->whereDate('registration_expires_at', '<=', now()->addDays($days))
            ->orWhereDate('insurance_expires_at', '<=', now()->addDays($days))
            ->orWhereDate('next_service_at', '<=', now()->addDays($days)));
    }
}
