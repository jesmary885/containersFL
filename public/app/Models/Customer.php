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

    /**
     * A quien hay que avisarle cuando este cliente debe.
     *
     * Devuelve una lista de destinos: cada uno con nombre, correo y
     * telefono. No devuelve contactos, devuelve DONDE mandar el aviso,
     * que no es lo mismo.
     *
     * -- QUE ESTABA MAL --
     *
     * Antes devolvia solo los contactos con `notify_reminders`. Si el
     * cliente no tenia ningun contacto cargado, devolvia una lista
     * vacia y el aviso no salia para ninguna parte, en silencio.
     *
     * Y el cliente sin contactos es el caso NORMAL: una persona natural
     * que compro un contenedor da su telefono y su correo, y no hay
     * ninguna "persona de pagos" que registrar.
     *
     * -- LO QUE PIDIO EL CLIENTE --
     *
     * Textual del segundo levantamiento, 14 de agosto:
     *
     *   "Normalmente el sistema va a utilizar los modos de comunicacion
     *   que tengamos archivados para ese cliente. Si hay un telefono y
     *   hay un email, una notificacion por email y un text message al
     *   cliente."
     *
     * O sea: se usa TODO lo que este archivado, no solo los contactos.
     *
     * -- EL ORDEN Y LOS REPETIDOS --
     *
     * Primero los contactos que lo pidieron, y despues el telefono y el
     * correo de la ficha si no los cubre ya ningun contacto.
     *
     * Lo de "si no los cubre ya" importa: es normal que el contacto
     * principal tenga el mismo correo que la ficha, y mandarle el mismo
     * aviso dos veces al mismo buzon hace que lo marquen como spam.
     */
    public function reminderRecipients()
    {
        return $this->destinosDeAviso('notify_reminders');
    }

    /**
     * Los mismos destinos, para mandar la factura.
     *
     * Existe aparte porque hay clientes donde la factura va a
     * contabilidad y la cobranza va al dueno.
     */
    public function invoiceRecipients()
    {
        return $this->destinosDeAviso('notify_invoices');
    }

    /**
     * El motor de los dos de arriba.
     *
     * Se descartan los destinos sin correo NI telefono. Un renglon al
     * que no hay por donde escribirle no es un destino: es una fila que
     * hace creer que el aviso salio.
     */
    protected function destinosDeAviso(string $bandera)
    {
        $destinos = $this->contacts()
            ->where($bandera, true)
            ->get()
            ->map(fn (CustomerContact $c) => [
                'nombre'   => $c->name,
                'email'    => $c->email,
                'telefono' => $c->phone,
                'origen'   => 'contacto',
            ]);

        $correosCubiertos   = $destinos->pluck('email')->filter()
                                       ->map(fn ($e) => strtolower($e))->all();

        $telefonosCubiertos = $destinos->pluck('telefono')->filter()->all();

        $faltaCorreo = filled($this->primary_email)
            && ! in_array(strtolower($this->primary_email), $correosCubiertos, true);

        $faltaTelefono = filled($this->primary_phone)
            && ! in_array($this->primary_phone, $telefonosCubiertos, true);

        if ($faltaCorreo || $faltaTelefono) {
            $destinos->push([
                'nombre'   => $this->name,
                'email'    => $faltaCorreo ? $this->primary_email : null,
                'telefono' => $faltaTelefono ? $this->primary_phone : null,
                'origen'   => 'ficha',
            ]);
        }

        return $destinos
            ->filter(fn (array $d) => filled($d['email']) || filled($d['telefono']))
            ->values();
    }

    /**
     * Hay por donde avisarle a este cliente?
     *
     * Para poder ensenar el aviso en la ficha antes de que haga falta,
     * en vez de descubrirlo el dia que una factura se vence y el aviso
     * no sale.
     */
    public function tieneComoAvisar(): bool
    {
        return $this->reminderRecipients()->isNotEmpty();
    }
}
