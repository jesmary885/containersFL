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
        Schema::create('container_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();   // 10FT, 20FT, 20FT-HC, 40FT-STD, 40FT-HC, 45FT-HC
            $table->string('name', 100);
            $table->decimal('length_ft', 4, 1)->nullable();
            $table->boolean('is_high_cube')->default(false);
            $table->unsignedInteger('default_tare_lbs')->nullable();
            $table->unsignedInteger('default_max_lbs')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_sizes');
    }
};
