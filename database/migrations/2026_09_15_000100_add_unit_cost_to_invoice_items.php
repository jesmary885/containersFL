<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL COSTO CONGELADO EN EL RENGLÓN DE LA FACTURA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── QUÉ RESUELVE ──
 *
 * Hasta ahora la factura sabía lo que le cobró al cliente y no sabía lo que
 * costó la unidad. Sin ese dato no hay ganancia posible: solo ingresos.
 *
 * El costo YA EXISTE, en el contenedor: adquisición + recogida +
 * reacondicionamiento. Lo que faltaba era copiarlo al documento en el
 * momento de facturar.
 *
 * ── POR QUÉ UNA COPIA Y NO LEERLO DEL CONTENEDOR CADA VEZ ──
 *
 * Porque el costo del contenedor CAMBIA. Se le hace una reparación el mes
 * que viene y sube. Si el margen de una venta de marzo se calculara leyendo
 * el costo de hoy, el número cambiaría solo, y una venta cerrada no puede
 * cambiar de resultado.
 *
 * Es el mismo criterio que ya sigue esta factura con la dirección del
 * cliente, con la tasa de impuesto y con la comisión: lo que se pactó queda
 * congelado en el documento.
 *
 * ── POR QUÉ EN EL RENGLÓN Y NO EN LA CABECERA ──
 *
 * Porque una factura puede llevar tres contenedores con tres costos
 * distintos, más una entrega que no tiene costo de unidad. El margen se
 * calcula por renglón y se suma; al revés no se puede desglosar.
 *
 * ── NULLABLE A PROPÓSITO ──
 *
 * Un renglón sin contenedor —una entrega, un cargo por mora, un almacenaje—
 * no tiene costo de unidad. Y las facturas emitidas ANTES de esta migración
 * se quedan en null, que es lo honesto: de esas no sabemos el costo y el
 * sistema tiene que poder decir "no lo sé" en vez de inventar un cero, que
 * se leería como margen del 100%.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {

            /*
             | Lo que nos costó ESA unidad, puesta en yarda.
             |
             | decimal(12,2) y nunca float: con float, 0.1 + 0.2 no da
             | exactamente 0.3, y en dinero eso no se perdona.
             */
            $table->decimal('unit_cost', 12, 2)
                ->nullable()
                ->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
