<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Enums\CustomerType;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type'               => CustomerType::class,
            'tax_exempt'         => 'boolean',
            'sunbiz_verified'    => 'boolean',
            'allow_credit_card'  => 'boolean',
            'credit_hold'        => 'boolean',
            'is_active'          => 'boolean',
            'sunbiz_verified_at' => 'datetime',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function contacts()     { return $this->hasMany(CustomerContact::class); }
    public function addresses()    { return $this->hasMany(CustomerAddress::class); }
    public function certificates() { return $this->hasMany(TaxExemptionCertificate::class); }
    public function cardAuths()    { return $this->hasMany(CreditCardAuthorization::class); }
    public function estimates()    { return $this->hasMany(Estimate::class); }
    public function sales()        { return $this->hasMany(Sale::class); }
    public function invoices()     { return $this->hasMany(Invoice::class); }
    public function rentals()      { return $this->hasMany(Rental::class); }
    public function payments()     { return $this->hasMany(Payment::class); }
    public function documents()    { return $this->morphMany(Document::class, 'documentable'); }

    /** Si este cliente es en realidad la otra compañía (facturación interna). */
    public function relatedCompany()
    {
        return $this->belongsTo(Company::class, 'related_company_id');
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Nombre a mostrar: alias -> razón social -> nombre y apellido. */
    public function getNameAttribute(): string
    {
        return $this->display_name
            ?: ($this->company_name ?: trim($this->first_name.' '.$this->last_name));
    }

    public function scopeActive(Builder $q): Builder    { return $q->where('is_active', true); }
    public function scopeTaxExempt(Builder $q): Builder { return $q->where('tax_exempt', true); }

    /**
     * Busca por nombre, número, teléfono, email Y por los datos de los
     * contactos. Es común que llamen dando el nombre del empleado, no
     * el de la empresa.
     */
    public function scopeSearch(Builder $q, string $term): Builder
    {
        $t = '%'.$term.'%';

        return $q->where(function (Builder $q) use ($t) {
            $q->where('display_name', 'like', $t)
              ->orWhere('company_name', 'like', $t)
              ->orWhere('customer_number', 'like', $t)
              ->orWhere('primary_phone', 'like', $t)
              ->orWhere('primary_email', 'like', $t)
              ->orWhereHas('contacts', fn (Builder $c) => $c
                  ->where('name', 'like', $t)
                  ->orWhere('email', 'like', $t)
                  ->orWhere('phone', 'like', $t));
        });
    }

    /**
     * Certificado de exención vigente a una fecha.
     * El id que devuelve se guarda EN el invoice como prueba de por qué
     * no se cobró impuesto. Si el certificado se vence después, la
     * factura vieja conserva su respaldo.
     */
    public function activeExemptionCertificate(?Carbon $date = null): ?TaxExemptionCertificate
    {
        $date ??= now();

        return $this->certificates()
            ->where('status', 'active')
            ->whereDate('valid_from', '<=', $date)
            ->whereDate('valid_until', '>=', $date)
            ->latest('valid_until')
            ->first();
    }

    /** Contactos que aceptaron recibir recordatorios de cobro. */
    public function reminderRecipients()
    {
        return $this->contacts()->where('notify_reminders', true)->get();
    }
}
