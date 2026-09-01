<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Cómo la llama el cliente: "Oficina", "Bodega Hialeah"
            $table->string('label', 50)->nullable();

            // billing = solo facturar · shipping = solo entregar · both
            $table->string('type', 20)->default('both');

            /* -------------------------------------------------------------
             | LA DIRECCIÓN
             |
             | Acá va en columnas sueltas, no en JSON, porque se imprime en
             | las facturas y hay que poder buscar por ciudad o estado.
             * ---------------------------------------------------------- */
            $table->string('line1', 150);
            $table->string('line2', 150)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->char('country', 2)->default('US');

            // Para calcular las millas del viaje de entrega.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            /* -------------------------------------------------------------
             | CUÁL SE PRECARGA
             |
             | Dos banderas separadas porque la dirección de facturación
             | suele ser distinta de la de entrega.
             * ---------------------------------------------------------- */
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
