<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuándo avisar de algo que vence (RB-027).
 *
 * Cada fila es una instrucción del tipo:
 *   "cuando falten 3 días para que venza una renta, mandar un email
 *    con la plantilla rental_due_reminder, repitiendo cada 2 días,
 *    máximo 3 veces".
 *
 * Está en base de datos y no en el código para que el cliente pueda
 * cambiar "avisar 30 días antes" por "45" sin llamarte.
 *
 * ─────────────────────────────────────────────────────────────────────
 * ESTE MODELO ESTABA ESCRITO CONTRA OTRA VERSIÓN DE LA TABLA
 *
 * Decía esto:                    y la tabla tiene esto:
 *   'days_before' => 'array'       offset_days   (entero con signo)
 *   'channels'    => 'array'       channel       (uno solo)
 *   'recipients'  => 'array'       — no existe —
 *
 * Los tres castes devolvían null, así que shouldNotifyAt() comparaba
 * contra un array vacío y NUNCA daba true: el sistema de avisos
 * habría quedado mudo sin lanzar un solo error.
 *
 * Sobre 'recipients': se quitó a propósito, no por olvido. RB-027 y
 * RB-028 dicen que el aviso va a TODOS los contactos registrados del
 * cliente, que pueden ser varios teléfonos y varios correos. Esa lista
 * vive en customer_contacts, donde el cliente la mantiene. Duplicarla
 * acá significaría tener que actualizarla en dos sitios.
 * ─────────────────────────────────────────────────────────────────────
 */
class NotificationRule extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            /*
             | El signo es la clave de todo:
             |   −3 = tres días ANTES del vencimiento
             |    0 = el mismo día
             |    5 = cinco días DESPUÉS (aviso de mora)
             |
             | Una sola columna cubre recordatorios y cobranza.
             */
            'offset_days'       => 'integer',

            'repeat_every_days' => 'integer',
            'max_repeats'       => 'integer',
            'is_active'         => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /** Null = la regla vale para las dos compañías. */
    public function company() { return $this->belongsTo(Company::class); }

    /** Los avisos que ya se mandaron por esta regla. */
    public function logs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Reglas de un evento concreto: rental_due, certificate_expiring... */
    public function scopeForEvent(Builder $q, string $event): Builder
    {
        return $q->where('event', $event);
    }

    /**
     * Reglas que aplican a una compañía: las suyas propias y las globales.
     *
     * Se usa en el comando diario de cobranza. El orderBy pone primero
     * las específicas, para que si una compañía tiene su propia regla
     * para el mismo evento, se pueda dar prioridad a esa.
     */
    public function scopeForCompany(Builder $q, ?int $companyId): Builder
    {
        return $q->where(fn (Builder $s) => $s
                ->where('company_id', $companyId)
                ->orWhereNull('company_id'))
            ->orderByRaw('company_id IS NULL');
    }

    /* =====================================================================
     | LA DECISIÓN
     * ================================================================== */

    /**
     * ¿Toca mandar el primer aviso hoy?
     *
     * $diasHastaVencimiento se cuenta desde hoy:
     *    3 = vence en tres días
     *    0 = vence hoy
     *   −5 = venció hace cinco días
     *
     * Y offset_days usa el mismo signo, así que la comparación es
     * directa: si la regla dice −3 (avisar tres días antes), coincide
     * cuando faltan 3 días, o sea cuando $diasHastaVencimiento vale 3.
     *
     * Por eso se compara contra el offset cambiado de signo.
     */
    public function shouldNotifyOn(int $diasHastaVencimiento): bool
    {
        return $diasHastaVencimiento === -$this->offset_days;
    }

    /**
     * ¿Toca una repetición hoy?
     *
     * Ejemplo: aviso de mora, repeat_every_days = 3, max_repeats = 5.
     * Se manda el día 1 de atraso, luego el 4, el 7, el 10 y el 13.
     * A partir de ahí se calla: ya se mandó cinco veces.
     *
     * $vecesEnviadas sale de contar las filas de notification_logs.
     */
    public function shouldRepeat(int $diasDesdeElPrimerAviso, int $vecesEnviadas): bool
    {
        if (! $this->repeat_every_days) {
            return false;   // esta regla no se repite
        }

        if ($this->max_repeats && $vecesEnviadas >= $this->max_repeats) {
            return false;   // ya se insistió lo suficiente
        }

        // Módulo: manda solo los días que caen justo en el múltiplo.
        return $diasDesdeElPrimerAviso > 0
            && $diasDesdeElPrimerAviso % $this->repeat_every_days === 0;
    }

    /** ¿Va por correo? El canal 'both' cuenta para los dos. */
    public function usesEmail(): bool
    {
        return in_array($this->channel, ['email', 'both'], true);
    }

    /** ¿Va por SMS? */
    public function usesSms(): bool
    {
        return in_array($this->channel, ['sms', 'both'], true);
    }
}
