<?php

namespace App\Models;

use App\Enums\RentalPeriodStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentalPeriod extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'             => RentalPeriodStatus::class,
            'period_start'       => 'date',
            'period_end'         => 'date',
            'due_date'           => 'date',
            'amount'             => 'decimal:2',
            'tax_amount'         => 'decimal:2',
            'late_fee_amount'    => 'decimal:2',
            'late_fee_applied'   => 'boolean',
            'late_fee_waived_at' => 'datetime',
            'paid_at'            => 'datetime',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function rental()  { return $this->belongsTo(Rental::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }

    /** Quién condonó la mora. Queda registrado, no se borra. */
    public function waivedBy()
    {
        return $this->belongsTo(User::class, 'late_fee_waived_by');
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /**
     * Semáforo de la pantalla de rentas.
     *   verde    = pagado o condonado
     *   amarillo = vencido pero dentro de los días de gracia
     *   rojo     = pasó la gracia
     */
    public function getSemaphoreAttribute(): string
    {
        if (in_array($this->status, [RentalPeriodStatus::Paid, RentalPeriodStatus::Waived], true)) {
            return 'green';
        }

        $graceEnd = $this->due_date->copy()->addDays($this->rental->grace_days ?? 5);

        return now()->startOfDay()->gt($graceEnd) ? 'red' : 'yellow';
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['paid', 'waived'])
            ->whereDate('due_date', '<', now());
    }

    public function scopeDueForNotification(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['paid', 'waived'])
            ->whereDate('due_date', '<=', now());
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * El sistema SIEMPRE calcula la mora. La decisión de perdonarla es
     * humana y queda registrada; no se resuelve "no cobrando".
     */
    public function applyLateFee(): static
    {
        if ($this->late_fee_applied || $this->status === RentalPeriodStatus::Paid) {
            return $this;
        }

        $this->late_fee_amount  = $this->rental->late_fee_amount;
        $this->late_fee_applied = true;
        $this->save();

        return $this;
    }

    /** Condonar exige usuario y motivo. Sin excepciones. */
    public function waiveLateFee(User $user, string $reason): static
    {
        $this->late_fee_waived_at = now();
        $this->late_fee_waived_by = $user->id;
        $this->waiver_reason      = $reason;
        $this->late_fee_amount    = 0;
        $this->save();

        return $this;
    }
}
