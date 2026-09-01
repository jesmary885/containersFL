<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     /* -----------------------------------------------------------------
         | QUÉ CONTENEDORES SE VENDIERON
         |
         | Tabla puente: una venta puede llevar varios contenedores, y
         | cada uno con su precio.
         * -------------------------------------------------------------- */

    public function up(): void
    {
        Schema::create('sale_containers', function (Blueprint $table) {
            $table->id();

            // cascade: si se borra la venta, esta línea no significa nada.
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            // restrict: NO se puede borrar un contenedor que está vendido.
            $table->foreignId('container_id')->constrained()->restrictOnDelete();

            $table->decimal('unit_price', 12, 2);

            /* -------------------------------------------------------------
             | EL COSTO CONGELADO  ← la clave del margen real
             |
             | Se copia el costo total del contenedor (compra + recogida +
             | reacondicionamiento) el día que se vende.
             |
             | ¿Por qué copiarlo si está en la tabla containers?
             |
             | Porque si el mes que viene se le hace una reparación más y
             | su costo sube, esta venta seguiría calculando el margen
             | contra el costo NUEVO, y daría un margen equivocado.
             |
             | Congelado acá, el margen de esta venta es para siempre
             | unit_price − cost_at_sale.
             * ---------------------------------------------------------- */
            $table->decimal('cost_at_sale', 12, 2)->nullable();

            // Cuándo se entregó físicamente esta unidad. Nullable porque
            // se puede vender hoy y entregar la semana que viene.
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            // El mismo contenedor no puede estar dos veces en la misma venta.
            $table->unique(['sale_id', 'container_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_containers');
    }
};
