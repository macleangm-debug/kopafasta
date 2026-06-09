<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('loan_product_payment_account_overrides');
        Schema::dropIfExists('payment_account_mappings');

        Schema::create('payment_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('payment_type', 40);
            $table->string('payment_method', 30);
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('mobile_money_account_id')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payment_type', 'payment_method'], 'pay_acct_map_type_method_uniq');
            $table->foreign('bank_account_id', 'pay_acct_map_bank_fk')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('mobile_money_account_id', 'pay_acct_map_mm_fk')->references('id')->on('mobile_money_accounts')->nullOnDelete();
        });

        Schema::create('loan_product_payment_account_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_product_id');
            $table->string('payment_type', 40);
            $table->string('payment_method', 30);
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('mobile_money_account_id')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->timestamps();

            $table->unique(['loan_product_id', 'payment_type', 'payment_method'], 'lp_pay_override_uniq');
            $table->foreign('loan_product_id', 'lp_pay_override_product_fk')->references('id')->on('loan_products')->cascadeOnDelete();
            $table->foreign('bank_account_id', 'lp_pay_override_bank_fk')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('mobile_money_account_id', 'lp_pay_override_mm_fk')->references('id')->on('mobile_money_accounts')->nullOnDelete();
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->unsignedBigInteger('customer_id');
            $table->string('payment_type', 40);
            $table->string('payment_method', 30);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 30)->default('pending_verification');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('mobile_money_account_id')->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->text('payment_instructions')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->text('verification_notes')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->date('payment_date')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->nullableMorphs('source');
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->unsignedBigInteger('loan_product_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'payment_type'], 'cust_payments_status_type_idx');
            $table->index(['customer_id', 'created_at'], 'cust_payments_customer_date_idx');

            $table->foreign('customer_id', 'cust_payments_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('bank_account_id', 'cust_payments_bank_fk')->references('id')->on('bank_accounts')->nullOnDelete();
            $table->foreign('mobile_money_account_id', 'cust_payments_mm_fk')->references('id')->on('mobile_money_accounts')->nullOnDelete();
            $table->foreign('verified_by', 'cust_payments_verifier_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('journal_entry_id', 'cust_payments_journal_fk')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('loan_id', 'cust_payments_loan_fk')->references('id')->on('loans')->nullOnDelete();
            $table->foreign('loan_product_id', 'cust_payments_product_fk')->references('id')->on('loan_products')->nullOnDelete();
            $table->foreign('created_by', 'cust_payments_creator_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('loan_product_payment_account_overrides');
        Schema::dropIfExists('payment_account_mappings');
    }
};
