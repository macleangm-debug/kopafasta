<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('borrower_refunds')) {
            return;
        }

        Schema::create('borrower_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_auction_settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 40)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 30)->default('pending');
            $table->string('payout_channel', 30)->nullable();
            $table->string('payout_phone', 30)->nullable();
            $table->string('payout_account_name', 120)->nullable();
            $table->string('payout_account_number', 80)->nullable();
            $table->string('payout_provider', 40)->nullable();
            $table->timestamp('details_submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrower_refunds');
    }
};
