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
        Schema::create('depots', function (Blueprint $table) {
             $table->id();
            $table->string('code', 20)->nullable()->unique();
            $table->string('name', 200);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            $table->json('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            // Montos sugeridos. Se copian a la operación y allí son editables.
            $table->decimal('default_pickup_fee', 12, 2)->nullable();
            $table->decimal('daily_late_fee', 12, 2)->nullable();
            $table->tinyInteger('default_pickup_days')->nullable(); // plazo de retiro del release
            $table->decimal('default_miles', 8, 2)->nullable();     // distancia habitual a la yarda

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depots');
    }
};
