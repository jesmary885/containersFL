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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('payment_number', 20);

            // cash | check | zelle | ach | wire | credit_card | other
            $table->string('method', 20);

            /* -------------------------------------------------------------
             | LOS TRES MONTOS  ← no son lo mismo
             |
             | amount     = lo que pagó el cliente          1,000.00
             | fee_amount = lo que retuvo Square              −29.00
             | net_amount = lo que entró al banco             971.00
             |
             | Se guardan los tres porque cada uno responde una pregunta
             | distinta:
             |   ¿cuánto debe el cliente todavía?  -> amount
             |   ¿cuánto costó cobrar?             -> fee_amount
             |   ¿cuánto hay en el banco?          -> net_amount
             |
             | fee_amount llega en el webhook de Square, no se calcula.
             * ---------------------------------------------------------- */
            $table->decimal('amount', 12, 2);
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);

            $table->dateTime('received_at');

            // Número de cheque, confirmación de Zelle, etc.
            $table->string('reference', 100)->nullable();

            /* -------------------------------------------------------------
             | DATOS DE LA PASARELA
             |
             | provider_payment_id es UNIQUE, y eso resuelve un problema
             | concreto: los webhooks se reintentan.
             |
             | Si Square no recibe respuesta a tiempo, vuelve a mandar el
             | mismo aviso. Sin este unique, el segundo intento crearía un
             | segundo pago y el cliente aparecería pagando doble.
             |
             | Con el unique, el segundo intento choca contra la base de
             | datos y se descarta solo. A esto se le llama idempotencia.
             * ---------------------------------------------------------- */
            $table->string('provider', 20)->default('manual');   // manual | square
            $table->string('provider_payment_id', 64)->nullable()->unique();
            $table->string('provider_status', 30)->nullable();
            $table->string('card_brand', 20)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->foreignId('credit_card_authorization_id')->nullable()
                  ->constrained('credit_card_authorizations')->nullOnDelete();

            /* -------------------------------------------------------------
             | ANTICIPOS Y SALDO SIN APLICAR
             |
             | is_deposit = es un anticipo antes de facturar
             |
             | unapplied_amount = cuánto de este pago todavía no se aplicó
             | a ninguna factura. Es campo derivado: sale de restar las
             | asignaciones al monto total.
             |
             | Sirve para el reporte "dinero recibido sin aplicar", que es
             | plata que está en el banco pero no reduce ninguna deuda.
             * ---------------------------------------------------------- */
            $table->boolean('is_deposit')->default(false);
            $table->decimal('unapplied_amount', 12, 2)->default(0);

            // pending | completed | failed | refunded | disputed
            $table->string('status', 20)->default('completed');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // SIN softDeletes: un pago recibido no se borra. Si fue un
            // error, se marca failed o refunded y queda el rastro.
            $table->timestamps();

            $table->unique(['company_id', 'payment_number']);
            $table->index(['company_id', 'received_at']);    // cierre de caja
            $table->index(['customer_id', 'received_at']);   // historial del cliente
            $table->index(['method', 'received_at']);        // "¿cuánto entró por Zelle?"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
