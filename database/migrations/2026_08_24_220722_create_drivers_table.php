<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los choferes.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
             $table->id();

            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | ACCESO AL SISTEMA
             |
             | Nullable porque no todos los choferes van a tener usuario.
             | Si lo tienen, pueden entrar a ver sus viajes y sus
             | liquidaciones desde el celular.
             * ---------------------------------------------------------- */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            /* -------------------------------------------------------------
             | LICENCIA
             |
             | La fecha de vencimiento alimenta el aviso automático: un
             | chofer con licencia vencida no debería salir a ruta.
             * ---------------------------------------------------------- */
            $table->string('license_number', 50)->nullable();
            $table->date('license_expires_at')->nullable();

             /* -------------------------------------------------------------
             | CERTIFICADO MÉDICO  ← COLUMNA NUEVA
             |
             | El DOT medical card es obligatorio para conducir comercial.
             | Vence cada dos años (o antes, si el médico lo restringe).
             |
             | Va junto a la licencia porque las dos responden la misma
             | pregunta: ¿este chofer puede salir hoy? El scope
             | withExpiredDocs() del modelo consulta las dos a la vez.
             * ---------------------------------------------------------- */
            $table->date('medical_expires_at')->nullable();

            /* -------------------------------------------------------------
             | ALTA  ← COLUMNA NUEVA
             |
             | Cuándo empezó a trabajar. Hace falta para el 1099 anual
             | y para saber la antigüedad.
             * ---------------------------------------------------------- */
            $table->date('hired_at')->nullable();

            /* -------------------------------------------------------------
             | CUÁNTO GANA
             |
             | Nivel 2 de la cascada:
             |   1. global de la compañía (30%)
             |   2. este campo (si el chofer tiene su propio %)
             |   3. el viaje (donde se puede sobrescribir a mano)
             |
             | Se llenan uno o el otro, no los dos:
             |   default_pay_percent = cobra un % del viaje
             |   default_pay_amount  = cobra monto fijo por viaje
             * ---------------------------------------------------------- */
            $table->decimal('default_pay_percent', 5, 2)->nullable();
            $table->decimal('default_pay_amount', 12, 2)->nullable();

            // Los choferes suelen ser contratistas: entran en el 1099.
            $table->boolean('is_1099_reportable')->default(true);

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
        Schema::dropIfExists('drivers');
    }
};
