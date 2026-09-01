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
        Schema::create('rental_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();

            // restrict: no se borra un contenedor que está rentado.
            $table->foreignId('container_id')->constrained()->restrictOnDelete();

            $table->decimal('monthly_rate', 12, 2);
            $table->date('from_date');
            $table->date('to_date')->nullable();   // null = sigue rentado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_containers');
    }
};
