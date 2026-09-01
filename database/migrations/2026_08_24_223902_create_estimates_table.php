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
        Schema::create('estimates', function (Blueprint $table) {
             $table->id();

            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('estimate_number', 20);

            // draft | sent | accepted | rejected | expired | converted
            $table->string('status', 20)->default('draft');

            /* -------------------------------------------------------------
             | VIGENCIA
             |
             | valid_until es hasta cuándo se respeta el precio. Pasada esa
             | fecha el estimate se marca como vencido y hay que rehacerlo.
             * ---------------------------------------------------------- */
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->string('terms', 50)->nullable();   // "Net 30"

            /* -------------------------------------------------------------
             | DIRECCIONES CONGELADAS
             |
             | JSON y no relación a customer_addresses. Es una COPIA del
             | día que se hizo el presupuesto.
             |
             | Si el cliente se muda antes de aceptar, el documento sigue
             | mostrando la dirección con la que se cotizó.
             * ---------------------------------------------------------- */
            $table->json('bill_to')->nullable();
            $table->json('ship_to')->nullable();

            /* -------------------------------------------------------------
             | ENTREGA
             |
             | use_type cambia el impuesto: si el contenedor se va a
             | exportar, no paga sales tax de Florida.
             |
             | Las millas y la tarifa se guardan además del monto porque
             | así se puede reconstruir cómo se llegó a esa cifra si el
             | cliente pregunta.
             * ---------------------------------------------------------- */
            $table->string('use_type', 20)->nullable();      // storage | export
            $table->string('delivery_zip', 10)->nullable();
            $table->decimal('miles', 8, 2)->nullable();
            $table->decimal('rate_per_mile', 12, 2)->nullable();
            $table->decimal('delivery_amount', 12, 2)->default(0);

            // El fee se precarga del depósito y queda editable acá.
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('pickup_fee', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | MONTOS
             |
             | Los mismos campos que la factura, y en el mismo orden. Es a
             | propósito: convertir un estimate en factura es copiar valores
             | uno a uno, sin traducir nada.
             |
             | taxable_base = solo lo que paga impuesto, después de
             | repartir el descuento proporcionalmente.
             * ---------------------------------------------------------- */
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('taxable_base', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->boolean('tax_exempt')->default(false);
            $table->decimal('credit_card_fee_percent', 5, 2)->default(0);
            $table->decimal('credit_card_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | LA FACTURA RESULTANTE
             |
             | Sin constrained(): invoices se crea en el bloque 7, mucho
             | después. Y además hay referencia circular — la factura
             | también apunta al estimate. Las dos llaves se cierran en el
             | bloque 8.
             * ---------------------------------------------------------- */
          
            $table->unsignedBigInteger('converted_invoice_id')->nullable();

            // Quién lo cotizó. Base de la comisión si se concreta.
            $table->foreignId('salesperson_id')->nullable()
                  ->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->text('footer_terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* -------------------------------------------------------------
             | ÍNDICES
             * ---------------------------------------------------------- */
            $table->unique(['company_id', 'estimate_number']);
            $table->index(['company_id', 'status']);        // "presupuestos enviados"
            $table->index(['customer_id', 'issue_date']);   // historial del cliente
        });

        /* -----------------------------------------------------------------
         | LAS LÍNEAS
         * -------------------------------------------------------------- */
        Schema::create('estimate_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();

            $table->smallInteger('line_number')->default(1);

            /* -------------------------------------------------------------
             | QUÉ SE COTIZA
             |
             | Los dos nullable: se puede cotizar un concepto escrito a
             | mano, sin producto del catálogo y sin contenedor asignado
             | todavía.
             * ---------------------------------------------------------- */
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();

            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | IMPUESTO POR LÍNEA
             |
             | Default false porque la mayoría de las líneas son servicios
             | (delivery, pickup), y en Florida el transporte no paga
             | sales tax. El contenedor sí, y esa línea se marca.
             * ---------------------------------------------------------- */
            $table->boolean('taxable')->default(false);

            /* -------------------------------------------------------------
             | AGRUPACIÓN PARA IMPRIMIR
             |
             | Las líneas con el mismo bundle_key se muestran al cliente
             | como UN solo renglón con el precio sumado, pero por dentro
             | cada una conserva su propio 'taxable'.
             |
             | Ejemplo: contenedor 2,400 (gravable) + delivery 350 (no).
             |   El cliente ve  -> "Contenedor 40HC entregado ... 2,750"
             |   El sistema cobra tax solo sobre 2,400.
             |
             | 36 caracteres porque se usa un UUID como llave del grupo.
             * ---------------------------------------------------------- */
            $table->string('bundle_key', 36)->nullable();
            $table->string('bundle_description', 255)->nullable();

            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            // La consulta de siempre: las líneas de un estimate, en orden.
            $table->index(['estimate_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::dropIfExists('estimate_items');
        Schema::dropIfExists('estimates');
    }
};
