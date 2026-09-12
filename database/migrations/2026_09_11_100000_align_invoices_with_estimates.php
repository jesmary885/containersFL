<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ALINEAR LA FACTURA CON EL PRESUPUESTO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * RB-033 dice que convertir un presupuesto en factura es COPIAR, no
 * traducir. Hoy no se cumple, y no por un descuido del código: seis
 * columnas del presupuesto no tienen dónde caer en la factura.
 *
 * Se pierden al convertir:
 *
 *   use_type          storage | export. RB-056: en exportación el
 *                     certificado va incluido en el precio. Sin este
 *                     dato la factura no sabe que es una exportación y
 *                     alguien puede cobrar el certificado dos veces.
 *
 *   delivery_amount   cuánto se cobró de transporte. Lo necesita RB-030
 *                     (base de la comisión) y el cálculo de ganancia del
 *                     viaje. En estimates se llena solo desde las líneas.
 *
 *   depot_id          de qué depósito sale el contenedor.
 *   pickup_fee        la tarifa plana de esa recogida.
 *                     Las dos se agregaron a estimates y sales el 9-sep
 *                     y a invoices no. La factura es el documento que se
 *                     cobra: si el costo de recogida no llega ahí, el
 *                     margen real de la operación no se puede sacar.
 *
 *   rental_months     el plazo cotizado de una renta (por línea).
 *   work_details      qué se le hizo al contenedor en una reparación.
 *                     Las dos existen en estimate_items y no en
 *                     invoice_items. El cliente aprueba un presupuesto
 *                     que dice "reparación: cambio de pisos y pintura" y
 *                     recibe una factura que dice "reparación".
 *
 * ── POR QUÉ ADITIVA Y NO UN REDISEÑO ──
 *
 * Todas son nullable o con default. Ninguna toca datos existentes, y
 * `down()` las revierte limpias. No hace falta migrate:fresh.
 *
 * ── LO QUE NO SE AGREGA, Y POR QUÉ ──
 *
 * `valid_until` no se copia: una factura no vence, se vence. Ese
 * concepto ya lo cubre `due_date`, que el InvoiceObserver calcula desde
 * los términos de pago.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            /* -------------------------------------------------------------
             | PARA QUÉ SE USA EL CONTENEDOR (RB-006, RB-016, RB-017, RB-056)
             |
             | nullable a propósito: las facturas que ya existen no lo
             | tienen y no hay forma honesta de adivinarlo. Null quiere
             | decir "no se registró", que es distinto de "almacenamiento".
             * ---------------------------------------------------------- */
            $table->string('use_type', 20)->nullable()->after('type');

            /* -------------------------------------------------------------
             | EL TRANSPORTE COBRADO (RB-030)
             |
             | Mismo criterio que en estimates: no se captura, se suma de
             | las líneas de entrega al guardar. Existe como columna
             | porque los reportes la consultan mil veces y recorrer las
             | líneas cada vez es caro.
             * ---------------------------------------------------------- */
            $table->decimal('delivery_amount', 12, 2)->default(0)->after('discount_amount');

            /* -------------------------------------------------------------
             | DE DÓNDE SALIÓ EL CONTENEDOR
             |
             | Copia congelada, igual que en estimates y sales: si el
             | depósito sube su tarifa el mes que viene, esta factura
             | sigue diciendo lo que costó el día que se emitió (RB-058).
             * ---------------------------------------------------------- */
            $table->foreignId('depot_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();

            $table->decimal('pickup_fee', 12, 2)->default(0)->after('depot_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {

            /* -------------------------------------------------------------
             | EL PLAZO COTIZADO DE LA RENTA
             |
             | Va en la línea y NO multiplica el importe: si sumara los 6
             | meses, el total de la factura no cuadraría con el cobro
             | mensual real. Es el mismo criterio que ya está escrito en
             | estimate_items y en las reglas del negocio.
             * ---------------------------------------------------------- */
            $table->unsignedSmallInteger('rental_months')->nullable()->after('rate_per_mile');

            /* -------------------------------------------------------------
             | QUÉ SE LE HIZO AL CONTENEDOR
             |
             | Texto libre de la reparación. Hoy se cotiza el detalle y se
             | factura sin él.
             * ---------------------------------------------------------- */
            $table->text('work_details')->nullable()->after('rental_months');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depot_id');
            $table->dropColumn(['use_type', 'delivery_amount', 'pickup_fee']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['rental_months', 'work_details']);
        });
    }
};
