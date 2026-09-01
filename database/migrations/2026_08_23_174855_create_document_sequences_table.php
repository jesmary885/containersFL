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
        Schema::create('document_sequences', function (Blueprint $table) {
             $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            /* -------------------------------------------------------------
             | QUÉ SE NUMERA
             |
             | Cada tipo lleva su propio contador, y cada compañía el suyo.
             | Las facturas de FLCHR van por 1358 y las de RST por 1240,
             | sin cruzarse.
             |
             | Valores: estimate | invoice | sale | payment | purchase |
             |          rental | trip | expense | settlement | commission |
             |          export_certificate
             * ---------------------------------------------------------- */
            $table->string('type', 30);

            /* -------------------------------------------------------------
             | CÓMO SE VE EL NÚMERO
             |
             | prefix      = "INV-", "EST-", o vacío
             | next_number = el próximo que se va a entregar
             | padding     = ceros a la izquierda: 4 -> "0001", 6 -> "000001"
             |
             | Con prefix "INV-", next_number 1358 y padding 4:
             |   resultado -> INV-1358
             * ---------------------------------------------------------- */
            $table->string('prefix', 10)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->tinyInteger('padding')->default(4);

            /* -------------------------------------------------------------
             | REINICIO ANUAL
             |
             | Si resets_yearly está activo y cambió el año, el contador
             | vuelve a 1. current_year guarda en qué año va, para saber
             | cuándo tocó el cambio.
             * ---------------------------------------------------------- */
            $table->boolean('resets_yearly')->default(false);
            $table->smallInteger('current_year')->nullable();

            $table->timestamps();

            // Un solo contador por tipo y compañía. Dos filas de
            // "invoice" para FLCHR sería el caos.
            $table->unique(['company_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
