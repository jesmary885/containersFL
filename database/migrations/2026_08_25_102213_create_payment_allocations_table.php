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
         | LA ASIGNACIÓN
         |
         | Cuánto de UN pago se aplicó a UNA factura.
         * -------------------------------------------------------------- */

            Schema::create('payment_allocations', function (Blueprint $table) {
            // cascade: si se borra el pago, sus asignaciones se van.
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            // restrict: NO se puede borrar una factura que tiene pagos
            // aplicados. Primero hay que deshacer la asignación.
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->timestamp('allocated_at');
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un pago se aplica UNA vez a cada factura. Si hay que
            // cambiar el monto, se edita la fila existente.
            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
