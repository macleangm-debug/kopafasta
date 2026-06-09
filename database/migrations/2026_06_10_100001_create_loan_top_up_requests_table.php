<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_top_up_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_amount', 14, 2);
            $table->string('reason', 500);
            $table->string('status', 30)->default('pending');
            $table->text('decision_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_top_up_requests');
    }
};
