<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_application_drafts')) {
            return;
        }

        Schema::create('loan_application_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->nullable()->constrained('loan_products')->nullOnDelete();
            $table->unsignedBigInteger('asset_reservation_id')->nullable();
            $table->string('phase', 32)->default('browse');
            $table->unsignedSmallInteger('step')->default(0);
            $table->json('payload');
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();

            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_application_drafts');
    }
};
