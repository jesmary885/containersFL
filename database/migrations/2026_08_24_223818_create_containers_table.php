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
        Schema::create('containers', function (Blueprint $table) {
      
            $table->id();
            // Se preguntan al registrar. Default: la compañía marcada como
            // is_default_container_owner (FLCHR). Ambos editables.
            $table->foreignId('owner_company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('billing_company_id')->constrained('companies')->restrictOnDelete();

            $table->string('container_number', 15)->nullable()->unique(); // ISO. Nullable
            $table->string('internal_code', 20)->nullable();              // "Unit #3". Nullable

            $table->foreignId('container_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_size_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_condition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('container_grade_id')->nullable()->constrained()->nullOnDelete();
            $table->string('material', 10)->nullable(); // steel|aluminum|frp

            $table->string('status', 20)->default('in_yard');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete(); // si sigue en el depósito
            $table->foreignId('purchase_item_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('acquisition_cost', 12, 2)->nullable();
            $table->decimal('pickup_cost', 12, 2)->default(0);
            $table->decimal('reconditioning_cost', 12, 2)->default(0); // cache de expenses

            $table->unsignedInteger('tare_weight_lbs')->nullable();
            $table->unsignedInteger('max_weight_lbs')->nullable();
            $table->smallInteger('year_manufactured')->nullable();

            $table->boolean('is_export_eligible')->default(false);
            $table->date('csc_valid_through')->nullable(); // cache del último certificado

            $table->date('received_at')->nullable();
            $table->date('sold_at')->nullable();
            $table->text('condition_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('internal_code');
            $table->index(['owner_company_id', 'status']);
            $table->index(['billing_company_id', 'status']);
            $table->index(['container_size_id', 'container_condition_id', 'status'], 'cont_size_cond_status_idx');
            $table->index(['status', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
