<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     /* -----------------------------------------------------------------
         | LOS PERÍODOS  ← el motor del semáforo
         |
         | Una fila por cada mes de renta. Se generan solas.
         |
         | NO sustituir esto por campos calculados en 'rentals'. Cada
         | período tiene su propio estado, su propia mora y su propia
         | factura. Sin filas separadas no hay forma de decir "marzo está
         | pagado, abril vencido y mayo condonado".
         * -------------------------------------------------------------- */
    public function up(): void
    {
        Schema::create('rental_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();

            // 1, 2, 3... el número de mes dentro del contrato.
            $table->unsignedInteger('period_number');

            /* -------------------------------------------------------------
             | LAS TRES FECHAS
             |
             | period_start / period_end = qué mes cubre
             | due_date                  = cuándo hay que pagarlo
             |
             | La fecha de pago NO es el fin del período: se cobra por
             | adelantado. El día concreto sale de la configuración
             | (rentals.due_day, default día 5).
             * ---------------------------------------------------------- */
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');

            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | LA MORA Y SU CONDONACIÓN
             |
             | El sistema SIEMPRE calcula la mora. Perdonarla es una
             | decisión humana que queda registrada: quién, cuándo y
             | por qué.
             |
             | Esto importa: sin registro, "no cobrar la mora" es
             | indistinguible de "olvidamos cobrarla".
             * ---------------------------------------------------------- */
            $table->boolean('late_fee_applied')->default(false);
            $table->decimal('late_fee_amount', 12, 2)->default(0);
            $table->timestamp('late_fee_waived_at')->nullable();
            $table->foreignId('late_fee_waived_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('waiver_reason', 255)->nullable();

            /* -------------------------------------------------------------
             | ESTADO — alimenta el semáforo
             |
             | pending  = generado, sin facturar
             | invoiced = facturado, sin cobrar
             | paid     = cobrado
             | overdue  = vencido
             | waived   = condonado
             |
             | Colores en pantalla:
             |   verde    = paid o waived
             |   amarillo = vencido pero dentro de los días de gracia
             |   rojo     = pasó la gracia
             * ---------------------------------------------------------- */
            $table->string('status', 20)->default('pending');

            // FK en el bloque 8: invoices todavía no existe.
            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // No puede haber dos "período 3" del mismo contrato.
            $table->unique(['rental_id', 'period_number']);

            // La consulta del comando diario: "¿qué está vencido hoy?"
            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_periods');
    }
};
