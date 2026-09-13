<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \App\Enums\CertificateStatus;

/**
 * Certificado que exime al cliente de pagar sales tax.
 *
 * Es la prueba ante el estado de por qué no se cobró impuesto en una
 * factura. Por eso el invoice guarda el id del certificado: si vence
 * después, la factura vieja conserva su respaldo.
 */

class TaxExemptionCertificate extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'      => CertificateStatus::class,
            'valid_from'  => 'date',
            'valid_until' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer()   { return $this->belongsTo(Customer::class); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }
    public function invoices()   { return $this->hasMany(Invoice::class, 'tax_exemption_certificate_id'); }
    public function documents()  { return $this->morphMany(Document::class, 'documentable'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Vigente a una fecha concreta, no necesariamente hoy. */
    public function scopeValidOn(Builder $q, ?Carbon $date = null): Builder
    {
        $date ??= now();

        return $q->where('status', 'active')
            ->whereDate('valid_from', '<=', $date)
            ->whereDate('valid_until', '>=', $date);
    }

    public function scopeExpiringSoon(Builder $q, int $days = 30): Builder
    {
        return $q->where('status', 'active')
            ->whereDate('valid_until', '>=', now())
            ->whereDate('valid_until', '<=', now()->addDays($days));
    }

    public function isValid(?Carbon $date = null): bool
    {
        $date ??= now();

        return $this->status === 'active'
            && $this->valid_from->lte($date)
            && $this->valid_until->gte($date);
    }

    /** Días que faltan para vencer. Negativo si ya venció. */
    public function getDaysUntilExpiryAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->valid_until, false);
    }
}
