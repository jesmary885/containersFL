<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de conceptos facturables: contenedores, delivery, pickup,
 * modificaciones, recargos. Es lo que llena el desplegable de las
 * líneas del invoice.
 */

class Product extends Model
{
     use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type'          => ProductType::class,
            'default_price' => 'decimal:2',
            'taxable'    => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function invoiceItems()  { return $this->hasMany(InvoiceItem::class); }
    public function estimateItems() { return $this->hasMany(EstimateItem::class); }
    public function company()       { return $this->belongsTo(Company::class); }
    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('name');
    }

    public function scopeOfType(Builder $q, ProductType $type): Builder
    {
        return $q->where('type', $type);
    }

    /**
     * Los productos que puede usar una compañía: los suyos y los
     * compartidos (company_id null).
     */
    public function scopeForCompany(Builder $q, ?int $companyId): Builder
    {
        return $q->where(fn (Builder $s) => $s
            ->where('company_id', $companyId)
            ->orWhereNull('company_id'));
    }

    /**
     * Valores con los que se precarga la línea del invoice.
     * Todos editables ahí mismo.
     */
    public function lineDefaults(): array
    {
        return [
            'description' => $this->name,
            'unit_price'  => (float) ($this->default_price ?? 0),
            'taxable'     => (bool) $this->is_taxable,
            'quantity'    => 1,
        ];
    }
}
