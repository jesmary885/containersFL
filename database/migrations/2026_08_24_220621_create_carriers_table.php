<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
 * Transportista. Puede ser externo o la compañía propia de transporte,
 * que es el caso de FLCS: por eso company_id es opcional.
 */
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
             $table->id();

            /* -------------------------------------------------------------
             | SI ES DEL GRUPO
             |
             | Con valor = es la transportista propia (RS Transport).
             | Null     = es un transportista externo contratado.
             |
             | Esta distinción es lo que dispara la facturación
             | intercompañía: cuando RST mueve un contenedor de FLCHR,
             | tiene que emitirle factura.
             * ---------------------------------------------------------- */
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 200);
            $table->boolean('is_internal')->default(false);

            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            /* -------------------------------------------------------------
             | TARIFA
             |
             | Nivel intermedio de la cascada de precios:
             |   este campo -> si está vacío, el global de la compañía
             |
             | Es sugerencia: en el viaje se puede cambiar.
             * ---------------------------------------------------------- */
            $table->decimal('default_rate_per_mile', 12, 2)->nullable();

            /* -------------------------------------------------------------
             | SEGURO  ← COLUMNA NUEVA
             |
             | A un transportista externo no se le puede asignar un viaje
             | con el seguro vencido: si pasa algo en ruta, la
             | responsabilidad vuelve a quien lo contrató.
             |
             | Nullable porque la transportista interna (RS Transport)
             | lleva su seguro por otro lado, en la ficha de cada
             | vehículo.
             * ---------------------------------------------------------- */
            $table->date('insurance_expires_at')->nullable();

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
        Schema::dropIfExists('carriers');
    }
};
