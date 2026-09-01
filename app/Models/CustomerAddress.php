<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


// Un cliente puede tener varias: la de facturación, la de entrega, y los
// distintos sitios de obra donde le dejan contenedores.

// > **Importante:** la factura NO apunta a esta tabla. Guarda una **copia**
// > de la dirección en su columna JSON `bill_to`. Así, si el cliente se muda
// > el año que viene, la factura de hoy sigue mostrando dónde estaba hoy.

class CustomerAddress extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_default_billing'  => 'boolean',
            'is_default_shipping' => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer() { return $this->belongsTo(Customer::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeBilling(Builder $q): Builder
    {
        return $q->where('is_default_billing', true);
    }

    public function scopeShipping(Builder $q): Builder
    {
        return $q->where('is_default_shipping', true);
    }

    /**
     * Dirección en una línea, para el PDF.
     * Salta las partes vacías sin dejar comas sueltas.
     */
    public function getFormattedAttribute(): string
    {
        return collect([
            $this->line1,
            $this->line2,
            $this->city,
            $this->state.' '.$this->postal_code,
        ])->map(fn ($p) => trim((string) $p))->filter()->implode(', ');
    }

    /**
     * Copia congelada para guardar en el invoice.
     * Si el cliente se muda después, la factura vieja conserva
     * la dirección que tenía ese día.
     */
    public function toSnapshot(): array
    {
        return $this->only([
            'label', 'line1', 'line2', 'city', 'state', 'postal_code', 'country',
        ]);
    }
}
