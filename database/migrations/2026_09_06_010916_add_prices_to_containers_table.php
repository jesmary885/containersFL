<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════════════════
     * EL PRECIO DE VENTA Y DE RENTA DEL CONTENEDOR
     * ═══════════════════════════════════════════════════════════════════════
     *
     * La tabla containers tenía tres columnas de dinero —acquisition_cost,
     * pickup_cost y reconditioning_cost— y las tres son COSTOS: lo que nos
     * costó a nosotros.
     *
     * No había ninguna columna con lo que se le COBRA al cliente. Por eso
     * al elegir un contenedor en el presupuesto el precio salía en cero:
     * no es que no se conectara el evento, es que no había de dónde
     * sacarlo.
     *
     * ── POR QUÉ DOS COLUMNAS Y NO UNA ──
     *
     * El mismo contenedor se puede vender o rentar, y son dos números
     * distintos: uno son 2,400 de una vez, el otro son 175 al mes. Si
     * hubiera una sola columna, el sistema no sabría cuál de las dos
     * cosas está cotizando.
     *
     * ── LAS DOS SON NULLABLE, A PROPÓSITO ──
     *
     * Un contenedor que solo se renta no tiene precio de venta, y uno que
     * solo se vende no tiene renta mensual. Null significa "esta unidad no
     * se ofrece de esa forma", que es distinto de cero.
     *
     * Y cuando está en null el formulario no rellena nada: deja el precio
     * en blanco para que el vendedor lo escriba. Es mejor un campo vacío
     * que lo obliga a pensar, que un cero que se puede guardar por
     * distracción.
     *
     * ── SIGUE SIENDO UNA SUGERENCIA ──
     *
     * RB-029: el precio de venta no es fijo, varía por temporada y por
     * volumen de compra. Este número precarga la línea del presupuesto y
     * el vendedor lo puede cambiar ahí mismo. Una vez guardado el
     * presupuesto, el documento conserva SU precio, no vuelve nunca a
     * mirar esta columna.
     *
     * Eso es lo que permite subir la lista de precios en enero sin que
     * cambien los presupuestos de diciembre.
     * ═══════════════════════════════════════════════════════════════════════
     */
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {

            // Precio de venta de lista. Se precarga al elegir el
            // contenedor en una línea de "Venta de contenedor".
            $table->decimal('list_price', 12, 2)
                  ->nullable()
                  ->after('reconditioning_cost');

            // Renta mensual. Se precarga en una línea de "Renta de
            // contenedor" (RB-022: los ciclos son mensuales).
            $table->decimal('monthly_rate', 12, 2)
                  ->nullable()
                  ->after('list_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn(['list_price', 'monthly_rate']);
        });
    }
};
