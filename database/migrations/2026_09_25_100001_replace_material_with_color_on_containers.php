<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        REUNIÓN 16-09-2026 · Denisse pidió reemplazar el campo "material" por
        "color", porque el material no le sirve para filtrar el inventario y
        el color sí: los contenedores se distinguen a simple vista en la yarda
        por amarillo, gris o azul.

        No se intenta convertir un valor en el otro: "acero" no es un color.
        Se agrega la columna nueva, se borra la vieja.

        La columna es un texto libre de 20 caracteres y no una tabla de
        catálogo, igual que era "material". Los valores permitidos viven en
        App\Enums\ContainerColor, que es donde se agregan colores nuevos sin
        tocar la base de datos.
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('container_grade_id');

            /*
             | Índice porque el filtro de color de la pantalla de inventario
             | consulta por esta columna, y el inventario pasa de 400 filas.
             */
            $table->index('color');
        });

        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn('material');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('material', 10)->nullable()->after('container_grade_id');
        });

        Schema::table('containers', function (Blueprint $table) {
            $table->dropIndex(['color']);
            $table->dropColumn('color');
        });
    }
};
