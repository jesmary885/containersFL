<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * RENTA DE YARDA — COBRO POR DÍA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DÓNDE SALE ──
 *
 * De la hoja RENTAS YARDA del Excel que la empresa usa hoy. El contrato de
 * MODUGO (contenedor 803847-3, desde el 21/10/2025) se lleva así:
 *
 *     DIAS EN RENTA      324        DIAS PENDIENTES   221
 *     PAGOS (días)       103        DEUDA ACTUAL      $442.00
 *
 * 221 × $2.00 = $442.00. Y en la hoja REPORTE YARDA, con corte al 28/02/2026:
 * 131 días transcurridos, 103 pagados, 28 pendientes, $56.00 de deuda.
 * 28 × $2.00 = $56.00.
 *
 * Es una renta de PATIO cobrada por día, no por mes. No es lo mismo que la
 * renta de contenedor (hoja RENTAS), que sí va mensual.
 *
 * ── POR QUÉ NO UNA TABLA NUEVA ──
 *
 * Porque todo lo demás es idéntico: cliente, contenedor, contrato,
 * direcciones congeladas, mora, facturación automática, pagos, períodos. Una
 * tabla `yard_rentals` aparte obligaría a duplicar RentalPeriod, la relación
 * con Invoice, el cálculo de mora de RB-024 y el observador de pagos. Cuatro
 * copias de la misma lógica es cuatro sitios donde arreglar el mismo bug.
 *
 * `billing_cycle` ya existía con default 'monthly' y no lo leía nadie. Este
 * es el momento en que empieza a significar algo.
 *
 * ── QUÉ NO CAMBIA ──
 *
 * Ninguna renta existente. `billing_cycle` sigue en 'monthly' por defecto y
 * `daily_rate` queda en null: una renta mensual no lo usa. Nada de lo que
 * ya funciona se toca.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {

            /* -----------------------------------------------------------------
            | LA TARIFA DIARIA
            |
            | Nullable a propósito: solo la llena una renta de yarda. Poner
            | 0 por defecto habría sido peor — no se distingue "no aplica"
            | de "gratis", y una renta diaria con la tarifa sin cargar
            | pasaría desapercibida generando períodos de $0.
            |
            | decimal(12,2) igual que monthly_rate. En el Excel son $2.00,
            | pero la columna tiene que aguantar una yarda de $85/día sin
            | que haya que migrar otra vez.
            * -------------------------------------------------------------- */
            $table->decimal('daily_rate', 12, 2)->nullable()->after('monthly_rate');

            /* -----------------------------------------------------------------
            | DÍAS YA PAGADOS
            |
            | El Excel lo lleva como un contador (columna PAGOS = 103) y
            | acá se replica, aunque en el sistema los pagos ya viven en
            | `payments`.
            |
            | Va como columna y no como suma calculada porque la migración
            | del Excel trae contratos con años de historia y sin el
            | detalle de qué día se pagó cada cosa. MODUGO tiene 103 días
            | pagados y no hay forma de reconstruir cuáles fueron.
            |
            | Para contratos nuevos lo mantiene el sistema. Para los
            | migrados es el saldo inicial. Sin esta columna, migrar el
            | Excel obligaría a inventar 103 pagos falsos.
            * -------------------------------------------------------------- */
            $table->unsignedInteger('paid_days')->default(0)->after('daily_rate');

            /* -----------------------------------------------------------------
            | CARGOS DE UNA SOLA VEZ
            |
            | El formulario del Excel los pide en el contrato, no como
            | conceptos sueltos: MONTO ENTRADA, MONTO SALIDA, PINTURA,
            | REPARACION.
            |
            | Se quedan en el contrato y no en las líneas de la factura
            | porque en el Excel son parte del acuerdo de yarda —se pactan
            | al firmar, no se agregan después— y porque la hoja REPORTE
            | YARDA los suma en columnas propias (ENTRADA $, SALIDA $,
            | PINTURA $, REPARACION $) al lado de la deuda de días. Ese
            | reporte hay que poder reproducirlo.
            |
            | entry_fee y exit_fee son el cobro por meter y sacar el
            | contenedor de la yarda. En el sistema ya existe pickup_fee,
            | que es otra cosa: el retiro desde un depósito de tercero.
            * -------------------------------------------------------------- */
            $table->decimal('entry_fee', 12, 2)->default(0)->after('paid_days');
            $table->decimal('exit_fee', 12, 2)->default(0)->after('entry_fee');
            $table->decimal('paint_fee', 12, 2)->default(0)->after('exit_fee');
            $table->decimal('repair_fee', 12, 2)->default(0)->after('paint_fee');

            /* -----------------------------------------------------------------
            | ÍNDICE
            |
            | La pantalla de yarda va a filtrar por ciclo y estado:
            | "todas las rentas de yarda activas". Sin el índice eso es un
            | recorrido de la tabla entera cada vez que se abre.
            * -------------------------------------------------------------- */
            $table->index(['company_id', 'billing_cycle', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'billing_cycle', 'status']);

            $table->dropColumn([
                'daily_rate', 'paid_days',
                'entry_fee', 'exit_fee', 'paint_fee', 'repair_fee',
            ]);
        });
    }
};
