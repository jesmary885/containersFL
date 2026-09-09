<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════════════════
     * EN QUÉ DOCUMENTO PUEDE APARECER CADA CONCEPTO
     * ═══════════════════════════════════════════════════════════════════════
     *
     * Tenías razón: en el desplegable de un PRESUPUESTO no deberían salir
     * "Cargo por mora" ni "Recargo por tarjeta".
     *
     * ── POR QUÉ NO SE BORRAN DEL CATÁLOGO ──
     *
     * Porque la FACTURA sí los necesita:
     *
     *   Cargo por mora    nace de una renta vencida (RB-024), se agrega
     *                     a la factura del mes siguiente. Nunca se cotiza
     *                     por adelantado: cotizar una mora sería decirle
     *                     al cliente "calculo que me vas a pagar tarde".
     *
     *   Recargo tarjeta   ya se calcula solo con el interruptor "pagará
     *                     con tarjeta" (RB-009). Si además estuviera como
     *                     línea, algún día alguien lo cobraría dos veces:
     *                     una en la línea y otra en el total. Y eso no
     *                     avisa, solo sale un total más alto.
     *
     *   Almacenaje        se cobra desde el 3er día después de la venta
     *                     (RB-021). Es un hecho posterior, no algo que se
     *                     cotiza.
     *
     * Borrarlos rompería la facturación. Esconderlos del presupuesto no
     * rompe nada.
     *
     * ── LOS TRES VALORES ──
     *
     *   'both'      sale en presupuestos y en facturas (la mayoría)
     *   'invoice'   solo en facturas
     *   'estimate'  solo en presupuestos (por ahora ninguno, pero el
     *               día que exista un concepto de solo-cotización el
     *               campo ya está)
     *
     * El default es 'both' para que ningún concepto desaparezca por
     * accidente si mañana se carga uno nuevo y se olvida marcarlo.
     * ═══════════════════════════════════════════════════════════════════════
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
             $table->string('usable_in', 10)
                  ->default('both')
                  ->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('usable_in');
        });
    }
};
