<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *

 *
 * Es el registro de cada aviso mandado. Responde las tres preguntas que
 * siempre aparecen cuando un cliente reclama:
 *
 *   "¿le avisamos?"            → existe la fila
 *   "¿le llegó?"               → status y sent_at
 *   "¿cuántas veces ya?"       → contar filas
 *
 * También es lo que impide mandar el mismo aviso dos veces si el comando
 * diario se ejecuta dos veces por error.
 */

class NotificationLog extends Model
{
     use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /**
     * Sobre qué se avisó: puede ser un período de renta, una factura,
     * un certificado o una compra. El morph evita una columna por cada
     * caso.
     */
    public function notifiable() { return $this->morphTo(); }

    public function rule()    { return $this->belongsTo(NotificationRule::class, 'notification_rule_id'); }
    public function contact() { return $this->belongsTo(CustomerContact::class, 'customer_contact_id'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Los que salieron bien. */
    public function scopeSent(Builder $q): Builder
    {
        return $q->where('status', 'sent');
    }

    /** Los que rebotaron: correo inválido, teléfono dado de baja. */
    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', 'failed');
    }
}
