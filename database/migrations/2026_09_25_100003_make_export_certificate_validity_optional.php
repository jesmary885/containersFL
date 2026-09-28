<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        REUNIÓN 16-09-2026 · El certificado se emite al vender.

        `valid_through` era la única columna obligatoria de la tabla. Tenía
        sentido cuando se creyó que todo contenedor llevaba un certificado
        con vigencia; después de la reunión ya no.

        Un certificado emitido en el acto de la venta no siempre trae una
        fecha "hasta". Obligarla forzaba a teclear una inventada, y un dato
        inventado en una tabla de certificados es peor que un campo vacío:
        parece verdad.

        Se agregan dos columnas:

          issued_at_sale   marca que este certificado salió con la venta, que
                           es el caso normal. Sirve para no confundirlo con
                           la inspección periódica de un tanque.

          service_due_at   la fecha de la PRÓXIMA inspección. Solo la usan
                           los tipos marcados con requires_service_inspection.
                           Vive acá y no en containers porque la emite el
                           mismo inspector que firma el certificado.

        ── Por qué SQLite necesita doctrine/dbal y MySQL no ──

        Cambiar una columna existente a nullable requiere `->change()`. En
        Laravel 11+ ya no hace falta el paquete doctrine/dbal: el cambio va
        nativo. Si el proyecto corriera en una versión anterior, esta
        migración fallaría y habría que instalarlo.
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::table('export_certificates', function (Blueprint $table) {
            $table->date('valid_through')->nullable()->change();

            $table->boolean('issued_at_sale')->default(true)->after('valid_through');
            $table->date('service_due_at')->nullable()->after('issued_at_sale');
        });
    }

    public function down(): void
    {
        Schema::table('export_certificates', function (Blueprint $table) {
            $table->dropColumn(['issued_at_sale', 'service_due_at']);
        });

        /*
         | valid_through se deja nullable a propósito.
         |
         | Volverla obligatoria reventaría si ya hay certificados emitidos
         | al vender, que son justamente los que esta migración permitió
         | guardar sin fecha. Un down() que borra datos del cliente no es
         | un down(), es un accidente.
         */
    }
};
