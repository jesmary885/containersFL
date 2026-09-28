<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        REUNIÓN 16-09-2026 · Marcado de reparaciones.

        Denisse preguntó cómo registrar las reparaciones de los contenedores
        que llegan cada semana. Lo que se acordó fue lo más simple posible:
        una marca de sí o no al recibir la unidad, como las pizarritas que
        usan en la yarda, y después se actualiza en el sistema.

        Deliberadamente NO es un módulo de reparaciones. No hay órdenes de
        trabajo, ni repuestos, ni horas. Eso es otra cosa y no se pidió.

        Son dos columnas:

          needs_repair    la marca. Es la que filtra la pantalla de
                          inventario: "enséñame lo que hay por arreglar".

          repair_notes    qué tiene. Texto libre corto, porque en la yarda
                          se escribe "puerta izquierda" y no un catálogo de
                          daños.

        ── Por qué no se reutilizó condition_notes ──

        Porque condition_notes describe cómo llegó la unidad y no cambia.
        repair_notes describe lo que falta por hacer y se vacía cuando se
        hizo. Mezclarlas obligaría a borrar el historial para marcar que ya
        se arregló.
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->boolean('needs_repair')->default(false)->after('condition_notes');
            $table->string('repair_notes', 255)->nullable()->after('needs_repair');

            /*
             | Índice compuesto: la consulta real nunca es "todas las que
             | necesitan reparación" sino "las de ESTA compañía que
             | necesitan reparación".
             */
            $table->index(['owner_company_id', 'needs_repair'], 'cont_owner_repair_idx');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropIndex('cont_owner_repair_idx');
            $table->dropColumn(['needs_repair', 'repair_notes']);
        });
    }
};
