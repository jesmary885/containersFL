<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MIGRACIÓN NUEVA — la única que no se puede resolver editando un
     * archivo que ya existe.
     *
     * ── POR QUÉ VA APARTE ──
     *
     * Esta columna es una llave foránea de companies hacia customers.
     * Pero `companies` se crea en la migración del 23 de agosto y
     * `customers` en la del 24: cuando corre la primera, la tabla
     * customers todavía no existe y la llave no se puede crear.
     *
     * Ponerla en un archivo con fecha posterior resuelve el orden sin
     * tener que renumerar nada.
     *
     * ── PARA QUÉ SIRVE ──
     *
     * RB-003: RS Transport le emite una factura semanal a FLCHR por
     * todos los viajes. Para emitir esa factura, FLCHR tiene que
     * existir como CLIENTE dentro del sistema.
     *
     * El CustomerSeeder ya crea ese cliente y le pone
     * `related_company_id` apuntando a FLCHR. Lo que faltaba era el
     * camino de vuelta: desde la compañía, saber cuál es su ficha de
     * cliente.
     *
     *   companies.customer_id → customers.id
     *   customers.related_company_id → companies.id
     *
     * Con las dos direcciones, el generador de la factura
     * intercompañía no tiene que adivinar nada.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            /* -------------------------------------------------------------
             | nullOnDelete y no cascade:
             |
             | Si alguien borra por error la ficha de cliente de FLCHR,
             | queremos que la COMPAÑÍA sobreviva y quede el campo en
             | blanco. Con cascade se borraría la empresa entera.
             * ---------------------------------------------------------- */
            $table->foreignId('customer_id')->nullable()
                  ->after('is_default_transport_provider')
                  ->constrained('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Primero se suelta la llave, después la columna.
            // Al revés, MySQL se queja de que la columna está en uso.
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
