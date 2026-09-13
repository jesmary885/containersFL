<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL USO PREVISTO, POR RENGLON
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * `use_type` ya existia en la CABECERA de la factura, y ahi no sirve.
 *
 * Una factura puede llevar tres contenedores con tres destinos distintos:
 * uno para almacenaje, uno para obra y uno para exportacion. Preguntarlo
 * una sola vez arriba obliga a elegir uno y que los otros dos queden mal.
 *
 * Y no es un dato decorativo: RB-056 dice que en exportacion solo entra
 * el Cargo Worthy, asi que el uso decide que unidades se pueden ofrecer.
 * Si el uso es del documento, esa regla no se puede aplicar por unidad.
 *
 * La columna de la cabecera se deja: sigue siendo util cuando toda la
 * factura es de lo mismo, y quitarla romperia lo ya guardado.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('use_type', 20)->nullable()->after('service_date');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('use_type');
        });
    }
};
