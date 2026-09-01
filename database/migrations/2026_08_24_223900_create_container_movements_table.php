<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
 * Historial de cada cambio de estado o ubicación de un contenedor.
 *
 * Es solo lectura: se escribe una vez y nunca se edita ni se borra.
 * Sirve para responder "¿dónde estaba esta unidad en marzo?".
 */
    public function up(): void
    {
        Schema::create('container_movements', function (Blueprint $table) {
              $table->id();
               $table->timestamp('created_at')->nullable();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();

            // receipt = llegó · transfer = cambió de yarda
            // delivery = salió al cliente · return = volvió
            // adjustment = corrección manual
            $table->string('type', 20);

            /* -------------------------------------------------------------
             | DE DÓNDE A DÓNDE
             |
             | Hay que decirle 'locations' a mano porque de
             | "from_location_id" Laravel no deduce la tabla.
             |
             | from_depot_id existe porque el primer movimiento suele ser
             | "salió del depósito del proveedor y entró a nuestra yarda":
             | el origen no es una location, es un depot.
             * ---------------------------------------------------------- */
            $table->foreignId('from_location_id')->nullable()
                  ->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()
                  ->constrained('locations')->nullOnDelete();
            $table->foreignId('from_depot_id')->nullable()
                  ->constrained('depots')->nullOnDelete();

            /* -------------------------------------------------------------
             | POR QUÉ SE MOVIÓ
             |
             | nullableMorphs crea dos columnas que pueden apuntar a
             | cualquier tabla:
             |   reference_type -> "App\Models\Sale"
             |   reference_id   -> 47
             |
             | Así un mismo movimiento puede colgar de una venta, una
             | renta, una compra o un viaje, sin necesitar cuatro columnas
             | distintas.
             |
             | La versión "nullable" permite movimientos sin origen: un
             | ajuste manual de inventario no viene de ningún documento.
             * ---------------------------------------------------------- */
            $table->nullableMorphs('reference');

            /* -------------------------------------------------------------
             | EL CAMBIO DE ESTADO
             |
             | Guardar el antes y el después es lo que permite reconstruir
             | la línea de tiempo completa sin adivinar.
             * ---------------------------------------------------------- */
            $table->string('status_before', 20)->nullable();
            $table->string('status_after', 20)->nullable();

            $table->timestamp('moved_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
           

            // La consulta de siempre: el historial de un contenedor,
            // del más reciente al más viejo.
            $table->index(['container_id', 'moved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_movements');
    }
};
