<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        REUNIÓN 16-09-2026 · Que la comisión llegue a existir.

        ── EL PROBLEMA QUE SE ENCONTRÓ ──

        La maquinaria de comisiones estaba completa: tabla, abonos parciales,
        observador, saldos, y un CommissionResolver que corre al emitir la
        factura. Pero NO CREABA NINGUNA COMISIÓN. Nunca.

        El motivo es un desencuentro entre dos columnas:

          El formulario de la factura guarda el vendedor en
          `sold_by_employee_id`, que apunta a TRABAJADORES.

          El CommissionResolver busca el vendedor en `salesperson_id`, que
          apunta a USUARIOS del sistema.

        Como el formulario nunca llenaba `salesperson_id`, el resolver salía
        por la primera línea y devolvía null. Se elegía el vendedor, se
        emitía la factura, y la comisión no aparecía en ninguna parte.

        ── POR QUÉ GANA TRABAJADORES ──

        Porque es lo que dice el negocio. El propio proyecto lo tiene
        escrito: "Miguelito vende desde 2024 y probablemente nunca ha
        abierto el sistema". Exigir que un vendedor tenga cuenta para poder
        cobrar comisión deja fuera justo a los que venden.

        `salesperson_id` se queda (es quien registró, si acaso) pero pasa a
        ser opcional, y la comisión se cuelga de `employee_id`.

        ── LA BASE DE CÁLCULO ──

        `invoices.commission_base` congela en el documento si el porcentaje
        salió de toda la venta o solo de los contenedores. Se propone desde
        la ficha del vendedor y se puede cambiar en cada factura.

        Va en la factura y no solo en el trabajador porque lo pactado se
        congela en el documento (RB-058): si mañana cambia el acuerdo, las
        facturas viejas tienen que seguir explicando su propio número.
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 'subtotal' | 'containers'
            $table->string('commission_base', 20)
                  ->default('subtotal')
                  ->after('commission_amount');
        });

        Schema::table('commissions', function (Blueprint $table) {

            /*
             | El vendedor de verdad: un trabajador, tenga cuenta o no.
             |
             | nullable para no romper las filas que ya existan, aunque hoy
             | no hay ninguna — precisamente porque nunca se creó ninguna.
             */
            $table->foreignId('employee_id')
                  ->nullable()
                  ->after('salesperson_id')
                  ->constrained('employees')
                  ->restrictOnDelete();

            $table->string('base_type', 20)->default('subtotal')->after('base_amount');

            $table->index(['employee_id', 'status']);   // "¿cuánto le debo a Miguelito?"
        });

        /*
         | salesperson_id deja de ser obligatoria.
         |
         | Se hace en un Schema::table aparte porque cambiar una columna y
         | agregar otra en el mismo bloque da problemas en algunos motores.
         */
        Schema::table('commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('salesperson_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'status']);
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('base_type');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('commission_base');
        });

        /*
         | salesperson_id se deja nullable. Volverla obligatoria reventaría
         | con las comisiones que esta migración permitió crear sin usuario,
         | que son la mayoría.
         */
    }
};
