<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        REUNIÓN 16-09-2026 · Vigencia del certificado de exportación.

        Lo que se acordó, en palabras de la reunión: para los TANQUES se
        mantiene una fecha de expiración para alertas de servicio, y para los
        contenedores CORRIENTES la vigencia se aplica en el momento de la
        venta.

        Son dos cosas distintas que hasta hoy compartían un solo campo:

          Un contenedor corriente no tiene nada que vigilar mientras está en
          la yarda. Cuando se vende para exportación, el inspector va, lo
          revisa y emite el certificado ese día. Pedir una fecha de vigencia
          al registrarlo obligaba a inventarse un dato.

          Un tanque sí tiene una inspección que vence, y vencida lo deja
          fuera de servicio. Esa fecha hay que verla venir con tiempo.

        La diferencia no es del contenedor sino de SU TIPO, así que la marca
        va en el catálogo de tipos. Mañana entra otro tipo que también
        inspeccione (un reefer con certificación de frío, por ejemplo) y se
        marca desde la pantalla de catálogos, sin tocar código.
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::table('container_types', function (Blueprint $table) {
            $table->boolean('requires_service_inspection')
                  ->default(false)
                  ->after('name_en');
        });
    }

    public function down(): void
    {
        Schema::table('container_types', function (Blueprint $table) {
            $table->dropColumn('requires_service_inspection');
        });
    }
};
