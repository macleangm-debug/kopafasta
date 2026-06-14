<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('write_off_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arrear_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('write_off_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('status')->default('recommended');
            $table->boolean('auto_proposed')->default(false);
            $table->foreignId('recommended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recommended_at')->nullable();
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable();
            $table->text('manager_notes')->nullable();
            $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finance_approved_at')->nullable();
            $table->text('finance_notes')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('write_off_requests');
    }
};
