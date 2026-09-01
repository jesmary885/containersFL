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

     /* -----------------------------------------------------------------
         | LAS LÍNEAS
         * -------------------------------------------------------------- */

        Schema::create('invoice_items', function (Blueprint $table) {
             $table->id();

            /* -------------------------------------------------------------
             | A qué factura pertenece
             |
             | cascadeOnDelete: si se borra la factura, las líneas se van
             | con ella. Una línea sin factura no significa nada.
             * ---------------------------------------------------------- */

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->smallInteger('line_number')->default(1);

            /* -------------------------------------------------------------
             | QUÉ SE COBRA
             |
             | trip_id es para la factura intercompañía: cada línea es un
             | viaje que RST le cobra a FLCHR, y así queda el detalle de
             | qué viajes cubre.
             * ---------------------------------------------------------- */
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();

            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | EL IMPUESTO VA POR LÍNEA, NO POR FACTURA
             |
             | Acá está la pieza que hace funcionar todo el cálculo:
             | dentro de la misma factura, el contenedor paga tax y el
             | delivery no.
             * ---------------------------------------------------------- */
            $table->boolean('taxable')->default(false);

            /* -------------------------------------------------------------
             | AGRUPACIÓN PARA IMPRIMIR
             |
             | Las líneas con el mismo bundle_key se muestran al cliente
             | como UN renglón con el precio sumado, conservando por
             | dentro su propio 'taxable'.
             |
             |   Interno:  Contenedor 40HC  2,400  gravable
             |             Delivery           350  no gravable
             |
             |   Cliente:  Contenedor 40HC entregado ... 2,750
             |
             |   Tax:      solo sobre 2,400
             |
             | 36 caracteres = el largo de un UUID.
             * ---------------------------------------------------------- */
            $table->string('bundle_key', 36)->nullable();
            $table->string('bundle_description', 255)->nullable();

            // Para facturas de servicio: qué día se prestó.
            $table->date('service_date')->nullable();

            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);   // pintar la factura
            $table->index('bundle_key');                   // agrupar al imprimir
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
