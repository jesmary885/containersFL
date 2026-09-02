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
            'taxable'       => 'boolean',
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
     *
     * ══════════════════════════════════════════════════════════════════
     * AQUÍ ESTABA EL ERROR MÁS CARO DE TODO EL PROYECTO
     * ══════════════════════════════════════════════════════════════════
     *
     * La línea de abajo decía:
     *
     *     'taxable' => (bool) $this->is_taxable,
     *
     * pero la columna de la tabla se llama 'taxable', no 'is_taxable'.
     *
     * Qué pasaba, paso a paso:
     *
     *   1. $this->is_taxable buscaba una columna que no existe.
     *   2. Como no existe, PHP devolvía null (sin avisar de nada).
     *   3. (bool) null da false.
     *   4. Toda línea de factura nacía marcada como NO GRAVABLE.
     *
     * Traducido al negocio: el 7% de sales tax (RB-006) no se cobraba
     * en NINGUNA venta. Y no hay forma de darse cuenta mirando la
     * pantalla, porque no sale ningún error: simplemente el total es
     * más bajo, el cliente paga contento, y el problema aparece cuando
     * llega el reporte trimestral al estado de Florida y hay que pagar
     * de la propia bolsa un impuesto que nunca se le cobró a nadie.
     *
     * El cast de arriba ya estaba corregido; faltaba esta línea.
     * ══════════════════════════════════════════════════════════════════
     */
    public function lineDefaults(): array
    {
        return [
            'description' => $this->name,
            'unit_price'  => (float) ($this->default_price ?? 0),
            'taxable'     => (bool) $this->taxable,
            'quantity'    => 1,
        ];
    }
}
