<?php

namespace App\Models;

use App\Enums\ContainerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *   1. Castes de list_price y monthly_rate (las columnas nuevas).
 *
 *   2. suggestedPrice(): un solo sitio que responde "cuánto vale esta
 *      unidad", según si se está vendiendo o rentando.
 *
 *   3. scopeSearch(): buscar por número ISO, código interno, medida,
 *      condición o calidad. Es lo que alimenta el buscador de unidades
 *      del presupuesto, que antes era un desplegable de 300 opciones.
 *
 *   4. scopeForBillingCompany(): ⚠️ ESTE ARREGLA UNA FUGA REAL.
 *
 *      El formulario de presupuesto llamaba a Container::available() sin
 *      filtrar por empresa. Container NO usa BelongsToCompany a propósito
 *      —es un maestro compartido— así que el desplegable mostraba las
 *      unidades de las DOS compañías.
 *
 *      Traducido: un presupuesto de RS Transport podía ofrecer
 *      contenedores de FLCHR. Y como los contenedores son de FLCHR
 *      (RB-001, RB-002), eso significa que casi todo el inventario
 *      aparecía donde no debía.
 * ═══════════════════════════════════════════════════════════════════════════
 */
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

            // NUEVAS: lo que se le cobra al cliente, no lo que nos costó.
            'list_price'          => 'decimal:2',
            'monthly_rate'        => 'decimal:2',

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

    /** Quién lo vende o lo renta. Se precarga en el documento y es editable. */
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
     * El inventario se deriva de acá, nunca de un contador manual (RB-019).
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

    /**
     * Las unidades que ESTA compañía puede facturar.
     *
     * Es distinto de forOwner(): el dueño es quien puso el dinero, el
     * facturador es quien lo vende. Casi siempre coinciden, pero pueden
     * no hacerlo, y para el presupuesto manda el facturador.
     *
     * Con null no filtra nada: es lo que pasa en un comando sin sesión, y
     * ahí es mejor devolver todo que devolver cero en silencio.
     */
    public function scopeForBillingCompany(Builder $q, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $q;
        }

        return $q->where('billing_company_id', $companyId);
    }

    /**
     * El buscador de unidades.
     *
     * Busca en lo que la gente dice por teléfono: el número ISO, el
     * código interno ("Unit #3", RB-042), o la descripción a secas
     * ("el 40 alto usado").
     */
    public function scopeSearch(Builder $q, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $q;
        }

        $t = '%'.trim($termino).'%';

        return $q->where(function (Builder $q) use ($t) {
            $q->where('container_number', 'like', $t)
              ->orWhere('internal_code', 'like', $t)
              ->orWhereHas('size', fn (Builder $s) => $s
                  ->where('name', 'like', $t)
                  ->orWhere('code', 'like', $t))
              ->orWhereHas('condition', fn (Builder $s) => $s->where('name', 'like', $t))
              ->orWhereHas('grade', fn (Builder $s) => $s->where('name', 'like', $t));
        });
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

    /* =====================================================================
     | EL PRECIO SUGERIDO
     * ================================================================== */

    /**
     * Cuánto vale esta unidad, según lo que se esté cotizando.
     *
     *     $contenedor->suggestedPrice()       -> precio de venta
     *     $contenedor->suggestedPrice(true)   -> renta mensual
     *
     * ── POR QUÉ DEVUELVE null Y NO 0 ──
     *
     * null significa "esta unidad no tiene precio cargado para eso". El
     * formulario, al recibir null, deja el campo del precio como estaba
     * en vez de escribir un cero.
     *
     * Un cero se guarda sin que nadie se dé cuenta y sale un presupuesto
     * de $0.00. Un campo vacío obliga al vendedor a escribir algo, y si
     * lo deja vacío la validación lo detiene.
     *
     * ── SIGUE SIENDO UNA SUGERENCIA (RB-029) ──
     *
     * El precio varía por temporada y por volumen. Esto solo precarga la
     * línea; el vendedor manda.
     */
    public function suggestedPrice(bool $renta = false): ?float
    {
        $valor = $renta ? $this->monthly_rate : $this->list_price;

        return $valor === null ? null : (float) $valor;
    }

    /**
     * El texto con el que se describe la unidad en la línea del
     * documento: "Contenedor 40FT High Cube · Usado · Cargo Worthy
     * (MSCU1234567)".
     *
     * Se arma aquí y no en la pantalla para que el presupuesto y la
     * factura escriban exactamente lo mismo.
     */
    public function lineDescription(): string
    {
        $partes = array_filter([
            'Contenedor '.($this->size?->name ?: ''),
            $this->condition?->name,
            $this->grade?->name,
        ], fn ($p) => trim((string) $p) !== '' && trim((string) $p) !== 'Contenedor');

        $texto = implode(' · ', $partes);

        if ($this->container_number) {
            $texto .= ' ('.$this->container_number.')';
        } elseif ($this->internal_code) {
            $texto .= ' ('.$this->internal_code.')';
        }

        return trim($texto) ?: 'Contenedor '.$this->full_identifier;
    }
}
