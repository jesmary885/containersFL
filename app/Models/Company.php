<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;


/**
 * Las dos entidades legales: FLCHR (contenedores) y RS Transport
 * (transporte). RB-001.
 *
 * No son "sucursales": emiten documentos fiscales por separado, con su
 * propia numeración, su propio EIN y su propia plantilla de factura.
 */

class Company extends Model
{
    use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_instructions'          => 'array',
            'collects_sales_tax'            => 'boolean',
            'is_default_container_owner'    => 'boolean',
            'is_active'                     => 'boolean',
            'default_tax_rate'              => 'decimal:2',
            'credit_card_fee_percent'       => 'decimal:2',
            'resale_certificate_expires_at' => 'date',

            /*
             | COLUMNA NUEVA (RB-002 / RB-003).
             |
             | Marca cuál de las dos presta el servicio de transporte.
             | Sin este dato, la factura intercompañía semanal no sabe
             | quién la emite ni quién la recibe como gasto.
             */
            'is_default_transport_provider' => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /** Usuarios con acceso. El pivot marca cuál es su compañía por defecto. */
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function sequences() { return $this->hasMany(DocumentSequence::class); }
    public function settings()  { return $this->hasMany(Setting::class); }
    public function invoices()  { return $this->hasMany(Invoice::class); }
    public function carrier()   { return $this->hasOne(Carrier::class); }

    /** Contenedores que le pertenecen (quien puso el dinero). */
    public function ownedContainers()
    {
        return $this->hasMany(Container::class, 'owner_company_id');
    }

    /** Contenedores que ella factura, sea suyo o no. */
    public function billedContainers()
    {
        return $this->hasMany(Container::class, 'billing_company_id');
    }

    /**
     * NUEVO — Esta compañía vista como CLIENTE de la otra.
     *
     * Para que RS Transport pueda facturarle a FLCHR, FLCHR tiene que
     * existir como fila en la tabla de clientes. Esta columna cierra
     * el círculo:
     *
     *   companies.customer_id → customers.id → customers.related_company_id
     *
     * El CustomerSeeder ya crea ese cliente especial. Lo único que
     * faltaba era el puente de vuelta.
     */
    public function asCustomer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** La compañía que se precarga al registrar un contenedor (FLCHR). */
    public static function defaultContainerOwner(): self
    {
        return static::where('is_default_container_owner', true)->firstOrFail();
    }

    /**
     * NUEVO — La compañía que presta el transporte (RS Transport).
     *
     * Devuelve null en vez de reventar, a diferencia de la de arriba:
     * un sistema puede funcionar sin transportista interna (todo
     * subcontratado a externos), pero no sin dueño de contenedores.
     */
    public static function defaultTransportProvider(): ?self
    {
        return static::where('is_default_transport_provider', true)->first();
    }

    /** ¿Es esta la que factura los viajes? Para ocultar/mostrar menús. */
    public function isTransportProvider(): bool
    {
        return (bool) $this->is_default_transport_provider;
    }

    /**
     * Valor efectivo de una clave de configuración.
     * Cascada: compañía -> global -> default.
     */
    public function setting(string $group, string $key, mixed $default = null): mixed
    {
        return Setting::resolve($group, $key, $this->id, $default);
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Consume el siguiente número de una secuencia y lo devuelve formateado.
     *
     * lockForUpdate() bloquea la fila hasta el commit: si dos usuarios
     * facturan al mismo tiempo, el segundo espera. Sin esto salen dos
     * facturas con el mismo folio.
     *
     * NUNCA usar max(invoice_number)+1 ni count().
     *
     * Si ya estás dentro de una transacción (lo normal al crear un
     * invoice), Laravel usa un savepoint y el lock se libera con el
     * commit externo. Eso es lo correcto: si la factura falla, el
     * número no se pierde.
     */
    public function nextNumber(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $seq = DocumentSequence::where('company_id', $this->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->firstOrFail();

            // Reinicio anual, si la secuencia lo pide.
            if ($seq->resets_yearly && $seq->current_year !== (int) now()->year) {
                $seq->next_number  = 1;
                $seq->current_year = (int) now()->year;
            }

            $number = str_pad(
                (string) $seq->next_number,
                $seq->padding,
                '0',
                STR_PAD_LEFT,
            );

            $seq->next_number++;
            $seq->save();

            return ($seq->prefix ?? '').$number;
        });
    }



}
