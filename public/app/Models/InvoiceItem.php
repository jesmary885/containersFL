<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
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
        ];
    }

    /* =====================================================================
     | EVENTOS
     * ================================================================== */

    /**
     * El monto SIEMPRE se calcula, nunca se escribe a mano.
     * Evita líneas donde cantidad × precio no cuadra con el total.
     */
    protected static function booted(): void
    {
        static::saving(function (InvoiceItem $item) {
            $item->amount = round(
                (float) $item->quantity * (float) $item->unit_price,
                2,
            );
        });
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function invoice()   { return $this->belongsTo(Invoice::class); }
    public function product()   { return $this->belongsTo(Product::class); }
    public function container() { return $this->belongsTo(Container::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /**
     * Si esta línea forma parte de un grupo que se imprime como un solo
     * precio (contenedor + delivery = un renglón para el cliente).
     */
    public function isBundled(): bool
    {
        return ! empty($this->bundle_key);
    }
}
