<?php

namespace App\Models;

use App\Enums\ContainerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Container extends Model
{
    use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'              => ContainerStatus::class,
            'is_export_eligible'  => 'boolean',
            'acquisition_cost'    => 'decimal:2',
            'pickup_cost'         => 'decimal:2',
            'reconditioning_cost' => 'decimal:2',
            'csc_valid_through'   => 'date',
            'received_at'         => 'date',
            'sold_at'             => 'date',
        ];
    }

    /* =====================================================================
     | EVENTOS
     * ================================================================== */

    /**
     * Si el formulario no trajo compañías, se precargan con la dueña por
     * defecto. Evita contenedores huérfanos si alguien crea uno desde un
     * comando o una importación.
     */
    protected static function booted(): void
    {
        static::creating(function (Container $c) {
            if (empty($c->owner_company_id) || empty($c->billing_company_id)) {
                $default = Company::defaultContainerOwner();

                $c->owner_company_id   ??= $default->id;
                $c->billing_company_id ??= $c->owner_company_id;
            }
        });
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /** De quién es el contenedor: quien puso el dinero. */
    public function ownerCompany()
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    /** Quién lo vende o lo renta. Se precarga en el invoice y es editable ahí. */
    public function billingCompany()
    {
        return $this->belongsTo(Company::class, 'billing_company_id');
    }

    public function type()      { return $this->belongsTo(ContainerType::class, 'container_type_id'); }
    public function size()      { return $this->belongsTo(ContainerSize::class, 'container_size_id'); }
    public function condition() { return $this->belongsTo(ContainerCondition::class, 'container_condition_id'); }
    public function grade()     { return $this->belongsTo(ContainerGrade::class, 'container_grade_id'); }
    public function location()  { return $this->belongsTo(Location::class); }
    public function depot()     { return $this->belongsTo(Depot::class); }

    public function purchaseItem()       { return $this->belongsTo(PurchaseItem::class); }
    public function movements()          { return $this->hasMany(ContainerMovement::class); }
    public function exportCertificates() { return $this->hasMany(ExportCertificate::class); }
    public function expenses()           { return $this->hasMany(Expense::class); }
    public function documents()          { return $this->morphMany(Document::class, 'documentable'); }

    /** El pivot guarda el precio y el costo del día de la venta. */
    public function sales()
    {
        return $this->belongsToMany(Sale::class, 'sale_containers')
            ->withPivot('unit_price', 'cost_at_sale', 'released_at')
            ->withTimestamps();
    }

    public function rentals()
    {
        return $this->belongsToMany(Rental::class, 'rental_containers')
            ->withPivot('monthly_rate', 'from_date', 'to_date')
            ->withTimestamps();
    }

    /* =====================================================================
     | LECTURA — scopes
     * ================================================================== */

    /**
     * Disponible de verdad: en yarda Y sin venta ni renta activa.
     * El inventario se deriva de acá, nunca de un contador manual.
     */
    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('status', ContainerStatus::InYard)
            ->whereDoesntHave('sales', fn (Builder $s) => $s
                ->whereNotIn('sales.status', ['cancelled']))
            ->whereDoesntHave('rentals', fn (Builder $r) => $r
                ->where('rentals.status', 'active'));
    }

    public function scopeInYard(Builder $q): Builder     { return $q->where('status', ContainerStatus::InYard); }
    public function scopeAtSupplier(Builder $q): Builder { return $q->where('status', ContainerStatus::AtSupplier); }

    /** Apto para exportar: marcado como tal y con CSC vigente (o sin CSC). */
    public function scopeExportEligible(Builder $q): Builder
    {
        return $q->where('is_export_eligible', true)
            ->where(fn (Builder $s) => $s
                ->whereNull('csc_valid_through')
                ->orWhereDate('csc_valid_through', '>=', now()));
    }

    public function scopeForOwner(Builder $q, int $companyId): Builder
    {
        return $q->where('owner_company_id', $companyId);
    }

    /* =====================================================================
     | LECTURA — accessors
     * ================================================================== */

    /** Costo real puesto en yarda: compra + recogida + reacondicionamiento. */
    public function getTotalCostAttribute(): float
    {
        return (float) $this->acquisition_cost
             + (float) $this->pickup_cost
             + (float) $this->reconditioning_cost;
    }

    /** ISO si existe; si no, el código interno; si no, el id. */
    public function getFullIdentifierAttribute(): string
    {
        return $this->container_number ?: ($this->internal_code ?: '#'.$this->id);
    }

    /** "20FT · Usado · Cargo Worthy". Tolera catálogos vacíos. */
    public function getClassificationAttribute(): string
    {
        return collect([$this->size?->name, $this->condition?->name, $this->grade?->name])
            ->filter()
            ->implode(' · ');
    }
}
