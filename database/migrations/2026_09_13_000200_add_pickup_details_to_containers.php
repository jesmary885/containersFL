<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * QUIEN TRAJO CADA CONTENEDOR
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DONDE SALE ──
 *
 * De la columna `NOMBRE TRANS.` de la hoja COMPRAS del Excel. Ahi hay
 * tres clases de valor:
 *
 *   "S TRANSPORT FLORIDA"   un transportista externo
 *   "MIGUELITO"             un trabajador nuestro
 *   "DIRECTO A LA YARDA"    nadie: lo trajo el proveedor
 *
 * Por eso son dos columnas y no una: el que trae puede ser de la casa o
 * de fuera, y son dos tablas distintas. Las dos en null significan que lo
 * trajo el proveedor, que es el caso de las filas con PICK UP en $0.00.
 *
 * ── POR QUE NO UN CAMPO DE TEXTO ──
 *
 * Porque en el Excel "S TRANSPORT FLORIDA" y "S TRANSPORT" son la misma
 * empresa escrita de dos formas, y al sumar el gasto de transporte del
 * mes salen como dos proveedores.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {

            // Lo trajo un trabajador nuestro.
            $table->foreignId('pickup_by_employee_id')
                ->nullable()->after('pickup_cost')
                ->constrained('employees')->nullOnDelete();

            // Lo trajo un transportista de fuera.
            $table->foreignId('pickup_supplier_id')
                ->nullable()->after('pickup_by_employee_id')
                ->constrained('suppliers')->nullOnDelete();

            // El dia que se hizo el retiro del deposito.
            $table->date('picked_up_at')->nullable()->after('pickup_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_by_employee_id');
            $table->dropConstrainedForeignId('pickup_supplier_id');
            $table->dropColumn('picked_up_at');
        });
    }
};
