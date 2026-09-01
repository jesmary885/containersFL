<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La autorización firmada del cliente para cobrarle a su tarjeta.
        **Esta tabla tiene una regla que no se negocia:** no guarda, ni va a
        *guardar, el número de tarjeta, el CVV, la banda ni el chip. Solo los
        *últimos 4 dígitos, la marca y el identificador que devuelve Square.
        *Guardar el resto viola PCI-DSS y expone a una demanda.
     */
    public function up(): void
    {
        /**
     * REGLA NO NEGOCIABLE: esta tabla no tiene, ni tendrá, columna para
     * número de tarjeta (PAN), CVV, banda magnética ni chip.
     */
        Schema::create('credit_card_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            /* -------------------------------------------------------------
             | LO ÚNICO QUE SE PUEDE GUARDAR DE LA TARJETA
             |
             | Con la marca y los últimos 4 alcanza para que el cliente
             | reconozca cuál es ("Visa ····4242") y para conciliar con
             | el estado de cuenta.
             |
             | char(4) y no string: siempre son exactamente 4 dígitos.
             |
             | La fecha de vencimiento se guarda solo para avisar cuando
             | esté por caducar la autorización, no para procesar.
             * ---------------------------------------------------------- */
            $table->string('cardholder_name', 150);
            $table->string('card_brand', 20)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->tinyInteger('exp_month')->nullable();
            $table->smallInteger('exp_year')->nullable();
            $table->json('billing_address')->nullable();

            /* -------------------------------------------------------------
             | ALCANCE DE LA AUTORIZACIÓN
             |
             | single_use   = un solo cobro, por este monto
             | card_on_file = queda guardada para cobros recurrentes
             |
             | authorized_amount pone el techo: no se puede cobrar más de
             | lo que el cliente firmó.
             * ---------------------------------------------------------- */
            $table->decimal('authorized_amount', 12, 2)->nullable();
            $table->string('authorization_type', 20)->default('single_use');

            /* -------------------------------------------------------------
             | LOS IDENTIFICADORES DE SQUARE
             |
             | Acá está la solución al problema: Square guarda la tarjeta
             | y nos devuelve un identificador. Nosotros guardamos ese
             | identificador, no la tarjeta.
             |
             | Para cobrar, le decimos a Square "cóbrale a la tarjeta
             | ABC123 del cliente XYZ". El número nunca pasa por nuestro
             | servidor ni por nuestra base de datos.
             * ---------------------------------------------------------- */
            $table->string('square_customer_id', 64)->nullable();
            $table->string('square_card_id', 64)->nullable();

            /* -------------------------------------------------------------
             | LA PRUEBA
             |
             | Si el cliente desconoce el cargo, el papel firmado es la
             | única defensa. Por eso se guarda el escaneado.
             * ---------------------------------------------------------- */
            $table->date('signed_at')->nullable();
            $table->foreignId('signature_document_id')->nullable()
                  ->constrained('documents')->nullOnDelete();

            // Se verificó que la empresa existe antes de aceptar la
            // tarjeta corporativa.
            $table->boolean('sunbiz_verified')->default(false);

            // pending | active | expired | revoked
            $table->string('status', 20)->default('pending');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_card_authorizations');
    }
};
