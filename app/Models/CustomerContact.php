<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * Personas dentro de la empresa cliente.
 * Es común que llamen dando el nombre del empleado, no el de la empresa,
 * por eso el buscador de clientes también busca acá.
 */

class CustomerContact extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_primary'       => 'boolean',
            'notify_reminders' => 'boolean',
            'notify_invoices'  => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer() { return $this->belongsTo(Customer::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopePrimary(Builder $q): Builder
    {
        return $q->where('is_primary', true);
    }

    /** A quién se le mandan los recordatorios de cobro. */
    public function scopeForReminders(Builder $q): Builder
    {
        return $q->where('notify_reminders', true)->whereNotNull('email');
    }
}
