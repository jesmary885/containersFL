<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL USO PREVISTO, POR RENGLON DEL PRESUPUESTO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Lo mismo que se hizo en invoice_items, por la misma razon: un
 * presupuesto puede llevar tres contenedores con tres destinos distintos.
 *
 * La columna de la cabecera se mantiene y se sigue llenando, pero ahora
 * la calcula el sistema mirando los renglones: si alguno es de
 * exportacion, el documento lo es. Asi todo lo que ya dependia de ella
 * —el aviso de RB-056, el de no cotizar delivery en exportacion— sigue
 * funcionando sin tocarlo.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->string('use_type', 20)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->dropColumn('use_type');
        });
    }
};
