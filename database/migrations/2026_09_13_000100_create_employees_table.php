<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TRABAJADORES DE LA EMPRESA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── POR QUE HACE FALTA ESTA TABLA ──
 *
 * Lo dice la minuta del 8 de agosto, textual:
 *
 *   "No existe ficha de choferes ni de trabajadores: hoy solo aparece el
 *    nombre suelto dentro del viaje."
 *
 * Y Erik lo amplio en la misma reunion: nomina completa —choferes,
 * secretaria, administrador, personal de limpieza— como gasto que reduce
 * la ganancia real.
 *
 * ── POR QUE NO SIRVE LA TABLA `users` ──
 *
 * `users` es quien ENTRA al sistema. Miguelito vende contenedores y
 * cobra comision, y probablemente nunca abra el sistema. La secretaria
 * que registra no es la que vendio.
 *
 * Mezclarlos obligaria a crear un usuario con contrasena para cada
 * persona que aparece en una comision, y a mantener activos usuarios que
 * nadie usa.
 *
 * ── POR QUE NO SIRVE `drivers` ──
 *
 * Esa tabla ya existe y es especifica: licencia, certificado medico,
 * porcentaje de pago por viaje. Un vendedor no tiene nada de eso.
 *
 * Un chofer puede estar en las dos: aqui como trabajador, y en `drivers`
 * con sus papeles. `driver_id` los enlaza.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            /*
             | Puede trabajar para una empresa o para las dos.
             |
             | null significa "para las dos": Denisse administra FLCHR y
             | RST, y obligarla a existir dos veces seria tener dos fichas
             | de la misma persona que hay que mantener a la par.
             */
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();

            // vendedor | chofer | secretaria | administrador | limpieza | taller | otro
            $table->string('role', 30)->default('vendedor');

            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            $table->date('hired_at')->nullable();

            /*
             | Lo que se le paga de comision por defecto.
             |
             | En el Excel las comisiones son MONTOS PACTADOS, no
             | porcentajes: $300, $400, $100, $1,100. Por eso el monto va
             | primero. El porcentaje esta por si alguno cobra asi.
             */
            $table->decimal('default_commission_amount', 12, 2)->nullable();
            $table->decimal('default_commission_percent', 5, 2)->nullable();

            // Si ademas maneja, su ficha de chofer con licencia y papeles.
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();

            // Si ademas entra al sistema, su usuario.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'role']);
            $table->index('first_name');
        });

        /*
         | QUIEN VENDIO, EN LA FACTURA
         |
         | La comision es una columna de la venta en el Excel. Denisse:
         | "en el Excel dice qué tipo de contenedor, el número del
         | contenedor, si hay comisión, porque tenemos vendedores".
         |
         | Va en la factura y no en un modulo aparte porque es un dato de
         | la venta, no un tramite posterior.
         */
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('sold_by_employee_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sold_by_employee_id');
        });

        Schema::dropIfExists('employees');
    }
};
