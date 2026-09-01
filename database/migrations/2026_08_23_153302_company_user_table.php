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
        Schema::create('company_user', function (Blueprint $table) {
             $table->id();

            /* -------------------------------------------------------------
             | LAS DOS PUNTAS
             |
             | cascadeOnDelete en ambas: si se borra el usuario o la
             | compañía, esta conexión deja de tener sentido y se va con
             | ellos. No se pierde nada importante, es solo un enlace.
             * ---------------------------------------------------------- */
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /* -------------------------------------------------------------
             | CUÁL ABRE POR DEFECTO
             |
             | NO limita el acceso. Solo dice cuál se selecciona sola al
             | iniciar sesión. El usuario puede cambiar a la otra desde el
             | selector del header cuando quiera.
             * ---------------------------------------------------------- */
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // Impide agregar dos veces al mismo usuario a la misma compañía.
            $table->unique(['company_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
