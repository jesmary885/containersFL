<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Van en la misma migración porque una compra sin líneas no existe.

        *Hay dos formas de comprar:

        *- **`single`** — se paga y se retira todo de una vez.
        *- **`release`** — se paga un lote y se va retirando por partes, con un
        *  plazo. Pasado el plazo, el depósito cobra por día.
     */
    public function up(): void
    {
             /* -----------------------------------------------------------------
         | LA COMPRA
         * -------------------------------------------------------------- */
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            /* -------------------------------------------------------------
             | QUIÉN COMPRA Y A QUIÉN
             |
             | restrictOnDelete en las dos: no se puede borrar una compañía
             | ni un proveedor que tenga compras registradas. Son
             | documentos con valor contable.
             * ---------------------------------------------------------- */
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();

            /* -------------------------------------------------------------
             | IDENTIFICACIÓN
             |
             | purchase_number = nuestro número interno, de la secuencia
             | reference       = el número del RELEASE que da el proveedor
             * ---------------------------------------------------------- */
            $table->string('purchase_number', 20);
            $table->string('type', 10)->default('single');   // single | release
            $table->string('reference', 50)->nullable();
            $table->date('purchase_date');

            /* -------------------------------------------------------------
             | EL DEPÓSITO Y SUS CONDICIONES
             |
             | pickup_fee y daily_late_fee se PRECARGAN del depósito, pero
             | quedan editables acá y se guardan en esta fila.
             |
             | ¿Por qué copiarlos en vez de leerlos del depósito cada vez?
             | Porque si el depósito sube el fee de 75 a 90 el mes que
             | viene, esta compra tiene que seguir mostrando los 75 que se
             | pagaron. El depósito es la sugerencia; la compra es el hecho.
             * ---------------------------------------------------------- */
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('pickup_fee', 12, 2)->default(0);
            $table->decimal('daily_late_fee', 12, 2)->nullable();

            /* -------------------------------------------------------------
             | EL PLAZO DE RETIRO
             |
             | Solo aplica a los releases.
             |
             | pickup_deadline_at   = la fecha límite VIGENTE
             | original_deadline_at = la que había al principio
             |
             | Se guardan las dos para poder auditar las prórrogas: si
             | alguien extendió el plazo, queda constancia de que se
             | extendió y desde cuándo.
             * ---------------------------------------------------------- */
            $table->date('pickup_deadline_at')->nullable();
            $table->date('original_deadline_at')->nullable();

            /* -------------------------------------------------------------
             | MONTOS
             * ---------------------------------------------------------- */
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | ESTADO
             |
             | draft | open | partially_received | received | cancelled
             |
             | NO se escribe a mano: se deriva de cuántas unidades se han
             | retirado. Eso lo hace refreshStatus() en el modelo, disparado
             | por un observer cada vez que cambia una línea.
             * ---------------------------------------------------------- */
            $table->string('status', 20)->default('open');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* -------------------------------------------------------------
             | ÍNDICES
             * ---------------------------------------------------------- */

            // Cada compañía numera sus compras aparte.
            $table->unique(['company_id', 'purchase_number']);

            // Pantalla "compras abiertas de esta compañía".
            $table->index(['company_id', 'status']);

            // Alerta "releases vencidos, el depósito está cobrando".
            $table->index('pickup_deadline_at');
        });

        /* -----------------------------------------------------------------
         | LAS LÍNEAS
         |
         | Una línea es "10 contenedores 40HC usados a 1,850".
         |
         | OJO: acá NO hay contenedores todavía. Los contenedores físicos
         | se crean uno por uno a medida que se van RETIRANDO del depósito.
         | Esa es la diferencia clave con el Excel: comprar no es tener.
         * -------------------------------------------------------------- */
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete: una línea sin compra no significa nada.
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();

            /* -------------------------------------------------------------
             | QUÉ SE COMPRÓ
             |
             | Acá sí se puede usar constrained(): los catálogos se
             | crearon en la migración 18, ya existen.
             * ---------------------------------------------------------- */
            $table->foreignId('container_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_size_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_condition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_grade_id')->nullable()->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | CUÁNTAS Y CUÁNTAS FALTAN  ← lo que arregla el conteo
             |
             | quantity          = cuántas se compraron
             | received_quantity = cuántas ya se retiraron del depósito
             |
             | La diferencia son las que siguen allá. Ese número es el que
             | hoy se pierde en el Excel.
             |
             | received_quantity NO se suma a mano: se cuenta cuántos
             | contenedores existen apuntando a esta línea. Contar en vez
             | de sumar es lo que impide que se desincronice.
             * ---------------------------------------------------------- */
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('received_quantity')->default(0);

            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['purchase_id']);

       
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Orden inverso: primero las líneas, después la compra.
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
