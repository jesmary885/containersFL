<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * QUIÉN CERRÓ LA VENTA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── EL PROBLEMA ──
 *
 * `estimates` y `sales` ya distinguen dos personas distintas:
 *
 *     created_by       quien TECLEÓ el documento
 *     salesperson_id   quien CERRÓ la venta y cobra la comisión
 *
 * `invoices` solo tiene `created_by`. Así que en el momento en que el
 * presupuesto se convierte en factura, el vendedor SE PIERDE:
 * convertToInvoice() copia bill_to, ship_to, el tax, el recargo de
 * tarjeta y hasta el pie de página, pero no copia salesperson_id porque
 * no hay dónde ponerlo.
 *
 * Y la factura es el documento que se cobra. Si el vendedor no está ahí,
 * la comisión no tiene de dónde colgarse.
 *
 * Es exactamente el caso que planteó el cliente: Denisse registra el
 * presupuesto y la factura, pero la venta la cerró Miguelito. Hoy el
 * sistema guardaría a Denisse en las dos y a Miguelito en ninguna.
 *
 * ── LA COMISIÓN ──
 *
 * `commissions` y `commission_payments` YA existen y ya resuelven el
 * porcentaje, el monto fijo y los abonos parciales. Lo que falta es que
 * alguien las CREE: hoy no hay una sola línea en el proyecto que
 * escriba una fila en `commissions`. La mecánica está construida y
 * muerta.
 *
 * Para arrancarla hacen falta dos cosas:
 *
 *   commissions.invoice_id   de qué factura nace la comisión. La venta
 *                            puede no existir todavía (se factura antes
 *                            de registrar la venta), así que sale_id no
 *                            alcanza como único vínculo.
 *
 *   invoices.commission_*    lo pactado, congelado en el documento. Igual
 *                            que las direcciones (RB-058): si mañana
 *                            cambia el porcentaje del vendedor, esta
 *                            factura conserva el que se acordó.
 *
 * ── LAS DOS FORMAS, LAS DOS ──
 *
 * El Excel las usa mezcladas. En la hoja COMISIONES VENTAS hay montos
 * planos ($200, $300, $1,100, $650, $40) y en la hoja VENTAS hay una
 * comisión de $150 sobre $2,650, que es 5.66% — un número que nadie
 * pacta como porcentaje.
 *
 * Así que se guarda `commission_mode`: 'percent' o 'fixed'. Con
 * porcentaje, el monto se calcula; con fijo, se teclea. Los dos quedan
 * guardados en las dos columnas, para poder explicar después cómo se
 * llegó a la cifra.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            /* -----------------------------------------------------------------
            | EL VENDEDOR
            |
            | Nullable: hay facturas sin vendedor. Una renta que se factura
            | sola cada mes no la cierra nadie, y un cargo administrativo
            | tampoco.
            |
            | nullOnDelete y no restrict: si se da de baja un usuario, la
            | factura tiene que seguir existiendo. Lo que no se puede
            | borrar es un usuario con comisión pendiente, y de eso ya se
            | encarga el restrictOnDelete de `commissions`.
            * -------------------------------------------------------------- */
            $table->foreignId('salesperson_id')->nullable()->after('customer_id')
                ->constrained('users')->nullOnDelete();

            /* -----------------------------------------------------------------
            | LA COMISIÓN PACTADA
            |
            | 'percent'  el monto sale de aplicar el % a la base
            | 'fixed'    el monto se pactó a mano
            |
            | Nullable, no default: null significa "esta factura no genera
            | comisión". Un default 'percent' con 0% habría creado
            | comisiones de $0 para cada renta mensual automática.
            * -------------------------------------------------------------- */
            $table->string('commission_mode', 10)->nullable()->after('salesperson_id');
            $table->decimal('commission_percent', 5, 2)->nullable()->after('commission_mode');
            $table->decimal('commission_amount', 12, 2)->nullable()->after('commission_percent');

            $table->index(['company_id', 'salesperson_id']);   // "las ventas de Miguelito"
        });

        Schema::table('commissions', function (Blueprint $table) {

            /* -----------------------------------------------------------------
            | DE QUÉ FACTURA NACE
            |
            | cascadeOnDelete no: borrar una factura no debe borrar en
            | silencio una comisión que quizá ya se le pagó al vendedor.
            * -------------------------------------------------------------- */
            $table->foreignId('invoice_id')->nullable()->after('sale_id')
                ->constrained()->nullOnDelete();

            /* -----------------------------------------------------------------
            | CÓMO SE CALCULÓ
            |
            | La tabla ya guarda base_amount, percent y amount. Lo que no
            | dice es si el porcentaje se aplicó o si el monto se pactó a
            | mano. Sin eso, una comisión con percent=null y amount=300 es
            | ambigua: pudo ser un monto fijo, o un porcentaje que alguien
            | olvidó registrar.
            * -------------------------------------------------------------- */
            $table->string('mode', 10)->default('percent')->after('percent');
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('mode');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'salesperson_id']);
            $table->dropConstrainedForeignId('salesperson_id');
            $table->dropColumn(['commission_mode', 'commission_percent', 'commission_amount']);
        });
    }
};
