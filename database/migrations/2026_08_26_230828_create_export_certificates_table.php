<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El certificado CSC. Sin él un contenedor no puede cruzar frontera.
     */
    public function up(): void
    {
        Schema::create('export_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();

            /* -------------------------------------------------------------
             | LA VENTA Y LA FACTURA
             |
             | Sin constrained(): sales e invoices se crean en los bloques
             | 4 y 7, mucho después. Las llaves se agregan en el bloque 8.
             * ---------------------------------------------------------- */
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();

            /* -------------------------------------------------------------
             | EL CERTIFICADO
             |
             | Casi todo nullable menos valid_through, que es lo único
             | imprescindible: la fecha hasta la que sirve.
             |
             | A veces llega el certificado sin número visible o sin saber
             | quién lo emitió, y no por eso se puede dejar de registrar
             | la fecha.
             * ---------------------------------------------------------- */
            $table->string('certificate_number', 40)->nullable();
            $table->date('inspected_at')->nullable();
            $table->date('valid_through');
            $table->string('issuing_entity', 200)->nullable();

            // El PDF escaneado, en la tabla documents.
            $table->foreignId('document_id')->nullable()
                  ->constrained('documents')->nullOnDelete();

            // Cuándo se le mandó al cliente.
            $table->timestamp('sent_to_customer_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('container_id');
            $table->index('valid_through');   // "¿cuáles vencen pronto?"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_certificates');
    }
};
