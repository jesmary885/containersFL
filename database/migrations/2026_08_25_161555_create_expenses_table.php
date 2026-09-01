<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Todo lo que sale. Un gasto puede pagarse en varias partes..
     */
    public function up(): void
    {
        /* -----------------------------------------------------------------
         | EL GASTO
         * -------------------------------------------------------------- */
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();

            // La categoría es obligatoria: un gasto sin clasificar no
            // sirve para ningún reporte.
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();

            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();

            $table->string('expense_number', 20);

            /* -------------------------------------------------------------
             | CUANDO EL ACREEDOR NO ESTÁ CARGADO
             |
             | En la hoja CUENTAS del Excel el acreedor se escribe libre.
             | Obligar a crear un proveedor por cada gasto suelto haría
             | que nadie cargue los gastos.
             |
             | Si supplier_id está vacío, se usa payee_name.
             * ---------------------------------------------------------- */
            $table->string('payee_name', 200)->nullable();

            $table->string('description', 255);
            $table->text('notes')->nullable();

            /* -------------------------------------------------------------
             | MONTOS
             |
             | paid_amount y balance son campos DERIVADOS: se recalculan
             | sumando los abonos. Un observer los mantiene al día.
             |
             | Nunca se escriben a mano.
             * ---------------------------------------------------------- */
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            $table->date('expense_date');
            $table->date('due_date')->nullable();

            // pending | approved | partial | paid | cancelled
            $table->string('status', 20)->default('pending');

            $table->date('paid_at')->nullable();
            $table->string('supplier_invoice_number', 50)->nullable();

            /* -------------------------------------------------------------
             | A QUÉ SE IMPUTA  ← cinco columnas, todas opcionales
             |
             | Un gasto puede colgar de muchas cosas distintas:
             |   container_id = reacondicionamiento -> SUBE el costo
             |                  del contenedor
             |   trip_id      = peaje, combustible del viaje
             |   vehicle_id   = mantenimiento del camión
             |   purchase_id  = fee por pasarse del plazo de retiro
             |   driver_id    = adelanto al chofer
             |
             | container_id es el más importante: es el que alimenta
             | reconditioning_cost y hace que el costo real del contenedor
             | sea el verdadero, no solo el precio de compra.
             * ---------------------------------------------------------- */
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | CLASIFICACIÓN
             |
             | is_billable        = se le va a recobrar al cliente
             | is_1099_reportable = entra en el reporte anual al IRS
             |
             | El segundo se precarga de la categoría y es editable.
             * ---------------------------------------------------------- */
            $table->boolean('is_billable')->default(false);
            $table->boolean('is_1099_reportable')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'expense_number']);
            $table->index(['company_id', 'expense_date']);          // gastos del mes
            $table->index(['status', 'due_date']);                  // cuentas por pagar
            $table->index('container_id');                          // costo real por unidad
            $table->index(['is_1099_reportable', 'expense_date']);  // reporte anual
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
