<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  
     /* -----------------------------------------------------------------
         | EL CONTRATO
         * -------------------------------------------------------------- */


    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('contract_number', 20);

            /* -------------------------------------------------------------
             | DURACIÓN
             |
             | end_date es nullable porque una renta activa no tiene
             | fecha de fin: se sabe cuándo empezó, no cuándo va a acabar.
             |
             | Se llena el día que el cliente devuelve el contenedor.
             * ---------------------------------------------------------- */
            $table->date('start_date');
            $table->date('end_date')->nullable();

            /* -------------------------------------------------------------
             | EL DÍA DEL CICLO  ← el detalle que causa más errores
             |
             | El ciclo se ancla al día de la ENTREGA, no al primero de mes.
             | Si se entregó el 17 de marzo, los períodos van del 17 al 16.
             |
             | billing_anchor_day guarda ese día (17) para no tener que
             | deducirlo cada vez.
             |
             | El caso feo: si se entregó un 31, febrero no tiene 31.
             | El código usa addMonthNoOverflow() y luego recorta al último
             | día del mes. Sin eso, PHP salta a marzo y el cliente pierde
             | un mes de cobro.
             * ---------------------------------------------------------- */
            $table->tinyInteger('billing_anchor_day');
            $table->string('billing_cycle', 20)->default('monthly');

            $table->decimal('monthly_rate', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);

            /* -------------------------------------------------------------
             | LAS DOS DIRECCIONES (RB-035)
             |
             | Se llaman igual que en estimates, sales e invoices a
             | proposito. Antes aca decia 'delivery_address', que es la
             | misma cosa con otro nombre: cada factura mensual del
             | contrato obligaba a traducir el campo, y ahi es donde se
             | cuelan los errores.
             |
             | bill_to hace falta aca, no solo ship_to: la renta genera
             | facturas sola (auto_invoice) e invoices.bill_to NO es
             | nullable. Si el contrato no lo congela, la factura de
             | septiembre tendria que ir a buscar la direccion del cliente
             | HOY, y si el cliente se mudo en junio quedaria mal emitida.
             |
             | Son una COPIA del dia que se firmo el contrato, no un
             | vinculo a la ficha del cliente (RB-058).
             * ---------------------------------------------------------- */
            $table->json('bill_to')->nullable();
            $table->json('ship_to')->nullable();

            /* -------------------------------------------------------------
             | ENTREGA
             * ---------------------------------------------------------- */
            $table->decimal('miles', 8, 2)->nullable();
            $table->decimal('delivery_amount', 12, 2)->default(0);
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('pickup_fee', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | MORA
             |
             | grace_days          = días de tolerancia después del
             |                       vencimiento antes de considerarlo mora
             | late_fee_amount     = cuánto se cobra
             | auto_apply_late_fee = si se aplica sola o hay que decidirlo
             | auto_invoice        = si se genera la factura mensual sola
             |
             | Los dos "auto" están acá y no en la configuración global
             | porque puede haber clientes con trato especial.
             * ---------------------------------------------------------- */
            $table->tinyInteger('grace_days')->default(5);
            $table->decimal('late_fee_amount', 12, 2)->default(100.00);
            $table->boolean('auto_apply_late_fee')->default(true);
            $table->boolean('auto_invoice')->default(true);

            // draft | active | ended | cancelled
            $table->string('status', 20)->default('active');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'contract_number']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
