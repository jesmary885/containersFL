<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     *La venta cerrada. **Es distinta de la factura**: la venta es la operación
    *(qué contenedores, a quién, cuándo se entregan); la factura es el
    *documento de cobro. Una venta puede generar más de una factura.
     */
    public function up(): void
    {
          /* -----------------------------------------------------------------
         | LA VENTA
         * -------------------------------------------------------------- */

        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // De qué presupuesto salió, si vino de uno.
            $table->foreignId('estimate_id')->nullable()->constrained()->nullOnDelete();

            $table->string('sale_number', 20);
            $table->date('sale_date');

            /* -------------------------------------------------------------
             | TIPO Y ESTADO
             |
             | use_type: export cambia el tratamiento del impuesto y
             | obliga a emitir certificado.
             |
             | status sigue la OPERACIÓN, no el cobro:
             |   pending   = vendido, sin preparar
             |   ready     = listo para entregar
             |   delivered = entregado
             |   completed = cerrado
             |   cancelled
             |
             | El cobro va por su lado, en la factura.
             * ---------------------------------------------------------- */
            $table->string('use_type', 20)->default('storage');   // storage | export
            $table->string('status', 20)->default('pending');

            /* -------------------------------------------------------------
             | ENTREGA
             |
             | delivery       = se lo llevamos
             | customer_pickup = lo viene a buscar
             * ---------------------------------------------------------- */
            $table->string('delivery_method', 20)->default('delivery');
              /* -------------------------------------------------------------
             | LAS DOS DIRECCIONES (RB-035)
             |
             | Se llaman igual que en estimates y en invoices a propósito.
             | Antes acá decía 'delivery_address' y allá 'ship_to', que es
             | la misma cosa con dos nombres. Convertir un presupuesto en
             | venta obligaba a traducir el nombre del campo, y eso es
             | exactamente donde se cuelan los errores.
             |
             | Son una COPIA del día de la venta, no un vínculo a la ficha
             | del cliente. Si el cliente se muda, este documento sigue
             | mostrando dónde estaba (RB-058).
             |
             | ── POR QUÉ SIGUEN EN LA CABECERA ──
             |
             | Porque son direcciones, no cálculos. El documento necesita
             | UN "a nombre de quién" y UN destino principal.
             |
             | Cuando de verdad hay tres destinos, cada viaje lleva el
             | suyo en trips.destination_address. Lo que bajamos al
             | renglón fueron las millas y la tarifa, que son insumos de
             | un precio y necesitan uno por entrega.
             * ---------------------------------------------------------- */
            $table->json('bill_to')->nullable();
            $table->json('ship_to')->nullable();
            

            // Si el contenedor se retira de un depósito, el fee se
            // precarga de ahí y queda editable.
            // $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            // $table->decimal('pickup_fee', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | MONTOS
             |
             | Separados por concepto (contenedor / delivery) porque el
             | impuesto se calcula distinto sobre cada uno.
             |
             | deposit_amount es el anticipo. Solo aplica a ventas: en las
             | rentas no hay anticipo, hay mensualidades.
             * ---------------------------------------------------------- */
            $table->decimal('container_amount', 12, 2)->default(0);
            $table->decimal('delivery_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('deposit_amount', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | EXENCIÓN DE IMPUESTO
             |
             | Acá está la parte importante: no basta con marcar
             | tax_exempt, se guarda CUÁL certificado lo justifica.
             |
             | Si el año que viene ese certificado vence, esta venta sigue
             | apuntando al que estaba vigente el día que se hizo. Es la
             | prueba ante Hacienda.
             * ---------------------------------------------------------- */
            $table->boolean('tax_exempt')->default(false);
            $table->foreignId('tax_exemption_certificate_id')->nullable()
                  ->constrained('tax_exemption_certificates')->nullOnDelete();
            $table->decimal('tax_rate', 5, 2)->default(0);

            /* -------------------------------------------------------------
             | EXPORTACIÓN Y ALMACENAJE
             |
             | free_storage_until: hasta cuándo se le guarda gratis al
             | cliente. Pasada esa fecha empieza a correr el cobro diario
             | de la yarda.
             * ---------------------------------------------------------- */
            $table->boolean('requires_export_certificate')->default(false);
            $table->date('free_storage_until')->nullable();

            /* -------------------------------------------------------------
             | COMISIÓN
             |
             | Se guardan el porcentaje Y el monto. El porcentaje se
             | precarga del vendedor; el monto es lo que realmente se le
             | va a pagar, que puede ajustarse a mano.
             * ---------------------------------------------------------- */
            $table->foreignId('salesperson_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('commission_amount', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'sale_number']);
            $table->index(['company_id', 'status']);       // "ventas por entregar"
            $table->index(['customer_id', 'sale_date']);   // historial del cliente
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
