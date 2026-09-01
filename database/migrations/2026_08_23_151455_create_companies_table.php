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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('legal_name', 200);
            $table->string('slug', 50)->unique();
            $table->string('code', 10)->unique(); // FLCHR / RST

            $table->string('ein', 20)->nullable();
            $table->string('sales_tax_number', 30)->nullable();
            $table->string('resale_certificate_number', 30)->nullable();
            $table->date('resale_certificate_expires_at')->nullable();

            $table->boolean('collects_sales_tax')->default(true);
            $table->decimal('default_tax_rate', 5, 2)->default(7.00);
            $table->decimal('credit_card_fee_percent', 5, 2)->default(3.50);
            // Marca cuál es "la compañía de contenedores": se precarga al registrar un contenedor
            $table->boolean('is_default_container_owner')->default(false);

              /* -------------------------------------------------------------
             | QUIÉN HACE EL TRANSPORTE  ← COLUMNA NUEVA
             |
             | RB-002: FLCHR subcontrata todo su transporte a RS Transport.
             | RB-003: RS Transport le factura semanalmente.
             |
             | Para automatizar esa factura, el sistema tiene que saber
             | cuál de las dos es la transportista. Hoy solo sabía cuál
             | es la dueña de los contenedores.
             |
             | Sirve para dos cosas concretas:
             |   1. Precargar el transportista al crear un viaje de FLCHR.
             |   2. Saber quién EMITE la factura intercompañía y quién la
             |      recibe como gasto de "transportación".
             * ---------------------------------------------------------- */
            $table->boolean('is_default_transport_provider')->default(false);

            /* -------------------------------------------------------------
             | COLOR DE LA MARCA  ← COLUMNA NUEVA
             |
             | Hexadecimal, por ejemplo '#1B4D8F'.
             |
             | Se usa en dos sitios: la barra superior del sistema —para
             | que se vea de un golpe en qué empresa estás trabajando— y
             | el encabezado del PDF de la factura.
             |
             | Es la protección más barata contra el error más caro de un
             | sistema de dos empresas: emitir una factura desde la
             | compañía equivocada.
             * ---------------------------------------------------------- */
            $table->string('brand_color', 7)->nullable();

            $table->string('address_line1', 150)->nullable();
            $table->string('address_line2', 150)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->char('country', 2)->default('US');

            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 150)->nullable();
            $table->string('logo_path', 255)->nullable();

            $table->string('invoice_template', 50)->default('default');
            $table->text('invoice_footer_terms')->nullable();
            $table->json('payment_instructions')->nullable(); // Zelle, BoA acct/routing, SWIFT
            

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
