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
         | LOS VEHÍCULOS
         * -------------------------------------------------------------- */
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: no se puede borrar una compañía que tiene
            // camiones. Obliga a resolver primero qué pasa con ellos.
            $table->foreignId('company_id')->constrained()->restrictOnDelete();

            $table->foreignId('carrier_id')->nullable()->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | IDENTIFICACIÓN
             |
             | El VIN es el número de serie del vehículo. Siempre son
             | exactamente 17 caracteres, y es único en el mundo.
             |
             | Nullable porque un chasis o un montacargas puede no tenerlo.
             * ---------------------------------------------------------- */
            $table->string('vin', 17)->nullable()->unique();
            $table->string('plate_number', 15)->nullable();

            // truck | trailer | chassis | forklift
            $table->string('type', 30)->nullable();

            $table->string('make', 50)->nullable();    // Freightliner
            $table->string('model', 50)->nullable();   // Cascadia
            $table->smallInteger('year')->nullable();

            /* -------------------------------------------------------------
             | VENCIMIENTOS
             |
             | Circular sin registro o sin seguro vigente es un problema
             | serio. Estas fechas alimentan los avisos.
             * ---------------------------------------------------------- */
            $table->date('registration_expires_at')->nullable();
            $table->date('insurance_expires_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        /* -----------------------------------------------------------------
         | EL MANTENIMIENTO
         * -------------------------------------------------------------- */
        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete: si se borra el camión, su historial se va
            // con él. No sirve de nada suelto.
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            $table->string('type', 50);              // "Cambio de aceite"
            $table->text('description')->nullable();
            $table->decimal('cost', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | CUÁNDO
             |
             | performed_at = cuándo se hizo
             | next_due_at  = cuándo toca el próximo
             | odometer     = con cuántas millas se hizo
             * ---------------------------------------------------------- */
            $table->date('performed_at');
            $table->date('next_due_at')->nullable();
            $table->unsignedInteger('odometer')->nullable();

            /* -------------------------------------------------------------
             | EL GASTO ASOCIADO
             |
             | Sin constrained(): la tabla expenses se crea mucho más
             | adelante (bloque 7). La llave foránea se agrega en el
             | bloque 8.
             * ---------------------------------------------------------- */
            $table->unsignedBigInteger('expense_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Para el panel de "mantenimientos por vencer".
            $table->index('next_due_at');
        });
  
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenances');
        Schema::dropIfExists('vehicles');
    }
};
