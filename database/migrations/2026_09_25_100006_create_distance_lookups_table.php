<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        REUNIÓN 16-09-2026 · Cálculo automático de millas.

        Se acordó calcular las millas con la API de Google Maps a partir del
        código postal del destino.

        Esta tabla es la MEMORIA de esas consultas.

        ── POR QUÉ HACE FALTA ──

        Google cobra por consulta. Sin memoria, cotizar tres veces al mismo
        cliente son tres consultas pagadas por la misma respuesta, y la
        distancia entre dos códigos postales no cambia nunca.

        Con la yarda en Miami y clientes repetidos en el sur de Florida, la
        mayoría de las cotizaciones van a caer sobre un puñado de códigos
        postales. La segunda vez ya no se le pregunta a Google.

        ── POR QUÉ UNA TABLA Y NO EL CACHÉ DE LARAVEL ──

        Porque el caché se vacía. Un `php artisan cache:clear` en un deploy
        borraría meses de consultas pagadas. Esto es un dato que vale dinero
        y merece una tabla.

        Además permite corregir a mano: si Google devuelve una ruta rara,
        alguien edita la fila y el sistema deja de equivocarse.

        ── SE GUARDA TAMBIÉN EL FALLO ──

        Un código postal mal escrito hace que Google conteste "no encontrado".
        Si eso no se guarda, cada intento de cotizar vuelve a preguntarle y
        vuelve a pagar por el mismo "no". Con `status` se guarda el no y no
        se insiste.
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::create('distance_lookups', function (Blueprint $table) {
            $table->id();

            /*
             | El origen se guarda como texto y no como depot_id porque a
             | veces la salida es la yarda (una dirección propia) y a veces
             | un depósito. Lo que importa para la distancia es el punto,
             | no de quién es.
             */
            $table->string('origin', 120);
            $table->string('destination', 120);

            $table->decimal('miles', 8, 2)->nullable();
            $table->unsignedInteger('minutes')->nullable();

            // ok | not_found | error
            $table->string('status', 20)->default('ok');

            /*
             | manual = alguien escribió esta distancia a mano.
             |
             | Las manuales NO se refrescan aunque caduquen: si alguien la
             | corrigió es porque Google se equivocaba.
             */
            $table->boolean('is_manual')->default(false);

            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['origin', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distance_lookups');
    }
};
