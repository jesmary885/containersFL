<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Enums\SupplierType;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type'               => SupplierType::class,
            'address'            => 'array',
            'is_active'          => 'boolean',
            'is_1099_reportable' => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function depots()    { return $this->hasMany(Depot::class); }
    public function purchases() { return $this->hasMany(Purchase::class); }
    public function expenses()  { return $this->hasMany(Expense::class); }
    public function prices()    { return $this->hasMany(SupplierPrice::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Precio vigente para una combinación exacta de catálogos.
     * Devuelve null si no hay precio cargado; ahí el usuario lo escribe.
     */
    public function currentPriceFor(
        int $typeId,
        int $sizeId,
        ?int $conditionId = null,
        ?int $gradeId = null,
        ?int $depotId = null,
    ): ?float {
        $price = $this->prices()
            ->validOn()
            ->where('container_type_id', $typeId)
            ->where('container_size_id', $sizeId)
            ->when($conditionId, fn ($q) => $q->where('container_condition_id', $conditionId))
            ->when($gradeId, fn ($q) => $q->where('container_grade_id', $gradeId))
            ->when($depotId, fn ($q) => $q->where('depot_id', $depotId))
            ->latest('valid_from')
            ->first();

        return $price ? (float) $price->price : null;
    }
}
