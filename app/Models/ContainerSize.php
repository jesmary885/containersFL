<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContainerSize extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'length_ft' => 'decimal:1',
            'is_high_cube' => 'boolean'
        ];
    }

     /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function containers()    { return $this->hasMany(Container::class); }
    public function purchaseItems() { return $this->hasMany(PurchaseItem::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /**
     * Lo que se muestra en los selects: activos, en el orden que el
     * cliente definió, y alfabético como desempate.
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
