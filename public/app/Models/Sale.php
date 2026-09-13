<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use \App\Enums\SaleStatus;
use \App\Enums\UseType;
use \App\Enums\DeliveryMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory, \App\Models\Concerns\HasDocumentAddresses, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
             // 'use_type' distingue venta local de exportación.
        // Reemplaza al viejo booleano 'is_export': con un enum podemos
        // agregar un tercer caso mañana sin migrar la tabla.
        'use_type'                   => \App\Enums\UseType::class,
        'status'                     => \App\Enums\SaleStatus::class,
        'delivery_method'            => \App\Enums\DeliveryMethod::class,

        'sale_date'                  => 'date',
        'free_storage_until'         => 'date',

        // Direcciones congeladas del dia de la venta (RB-035). Mismos
        // nombres que estimate e invoice: convertir un documento en otro
        // es copiar valores, no traducir campos.
        'bill_to'                    => 'array',
        'ship_to'                    => 'array',

        // Los montos van desglosados: el precio consolidado que ve el
        // cliente se arma en el invoice, no acá (RB-007).
        'container_amount'           => 'decimal:2',
        'delivery_amount'            => 'decimal:2',
        'discount_amount'            => 'decimal:2',
        'deposit_amount'             => 'decimal:2',
        'tax_rate'                   => 'decimal:2',
        'commission_percent'         => 'decimal:2',
        'commission_amount'          => 'decimal:2',

        // Nota: 'miles' y 'rate_per_mile' NO van aca. Bajaron al renglon
        // (trips.miles / trips.rate_per_mile) porque una venta puede
        // tener tres destinos y cada viaje necesita los suyos.
        //
        // 'pickup_fee' vuelve: la recogida desde deposito es un requisito
        // del negocio, no una idea a medias.
        'pickup_fee'                 => 'decimal:2',

        'tax_exempt'                 => 'boolean',
        'requires_export_certificate' => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer()    { return $this->belongsTo(Customer::class); }
    public function estimate()    { return $this->belongsTo(Estimate::class); }

    /*
     | El deposito de donde se retira el contenedor.
     |
     | La habia comentado yo al ver que la columna estaba comentada en la
     | migracion. Era al reves: el levantamiento del 14 de agosto dice que
     | "las recogidas tienen un costo fijo asociado a los depositos", asi
     | que las dos columnas son un requisito, no una idea a medias.
     | Restauradas por la migracion ..._enable_depot_pickup_on_documents.
     */
    public function depot()       { return $this->belongsTo(Depot::class); }
    public function invoices()    { return $this->hasMany(Invoice::class); }
    public function trips()       { return $this->hasMany(Trip::class); }
    public function commissions() { return $this->hasMany(Commission::class); }
    public function documents()   { return $this->morphMany(Document::class, 'documentable'); }

    /**
     * El pivot guarda el precio Y el costo del día de la venta.
     * Congelar el costo es lo que permite calcular el margen real
     * aunque después cambien los costos del contenedor.
     */
    public function containers()
    {
        return $this->belongsToMany(Container::class, 'sale_containers')
            ->withPivot('unit_price', 'cost_at_sale', 'released_at')
            ->withTimestamps();
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Lo que se ganó: precio de venta menos costo congelado. */
    // public function getMarginAttribute(): float
    // {
    //     return (float) $this->containers->sum('pivot.unit_price')
    //          - (float) $this->containers->sum('pivot.cost_at_sale');
    // }


    // public function scopeForPeriod(Builder $q, $from, $to): Builder
    // {
    //     return $q->whereBetween('sale_date', [$from, $to]);
    // }

    /**
     * Ventas de exportación.
     * RB-016: toda venta de exportación exige certificado CSC.
     * RB-017: en el 98% de los casos NO llevan delivery.
     */
    public function scopeExport(Builder $q): Builder
    {
        return $q->where('use_type', \App\Enums\UseType::Export);
    }

    /** El certificado de exención que justifica no haber cobrado impuesto. */
    public function exemptionCertificate()
    {
        return $this->belongsTo(TaxExemptionCertificate::class, 'tax_exemption_certificate_id');
    }

    /** Quién hizo la venta. De acá sale la comisión (RB-030). */
    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    /** Certificados CSC emitidos para los contenedores de esta venta. */
    public function exportCertificates()
    {
        return $this->hasMany(ExportCertificate::class);
    }
}
