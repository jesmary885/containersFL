<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los proveedores: de quién se compran los contenedores y a quién se le pagan servicios.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_number', 20)->unique();
            $table->string('name', 200);

            /* -------------------------------------------------------------
             | QUÉ TIPO DE PROVEEDOR
             |
             | container_supplier = vende contenedores
             | depot              = solo guarda y entrega
             | service            = reparación, pintura, transporte
             | materials          = repuestos, pintura, candados
             * ---------------------------------------------------------- */
            $table->string('type', 30)->default('container_supplier');

            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            // Acá sí en JSON: la dirección del proveedor no se imprime
            // en documentos ni se usa para buscar.
            $table->json('address')->nullable();

            /* -------------------------------------------------------------
             | 1099
             |
             | En EE.UU. hay que reportar al IRS lo que se le pagó a cada
             | proveedor independiente durante el año. Esta bandera dice
             | cuáles entran en ese reporte.
             * ---------------------------------------------------------- */
            $table->boolean('is_1099_reportable')->default(false);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
