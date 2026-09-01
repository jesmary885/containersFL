<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * Catálogo de categorías de gasto (RB-038).
 *
 * Está en base de datos y no en el código porque el cliente tiene que
 * poder agregar categorías sin llamarme.
 */

class ExpenseCategory extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active'              => 'boolean',

            /*
             | CAMBIO — antes decía 'default_1099_reportable'.
             | La columna real de la migración se llama 'is_1099_default'.
             |
             | Con el nombre viejo, el cast no hacía nada y el valor
             | llegaba como null a la pantalla de gastos: ninguna
             | categoría se marcaba nunca para el 1099.
             */
            'is_1099_default'        => 'boolean',

            /*
             | Este SÍ se queda, pero hay que agregar la columna.
             |
             | Distingue los gastos que SUBEN el costo del contenedor
             | (reacondicionamiento, pintura, reparación) de los que no
             | (peajes, papelería, seguro).
             |
             | Importa para el margen: si repintar una unidad costó $400,
             | ese dinero tiene que sumarse a su costo antes de calcular
             | cuánto se ganó al venderla. La tabla containers ya tiene
             | 'reconditioning_cost' esperando ese dato.
             */
            'affects_container_cost' => 'boolean',

            'parent_id'              => 'integer',
            'sort_order'             => 'integer',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function expenses() { return $this->hasMany(Expense::class); }

    /** La categoría de la que cuelga esta, si cuelga de alguna. */
    public function parent()   { return $this->belongsTo(self::class, 'parent_id'); }

    /** Las que cuelgan de esta. */
    public function children() { return $this->hasMany(self::class, 'parent_id'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /** Solo las de primer nivel, para armar el árbol del desplegable. */
    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    /**
     * Si los gastos de esta categoría suben el costo del contenedor
     * (reacondicionamiento, pintura) o no (peajes, oficina).
     */
    public function affectsCost(): bool
    {
        return (bool) $this->affects_container_cost;
    }

    /** Valor que se precarga en el gasto para el reporte anual. */
    public function defaultsTo1099(): bool
    {
        return (bool) $this->is_1099_default;
    }
}
