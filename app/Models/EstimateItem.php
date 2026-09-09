<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EstimateItem extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount'     => 'decimal:2',
            'taxable'    => 'boolean',
            'miles'         => 'decimal:2',
            'rate_per_mile' => 'decimal:2',
        ];
    }

    /* =====================================================================
     | EVENTOS
     * ================================================================== */

    protected static function booted(): void
    {
        static::saving(function (EstimateItem $item) {
            $item->amount = round(
                (float) $item->quantity * (float) $item->unit_price,
                2,
            );
        });
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function estimate()  { return $this->belongsTo(Estimate::class); }
    public function product()   { return $this->belongsTo(Product::class); }
    public function container() { return $this->belongsTo(Container::class); }
}
