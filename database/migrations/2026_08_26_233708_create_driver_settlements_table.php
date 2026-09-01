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
        Schema::create('driver_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();

            $table->string('settlement_number', 20);

            // Qué semana cubre.
            $table->date('period_start');
            $table->date('period_end');

            /* -------------------------------------------------------------
             | LOS TRES TOTALES
             |
             | gross_amount      = suma de las líneas POSITIVAS (los viajes)
             | deductions_amount = suma de las NEGATIVAS, en valor absoluto
             | net_amount        = lo que se le paga de verdad
             |
             | Los tres son derivados: salen de sumar las líneas. Se
             | guardan para poder listar liquidaciones sin sumar cada vez.
             * ---------------------------------------------------------- */
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('deductions_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | ESTADO
             |
             | draft    = armándose, se pueden agregar viajes
             | approved = cerrada, ya no se toca
             | paid     = pagada
             | cancelled
             |
             | Aprobar es lo que marca los viajes como "settled" y evita
             | que entren en otra liquidación.
             * ---------------------------------------------------------- */
            $table->string('status', 20)->default('draft');

            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->date('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'settlement_number']);
            $table->index(['driver_id', 'period_start']);   // historial del chofer
            $table->index(['status', 'period_end']);        // "¿cuáles falta pagar?"
        });

        /* -----------------------------------------------------------------
         | LAS LÍNEAS
         |
         | El signo del monto decide todo:
         |   positivo = se le paga
         |   negativo = se le descuenta
         |
         | Es más simple que tener una columna "tipo" y andar sumando o
         | restando según el caso: se suma todo y el resultado es correcto.
         * -------------------------------------------------------------- */
        Schema::create('driver_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_settlement_id')->constrained()->cascadeOnDelete();

            // De qué viaje viene, si viene de uno.
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();

            // Para los adelantos: el gasto donde se registró el dinero
            // que ya se le dio.
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();

            // trip_pay | deduction | bonus | adjustment
            $table->string('type', 20)->default('trip_pay');

            $table->string('description', 255);
            $table->date('item_date')->nullable();

            // NEGATIVO para deducciones.
            $table->decimal('amount', 12, 2);

           // Al borrar el bloque, agregar este índice en su lugar:
            // es la consulta "las líneas de esta liquidación" y la de
            // "¿este viaje ya se liquidó?".
            
            $table->index(['driver_settlement_id']);
            $table->index(['trip_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_settlement_items');
        Schema::dropIfExists('driver_settlements');
    }
};
