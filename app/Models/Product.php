<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de conceptos facturables: contenedores, delivery, pickup,
 * modificaciones, recargos. Es lo que llena el desplegable de las
 * líneas del presupuesto y de la factura.
 *
 *
 *   1. usable_in: en qué documento puede aparecer cada concepto.
 *   2. scopeUsableIn(): el filtro que usa el desplegable.
 *   3. isRental() / isDelivery() / isPickup(): para que el formulario
 *      sepa de dónde sacar el precio sin escribir el código a mano en
 *      cinco sitios distintos.
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

    /**
     * El nombre que se muestra en el desplegable de conceptos.
     *
     * ══════════════════════════════════════════════════════════════════
     * POR QUE EXISTE ESTE ACCESSOR
     * ══════════════════════════════════════════════════════════════════
     *
     * form.blade.php pintaba {{ $producto->display_name }}. Esa columna
     * existe en CUSTOMERS, no en products: aca las columnas son 'name' y
     * 'name_en'. Eloquent no encontraba ni columna ni accessor, devolvia
     * null, y CADA <option> del desplegable salia vacio.
     *
     * El desplegable funcionaba: tenia sus once opciones y se podian
     * elegir. Solo que estaban todas en blanco, asi que elegir era
     * adivinar. Y como la fila de ZIP y millas solo aparece cuando la
     * linea es DELIVERY, tampoco habia forma de hacerla salir: para eso
     * habia que acertar a ciegas con la opcion correcta.
     *
     * De paso resuelve 'name_en', que estaba en la tabla y en el seeder
     * y no lo leia nadie: el catalogo ya era bilingue, faltaba usarlo.
     * ══════════════════════════════════════════════════════════════════
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => app()->getLocale() === 'en'
            ? ($this->name_en ?: $this->name)
            : $this->name);
    }

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
     * Los conceptos que se pueden poner en este tipo de documento.
     *
     *     Product::usableIn('estimate')   // presupuesto
     *     Product::usableIn('invoice')    // factura
     *
     * Los marcados 'both' salen siempre. Es lo que deja fuera del
     * presupuesto la mora, el almacenaje y el recargo de tarjeta, sin
     * borrarlos del catálogo (la factura los necesita).
     */
    public function scopeUsableIn(Builder $q, string $documento): Builder
    {
        return $q->whereIn('usable_in', [$documento, 'both']);
    }

    /* =====================================================================
     | LECTURA — qué es este concepto
     |
     | Se pregunta por el CÓDIGO y no por el nombre, porque el nombre lo
     | puede editar el usuario desde el catálogo y el código no.
     * ================================================================== */

    /** Renta de contenedor: el precio sale de monthly_rate de la unidad. */
    public function isRental(): bool
    {
        return $this->code === 'CONT-RENT';
    }

    /** Venta de contenedor: el precio sale de list_price de la unidad. */
    public function isSale(): bool
    {
        return $this->code === 'CONT-SALE';
    }

    /** Entrega: el importe se calcula millas × tarifa (RB-031). */
    public function isDelivery(): bool
    {
        return $this->code === 'DELIVERY';
    }

    /** Recogida: fee fijo del depósito (RB-031). */
    public function isPickup(): bool
    {
        return $this->code === 'PICKUP';
    }

    /**
     * Valores con los que se precarga la línea del documento.
     * Todos editables ahí mismo.
     *
     * ══════════════════════════════════════════════════════════════════
     * AQUÍ ESTABA EL ERROR MÁS CARO DE TODO EL PROYECTO
     * ══════════════════════════════════════════════════════════════════
     *
     * La línea de 'taxable' decía $this->is_taxable, pero la columna se
     * llama 'taxable'. PHP devolvía null, (bool) null da false, y TODA
     * línea nacía como no gravable: el 7% (RB-006) no se cobraba en
     * ninguna venta, sin que saliera ningún error.
     *
     * Ya está corregido. Se deja escrito para que no se repita.
     * ══════════════════════════════════════════════════════════════════
     */
    /**
     * El texto que se escribe solo en la descripcion del renglon.
     *
     * ══════════════════════════════════════════════════════════════════
     * POR QUE ESTA ACA Y NO EN EL FORMULARIO
     * ══════════════════════════════════════════════════════════════════
     *
     * El prefijo estaba escrito a mano dentro del Livewire y SOLO para
     * renta: 'Renta mensual · '.$texto. La venta se quedaba sin
     * ninguno, y por eso el EST-0004 salio impreso con un renglon que
     * dice "Contenedor 40 ft High Cube · Usado · Cargo Worthy" sin decir
     * en ningun lado que es una VENTA. El renglon de arriba si decia
     * "Renta mensual", asi que el documento parecia contradecirse solo.
     *
     * Ademas estaba en castellano dentro del codigo, con lo cual un
     * presupuesto en ingles habria salido con la mitad del renglon en
     * espanol.
     *
     * Ahora cada concepto sabe como se lee, y se lee en el idioma de la
     * sesion.
     * ══════════════════════════════════════════════════════════════════
     */
    /** ¿Es la renta de yarda, la que se cobra por dia? */
    public function isYardRental(): bool
    {
        return $this->code === 'YARD-RENT';
    }

    public function autoDescription(?Container $contenedor = null): string
    {
        $unidad = $contenedor?->lineDescription();

        if (! $unidad) {
            return $this->display_name;
        }

        return match (true) {
            $this->isRental()        => __('estimates.auto_rental', ['unit' => $unidad]),
            $this->isSale()          => __('estimates.auto_sale',   ['unit' => $unidad]),
            $this->code === 'REPAIR' => __('estimates.auto_repair', ['unit' => $unidad]),
            default                  => $this->display_name.' · '.$unidad,
        };
    }

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
