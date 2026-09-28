<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        REUNIÓN 16-09-2026 · Las tarifas de entrega van por RANGO de millas.

        Lo que dijo Denisse, textual de la minuta: las tarifas de milla varían
        según rangos — de 0 a 100 millas como variable, de 100 a 200 millas a
        4.50 dólares, y más de 200 millas a 5 dólares.

        Hasta hoy el sistema tenía UNA tarifa plana ($3.50) que se tecleaba a
        mano en cada renglón. Con rangos, la tarifa la propone el sistema en
        cuanto sabe cuántas millas son.

        ── POR QUÉ UNA TABLA Y NO TRES AJUSTES ──

        Porque los rangos cambian. Hoy son tres; mañana Denisse parte el
        primero en dos, o mete uno de más de 400. Con una tabla eso se hace
        desde la pantalla. Con tres claves de configuración habría que
        llamarnos, y en la reunión del 14 de agosto quedó dicho que esto
        tiene que ser administrable sin pedirle cambios al desarrollador.

        ── LOS LÍMITES SON [min, max) ──

        El mínimo entra, el máximo no. Así 100 millas cae en el rango de
        100-200 y no en los dos a la vez. Si fueran los dos inclusivos, una
        entrega de exactamente 100 millas podría cobrarse de dos formas
        distintas según el orden en que se consultara, y nadie sabría por
        qué dos cotizaciones iguales dieron números distintos.

        max_miles en NULL significa "de aquí en adelante": es el rango de
        más de 200 millas.

        ── POR QUÉ POR COMPAÑÍA ──

        Porque las dos facturan transporte. RST le cobra a FLCHR y también a
        terceros (Maritin, Ricardo), y no tienen por qué cobrar lo mismo.
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::create('delivery_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();

            $table->decimal('min_miles', 8, 2)->default(0);
            $table->decimal('max_miles', 8, 2)->nullable();   // null = sin tope

            $table->decimal('rate_per_mile', 8, 2);

            /*
             | El nombre que ve la gente: "0 a 100", "Más de 200".
             | Se guarda en vez de calcularse para que Denisse pueda
             | escribir "Área metropolitana" si eso le dice más.
             */
            $table->string('label', 60)->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            // "los rangos de esta compañía, en orden"
            $table->index(['company_id', 'is_active', 'min_miles'], 'delivery_rates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rates');
    }
};
