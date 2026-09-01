<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        Cada movimiento de camión. Es la tabla que conecta las dos compañías
        cuando RST mueve un contenedor de FLCHR, este viaje se convierte en una
        factura de una empresa a la otra.
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
             $table->id();

            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('trip_number', 20);

            /* -------------------------------------------------------------
             | QUÉ VIAJE ES
             |
             | delivery      = yarda -> cliente
             | pickup        = depósito -> yarda
             | move          = cliente -> cliente
             | repositioning = yarda -> yarda (interno, no se cobra)
             |
             | El tipo cambia cómo se sugiere el precio: el pickup usa el
             | fee fijo del depósito; el resto, millas × tarifa.
             * ---------------------------------------------------------- */
            $table->string('type', 20)->default('delivery');
            $table->string('status', 20)->default('scheduled');

            // Texto libre de la hoja VIAJES del Excel ("PUERTO", etc.).
            // Se conserva para poder cruzar con lo que ellos ya tienen.
            $table->string('category', 50)->nullable();

            /* -------------------------------------------------------------
             | QUIÉN LO HACE
             |
             | Todos nullable porque un viaje se programa antes de saber
             | qué chofer ni qué camión va a estar libre.
             * ---------------------------------------------------------- */
            $table->foreignId('carrier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | POR QUÉ SE HACE
             |
             | Un viaje puede venir de una venta (entregar), de una renta
             | (entregar o recoger) o de una compra (retirar del depósito).
             |
             | Se usan cuatro columnas en vez de un morph porque acá sí
             | interesa poder consultar directo "todos los viajes de esta
             | venta" con una llave foránea real.
             * ---------------------------------------------------------- */
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | DE DÓNDE A DÓNDE
             |
             | El origen puede ser un depósito de proveedor o una ubicación
             | propia. Por eso hay dos columnas y normalmente solo una
             | tiene valor.
             |
             | Las direcciones van en JSON porque son copias congeladas:
             | si el cliente se muda, este viaje sigue diciendo dónde se
             | entregó de verdad.
             * ---------------------------------------------------------- */
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('origin_location_id')->nullable()
                  ->constrained('locations')->nullOnDelete();
            $table->json('origin_address')->nullable();
            $table->json('destination_address')->nullable();
            $table->string('destination_zip', 10)->nullable();
            $table->decimal('miles', 8, 2)->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            /* -------------------------------------------------------------
             | EL DINERO DEL VIAJE  ← lo más importante de esta tabla
             |
             | Hay tres montos distintos y conviene no confundirlos:
             |
             |   customer_price = lo que se le cobra al CLIENTE FINAL
             |   carrier_cost   = lo que RST le cobra a FLCHR (intercompañía)
             |   driver_pay     = lo que se le paga al CHOFER
             |
             | Los tres se precargan y los tres son editables.
             |
             | driver_pay_percent y driver_pay conviven a propósito: el
             | porcentaje es la sugerencia (30% del precio al cliente),
             | driver_pay es lo que realmente se le paga. Si alguien
             | escribe un monto a mano, ese manda sobre el porcentaje.
             * ---------------------------------------------------------- */
            $table->decimal('rate_per_mile', 12, 2)->nullable();
            $table->decimal('pickup_fee', 12, 2)->default(0);
            $table->decimal('customer_price', 12, 2)->default(0);
            $table->decimal('carrier_cost', 12, 2)->default(0);
            $table->decimal('driver_pay_percent', 5, 2)->nullable();
            $table->decimal('driver_pay', 12, 2)->default(0);

            /* -------------------------------------------------------------
             | DOS ESTADOS DE PAGO SEPARADOS
             |
             | driver_payment_status = ¿ya se le pagó al chofer?
             |   pending -> settled (entró en una liquidación) -> paid
             |
             | trip_payment_status = ¿ya se cobró el viaje?
             |
             | Están separados porque avanzan por su cuenta: se le puede
             | pagar al chofer antes de que el cliente pague.
             * ---------------------------------------------------------- */
            $table->string('driver_payment_status', 20)->default('pending');
            $table->string('trip_payment_status', 20)->default('pending');

            /* -------------------------------------------------------------
             | LOS DOS DOCUMENTOS QUE LO CIERRAN
             |
             | Sin constrained(): invoices y driver_settlements se crean
             | después. Se conectan en el bloque 8.
             |
             | intercompany_invoice_id vacío + status completed = este
             | viaje todavía no se le facturó a la otra compañía. Esa
             | consulta es la base del proceso semanal.
             * ---------------------------------------------------------- */
            $table->unsignedBigInteger('intercompany_invoice_id')->nullable();
            $table->unsignedBigInteger('driver_settlement_id')->nullable();

            // POD = Proof of Delivery, la firma del cliente.
            $table->foreignId('pod_document_id')->nullable()
                  ->constrained('documents')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* -------------------------------------------------------------
             | ÍNDICES
             * ---------------------------------------------------------- */
            $table->unique(['company_id', 'trip_number']);

            // "viajes completados de esta compañía en este período"
            $table->index(['company_id', 'status', 'completed_at']);

            // "¿cuáles faltan por facturar a la otra compañía?"
            $table->index('intercompany_invoice_id');

            // "¿cuánto le debemos a este chofer?"
            $table->index(['driver_id', 'driver_payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
