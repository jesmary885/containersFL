<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yardas propias, puertos y sitios de cliente.
    **Los depósitos de proveedor ya no van acá**, tienen su propia tabla
    *(`depots`), porque necesitan cosas que una ubicación no tiene: fee de
    *recogida, plazo de retiro, fee diario por demora.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
             $table->id();

            /* -------------------------------------------------------------
             | DE QUIÉN ES
             |
             | Nullable porque un puerto o el sitio de un cliente no le
             | pertenecen a ninguna compañía. Solo las yardas propias
             | tienen dueño.
             * ---------------------------------------------------------- */
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 150);

            // yard = yarda propia · customer_site = terreno del cliente
            // port = puerto · other
            $table->string('type', 20)->default('yard');

            /* -------------------------------------------------------------
             | DÓNDE QUEDA
             |
             | Acá la dirección sí va en JSON (a diferencia de companies)
             | porque no se imprime en documentos ni se ordena por ella.
             | Solo se muestra completa.
             |
             | Las coordenadas sirven para calcular millas de los viajes.
             | decimal(10,7) da precisión de centímetros, más que suficiente.
             * ---------------------------------------------------------- */
            $table->json('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            /* -------------------------------------------------------------
             | ALMACENAJE
             |
             | Si un contenedor se queda más de los días libres en la
             | yarda, empieza a generar cobro diario.
             * ---------------------------------------------------------- */
            $table->decimal('daily_storage_fee', 12, 2)->nullable();
            $table->tinyInteger('free_storage_days')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Para los desplegables: "yardas activas".
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
