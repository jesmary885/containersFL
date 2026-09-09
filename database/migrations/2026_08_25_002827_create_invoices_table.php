<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /* -----------------------------------------------------------------
         | LA FACTURA
         * -------------------------------------------------------------- */
        Schema::create('invoices', function (Blueprint $table) {
              /* -------------------------------------------------------------
             | QUIÉN FACTURA Y A QUIÉN
             |
             | company_id se precarga con el billing_company_id del
             | contenedor y es EDITABLE al facturar. Es lo que permite que
             | RST facture un contenedor que es de FLCHR.
             |
             | restrictOnDelete en las dos: una factura emitida es un
             | documento fiscal. Ni la compañía ni el cliente se pueden
             | borrar mientras existan facturas suyas.
             * ---------------------------------------------------------- */
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('invoice_number', 20);

            // sale | rental | transport | intercompany | other
            $table->string('type', 20)->default('sale');

            // draft | sent | partial | paid | overdue | void
            $table->string('status', 20)->default('draft');

            /* -------------------------------------------------------------
             | DE DÓNDE SALIÓ
             |
             | Solo uno de los cuatro tiene valor, según el tipo.
             |
             | rental_period_id es importante: identifica exactamente qué
             | mes de qué contrato cubre esta factura.
             * ---------------------------------------------------------- */
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rental_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('estimate_id')->nullable()->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | FECHAS
             |
             | service_period_* son columnas propias, no una nota suelta:
             | en las facturas de renta hay que poder decir "esta cubre
             | del 17 de marzo al 16 de abril", y poder consultarlo.
             * ---------------------------------------------------------- */
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('terms', 50)->nullable();

            /* -------------------------------------------------------------
             | CÓMO DIJO QUE IBA A PAGAR  ← COLUMNA NUEVA
             |
             | El recargo del 3.5% (RB-009) se calcula al elegir el
             | método de pago. Pero la factura no guardaba cuál se
             | eligió, así que al reimprimirla no había forma de
             | explicar de dónde salió ese recargo.
             |
             | OJO CON LA DIFERENCIA:
             |
             |   expected_payment_method  = lo que el cliente DIJO que
             |                              iba a usar. Vive acá y sirve
             |                              para precargar el fee.
             |
             |   payments.method          = con qué pagó DE VERDAD. Ese
             |                              es el bueno para contabilidad.
             |
             | Pueden no coincidir: alguien anuncia tarjeta, se le
             | factura con el 3.5%, y al final paga con cheque. Ahí hay
             | que corregir la factura, y para saber que hay que
             | corregirla se necesitan los dos datos.
             * ---------------------------------------------------------- */
            $table->string('expected_payment_method', 20)->nullable();



            $table->date('service_period_start')->nullable();
            $table->date('service_period_end')->nullable();

            /* -------------------------------------------------------------
             | DIRECCIONES CONGELADAS
             |
             | bill_to NO es nullable: toda factura tiene que decir a
             | nombre de quién y a qué dirección se emitió.
             |
             | Es una copia, no una relación. Si el cliente se muda en
             | junio, la factura de marzo sigue mostrando la dirección de
             | marzo. Eso es lo correcto en un documento fiscal.
             * ---------------------------------------------------------- */
            $table->json('bill_to');
            $table->json('ship_to')->nullable();

            /* -------------------------------------------------------------
             |     | LOS MONTOS  ← el orden importa, es el orden del cálculo
             |
             |   subtotal        suma de TODAS las líneas
             | − discount_amount
             |   taxable_base    solo las líneas gravables, con el
             |                   descuento repartido proporcionalmente
             | × tax_rate        = tax_amount
             | + credit_card_fee
             |   ────────────────
             |   total           ← el valor real de la venta
             | − amount_paid     ← todo lo cobrado, depósito incluido
             |   ────────────────
             |   balance_due
             |
             | DEPOSIT_APPLIED NO SE RESTA ACÁ.
             |
             | Es una copia para imprimir: la suma de los pagos marcados
             | como depósito que se aplicaron a esta factura. Ya está
             | contenida dentro de amount_paid, así que restarla otra vez
             | descontaría el mismo dinero dos veces.
             |
             | Un depósito no baja el valor de la venta, baja lo que
             | falta cobrar. Si bajara el total, el reporte de ventas del
             | mes saldría corto.
             |
             | taxable_base es la columna clave del sistema: es lo que
             | permite mostrarle al cliente un precio consolidado y aún
             | así cobrar el impuesto correcto. Solo el contenedor paga
             | tax; el delivery no.
             |
             | tax_rate se precarga de la compañía pero es EDITABLE acá,
             | y una vez guardada la factura conserva el suyo.
             |
             | decimal(12,2) y nunca float: con float, 0.1 + 0.2 no da
             | exactamente 0.3, y en dinero eso no se perdona.
             * ---------------------------------------------------------- */
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('taxable_base', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->boolean('tax_exempt')->default(false);

            // La prueba de por qué no se cobró impuesto.
            $table->foreignId('tax_exemption_certificate_id')->nullable()
                  ->constrained('tax_exemption_certificates')->nullOnDelete();

            $table->decimal('deposit_applied', 12, 2)->default(0);
            $table->decimal('credit_card_fee_percent', 5, 2)->default(0);
            $table->decimal('credit_card_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('footer_terms')->nullable();

            /* -------------------------------------------------------------
             | INTEGRACIÓN CON SQUARE
             |
             | Las columnas se crean ahora aunque Square se conecte
             | después. Agregarlas hoy es gratis; agregarlas con 3,000
             | facturas cargadas es una migración con riesgo.
             |
             | square_invoice_id es unique para que un webhook repetido
             | no cree dos veces la misma factura.
             |
             | external_ref y external_system quedan previstos para
             | QuickBooks, si algún día se conecta.
             * ---------------------------------------------------------- */
            $table->string('square_invoice_id', 64)->nullable()->unique();
            $table->string('payment_link_url', 500)->nullable();
            $table->string('external_ref', 64)->nullable();
            $table->string('external_system', 20)->nullable();

            /* -------------------------------------------------------------
             | SEGUIMIENTO
             |
             | viewed_at responde "¿el cliente la abrió?", que es la
             | pregunta de siempre antes de llamar a cobrar.
             |
             | void_reason es obligatorio en la práctica: anular una
             | factura sin explicar por qué no sirve de nada.
             * ---------------------------------------------------------- */
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->string('locale', 5)->default('en');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            /* -------------------------------------------------------------
             | SIN softDeletes A PROPÓSITO
             |
             | Una factura no se borra nunca, ni siquiera "suave". Se
             | anula con status void y queda visible, con su número
             | consumido y su motivo.
             |
             | Un hueco en la numeración es un problema fiscal.
             * ---------------------------------------------------------- */
            $table->timestamps();

            /* -------------------------------------------------------------
             | ÍNDICES
             * ---------------------------------------------------------- */

            // No es búsqueda, es REGLA: prohíbe dos veces el mismo número
            // dentro de la misma compañía. FLCHR puede tener su 1358 y
            // RST la suya; lo que no puede haber es dos 1358 en FLCHR.
            //
            // Es el cinturón de seguridad detrás de nextNumber().
            $table->unique(['company_id', 'invoice_number']);

            // Aging: "de esta compañía, sin pagar, vencidas antes de X".
            $table->index(['company_id', 'status', 'due_date']);

            // Historial del cliente, de la más nueva a la más vieja.
            $table->index(['customer_id', 'issue_date']);

            // Reporte de impuestos: empieza por fecha porque la consulta
            // arranca por el período y después separa por compañía.
            // Son las mismas dos columnas que el aging pero al revés, y
            // por eso hace falta un índice aparte: el orden decide para
            // qué sirve.
            $table->index(['issue_date', 'company_id']);

            // Este es el más flojo de los cinco: como casi todas las
            // facturas tienen saldo, MySQL probablemente lo ignore.
            // Los índices sirven cuando descartan mucho, y este descarta
            // poco. Se puede quitar: ya está cubierto por el de aging.
            $table->index('balance_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
