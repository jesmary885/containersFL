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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            /* -------------------------------------------------------------
             | ÁMBITO
             |
             | null  = ajuste global, aplica a las dos compañías
             | 1 o 2 = ajuste propio de esa compañía, pisa al global
             |
             | Esto es lo que permite la cascada:
             |   ¿tiene la compañía su valor? -> ese
             |   ¿no? -> el global
             |   ¿tampoco? -> el default que trae el código
             * ---------------------------------------------------------- */
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();

            /* -------------------------------------------------------------
             | LA CLAVE
             |
             | group = la carpeta: 'fiscal', 'payments', 'rentals'
             | key   = el nombre: 'due_day', 'cc_fee_base'
             |
             | Se usa así: $company->setting('rentals', 'due_day', 5)
             * ---------------------------------------------------------- */
            $table->string('group', 50);
            $table->string('key', 100);

            /* -------------------------------------------------------------
             | EL VALOR
             |
             | JSON porque una sola tabla tiene que guardar números, textos,
             | booleanos y listas. La columna 'type' dice cómo interpretarlo
             | al leerlo de vuelta.
             * ---------------------------------------------------------- */
            $table->json('value')->nullable();
            $table->string('type', 20)->default('string'); // string|int|decimal|bool|json|array

            /* -------------------------------------------------------------
             | PARA LA PANTALLA DE CONFIGURACIÓN
             |
             | label      = el texto que ve el usuario
             | is_public  = si se puede mostrar en el frontend sin login
             * ---------------------------------------------------------- */
            $table->string('label', 150)->nullable();
            $table->boolean('is_public')->default(false);

            $table->timestamps();

            $table->unique(['company_id', 'group', 'key']);

            // Añadido: la pantalla de Configuración lista los ajustes
            // agrupados por carpeta. Sin este índice hace un escaneo
            // completo de la tabla en cada carga.
            $table->index(['group', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
