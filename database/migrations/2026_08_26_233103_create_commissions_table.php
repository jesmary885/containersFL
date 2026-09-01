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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();

            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();

            // restrict: no se puede borrar un usuario al que se le debe
            // comisión. Primero hay que liquidarla.
            $table->foreignId('salesperson_id')->constrained('users')->restrictOnDelete();

            /* -------------------------------------------------------------
             | POR CONTENEDOR
             |
             | Nullable porque a veces la comisión es por la venta
             | completa, no por unidad. Cuando se llena, permite comisionar
             | distinto según qué contenedor se vendió.
             * ---------------------------------------------------------- */
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();

            $table->date('sale_date');

            /* -------------------------------------------------------------
             | EL CÁLCULO
             |
             | base_amount = sobre qué monto se calculó
             | percent     = qué porcentaje se aplicó
             | amount      = cuánto quedó
             |
             | Se guardan los tres, aunque amount se pueda deducir de los
             | otros dos. ¿Por qué?
             |
             | Porque a veces el monto se ajusta a mano: se pactó otra
             | cosa, se redondeó, hubo un descuento. Guardando los tres,
             | siempre se puede explicar cómo se llegó a esa cifra, aunque
             | no cuadre con la multiplicación.
             * ---------------------------------------------------------- */
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount', 12, 2);

            // Derivados de los abonos, igual que en expenses.
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            // pending | partial | paid | cancelled
            $table->string('status', 20)->default('pending');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['salesperson_id', 'status']);   // "¿cuánto le debo a Juan?"
            $table->index(['company_id', 'sale_date']);    // comisiones del mes
        });

        Schema::create('commission_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->string('method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_payments');
        Schema::dropIfExists('commissions');
    }
};
