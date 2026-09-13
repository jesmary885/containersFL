<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Autorización firmada del cliente para cobrar a su tarjeta.
 *
 * ⚠️ NUNCA guardar el número completo, el CVV ni la fecha de
 * vencimiento de la tarjeta. Solo los últimos 4 dígitos, la marca y
 * el token que devuelve la pasarela. Guardar el resto viola PCI-DSS.
*/ 

class CreditCardAuthorization extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Estos campos no salen nunca en un JSON, ni en un log de error,
     * ni en un dd(). Los tokens de Square permiten cobrar: tratarlos
     * como si fueran la tarjeta misma.
     */
    protected $hidden = ['square_customer_id', 'square_card_id'];

    protected function casts(): array
    {
        return [
            'billing_address'   => 'array',
            'authorized_amount' => 'decimal:2',
            'signed_at'         => 'date',
            'sunbiz_verified'   => 'boolean',
            'exp_month'         => 'integer',
            'exp_year'          => 'integer',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer()  { return $this->belongsTo(Customer::class); }
    public function payments()  { return $this->hasMany(Payment::class, 'credit_card_authorization_id'); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }

    /** El PDF firmado del formulario. Sin esto no se procesa (RB-011). */
    public function signatureDocument()
    {
        return $this->belongsTo(Document::class, 'signature_document_id');
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /**
     * Autorizaciones que se pueden usar hoy.
     * "Vigente" = estado activo Y la tarjeta no ha expirado.
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /** "Visa ····4242" para mostrar en pantalla. */
    public function getMaskedAttribute(): string
    {
        return ($this->card_brand ?? 'Tarjeta').' ····'.($this->card_last4 ?? '????');
    }

    /**
     * La tarjeta venció por fecha (no por estado).
     * Se compara contra el último día del mes de vencimiento.
     */
    public function isExpired(): bool
    {
        if (! $this->exp_month || ! $this->exp_year) {
            return false;   // sin datos no podemos afirmar que venció
        }

        return now()->startOfMonth()
            ->gt(now()->setDate($this->exp_year, $this->exp_month, 1));
    }

    /**
     * RB-012 + RB-013: solo se procesa tarjeta si está firmada Y
     * (es persona física presente O la compañía está verificada en Sunbiz).
     */
    public function isUsable(): bool
    {
        return $this->status === 'active'
            && $this->signed_at !== null
            && ! $this->isExpired();
    }
}
