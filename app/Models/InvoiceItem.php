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

            /*
             | LA FECHA DEL SERVICIO FALTABA, Y REVENTABA LA FACTURA.
             |
             | Sin esta linea, `service_date` llega a la vista como el
             | texto "2026-09-12". La vista hace ->format('d/m/Y'), que es
             | un metodo de Carbon, y PHP corta con:
             |
             |     Call to a member function format() on string
             |
             | El ?-> no protege de esto: comprueba que no sea null, no
             | que sea un objeto. Un texto pasa ese filtro y revienta en
             | la linea siguiente.
             |
             | Salia justo al abrir una factura con un renglon fechado, o
             | sea al convertir un presupuesto de transporte. La pantalla
             | de la factura no llegaba a dibujarse.
             */
            'service_date' => 'date',
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
