<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personas dentro de la empresa cliente.
     * Es común que llamen dando el nombre del empleado, no el de la empresa,
     * por eso el buscador de clientes también busca acá.
     */
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete: un contacto sin cliente no significa nada.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('role', 50)->nullable();   // "Accounting", "Operaciones"
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();

            // El contacto principal: el que sale primero en la ficha.
            $table->boolean('is_primary')->default(false);

            /* -------------------------------------------------------------
             | A QUIÉN AVISAR DE QUÉ
             |
             | Separado a propósito: al de contabilidad le mandas las
             | facturas, al de operaciones los recordatorios de recogida.
             | No siempre es la misma persona.
             * ---------------------------------------------------------- */
            $table->boolean('notify_invoices')->default(true);
            $table->boolean('notify_reminders')->default(true);
            $table->string('preferred_channel', 10)->default('email'); // email|sms|both

            $table->timestamps();

            // El buscador de clientes también busca acá dentro.
            $table->index('email');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
    }
};
