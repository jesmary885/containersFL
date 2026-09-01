<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El catálogo de todo lo que se puede facturar.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
              $table->id();

            /* -------------------------------------------------------------
             | DE QUIÉN ES
             |
             | Nullable = producto compartido por las dos compañías.
             | Con valor = solo aparece en el catálogo de esa compañía.
             * ---------------------------------------------------------- */
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('name_en', 150)->nullable();

            /* -------------------------------------------------------------
             | QUÉ ES
             |
             | container = el contenedor en sí
             | service   = delivery, pickup, modificación
             | fee       = recargo de tarjeta, mora, fee de depósito
             | part      = repuesto, candado, piso
             * ---------------------------------------------------------- */
            $table->string('type', 20)->default('service');

            /* -------------------------------------------------------------
             | VALORES QUE SE PRECARGAN EN LA FACTURA
             |
             | Los dos son SUGERENCIAS. En la línea de la factura se pueden
             | cambiar, y una vez guardada, la factura conserva lo suyo.
             |
             | 'taxable' default false porque la mayoría del catálogo son
             | servicios (delivery, pickup), y en Florida el transporte
             | no paga sales tax. Los contenedores sí: esos se marcan a mano.
             * ---------------------------------------------------------- */
            $table->decimal('default_price', 12, 2)->nullable();
            $table->boolean('taxable')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
