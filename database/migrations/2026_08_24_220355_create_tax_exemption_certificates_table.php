<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La prueba ante el estado de por qué no se le cobró impuesto a un cliente.
     */
    public function up(): void
    {
        Schema::create('tax_exemption_certificates', function (Blueprint $table) {
             $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // annual_resale  = revende, no consume
            // consumer_exempt = organización exenta (iglesia, gobierno)
            $table->string('type', 30)->default('annual_resale');

            $table->string('certificate_number', 40);

            /* -------------------------------------------------------------
             | VIGENCIA
             |
             | Estos certificados son ANUALES: valen del 1 de enero al 31
             | de diciembre. Cada año el cliente tiene que traer el nuevo.
             |
             | Por eso hay una fila por año, no se edita la vieja: si en
             | marzo Hacienda revisa una factura de febrero, tiene que
             | poder ver el certificado que estaba vigente en febrero.
             * ---------------------------------------------------------- */
            $table->smallInteger('issued_year');
            $table->date('valid_from');
            $table->date('valid_until');    // normalmente 12/31

            // active | expired | revoked
            $table->string('status', 20)->default('active');

            /* -------------------------------------------------------------
             | QUIÉN LO VALIDÓ
             |
             | No basta con que el cliente mande el papel: alguien tiene
             | que verificarlo contra Sunbiz. Queda registrado quién y
             | cuándo.
             * ---------------------------------------------------------- */
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()
                  ->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            /* -------------------------------------------------------------
             | ÍNDICES
             |
             | El unique impide cargar dos veces el mismo certificado del
             | mismo año para el mismo cliente.
             |
             | El segundo argumento ('tec_customer_number_year_unique') es
             | un nombre corto puesto a mano. MySQL solo admite 64
             | caracteres en el nombre de un índice, y el que Laravel
             | genera solo con estas tres columnas ya se pasa.
             * ---------------------------------------------------------- */
            $table->unique(
                ['customer_id', 'certificate_number', 'issued_year'],
                'tec_customer_number_year_unique',
            );

            $table->index(['customer_id', 'status']);  // ¿tiene alguno activo?
            $table->index('valid_until');              // ¿cuáles vencen pronto?
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_exemption_certificates');
    }
};
