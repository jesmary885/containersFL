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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number', 20)->unique();
            $table->string('type', 20)->default('individual'); // individual|business
            $table->string('company_name', 200)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 200);
            $table->string('primary_phone', 30)->nullable();
            $table->string('primary_email', 150)->nullable();

            $table->boolean('tax_exempt')->default(false); // cache del certificado vigente
            $table->boolean('sunbiz_verified')->default(false);
            $table->timestamp('sunbiz_verified_at')->nullable();
            $table->foreignId('sunbiz_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sunbiz_document_number', 30)->nullable();

            $table->boolean('allow_credit_card')->default(false);
            $table->boolean('credit_hold')->default(false);
            $table->string('source', 30)->nullable(); // facebook|referral|walk-in
            // Solo para el cliente intercompañía (FLCHR como cliente de RS Transport)
            $table->foreignId('related_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('display_name');
            $table->index('primary_phone');
            $table->index('primary_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
