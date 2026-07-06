<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_redemptions')) {
            Schema::create('loyalty_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('option_key', 60);
                $table->string('label');
                $table->string('benefit_type', 40);
                $table->decimal('benefit_value', 10, 4)->default(0);
                $table->string('fee_type', 40)->nullable();
                $table->unsignedInteger('points_spent');
                $table->string('status', 20)->default('active');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
    }
};
