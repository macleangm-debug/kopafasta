<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('payment_type', 40);
            $table->string('payment_method', 30);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('mobile_money_account_id')->nullable()->constrained('mobile_money_accounts')->nullOnDelete();
            $table->text('payment_instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payment_type', 'payment_method']);
        });

        Schema::create('loan_product_payment_account_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_product_id')->constrained('loan_products')->cascadeOnDelete();
            $table->string('payment_type', 40);
            $table->string('payment_method', 30);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('mobile_money_account_id')->nullable()->constrained('mobile_money_accounts')->nullOnDelete();
            $table->text('payment_instructions')->nullable();
            $table->timestamps();

            $table->unique(['loan_product_id', 'payment_type', 'payment_method'], 'lp_payment_override_unique');
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('payment_type', 40);
            $table->string('payment_method', 30);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 30)->default('pending_verification');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('mobile_money_account_id')->nullable()->constrained('mobile_money_accounts')->nullOnDelete();
            $table->string('mobile_number', 20)->nullable();
            $table->text('payment_instructions')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->date('payment_date')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('loan_product_id')->nullable()->constrained('loan_products')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'payment_type']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('loan_product_payment_account_overrides');
        Schema::dropIfExists('payment_account_mappings');
    }
};
