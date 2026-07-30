<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrower_refunds', function (Blueprint $table): void {
            if (! Schema::hasColumn('borrower_refunds', 'accrual_journal_entry_id')) {
                $table->foreignId('accrual_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            }
            if (! Schema::hasColumn('borrower_refunds', 'payout_journal_entry_id')) {
                $table->foreignId('payout_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            }
            if (! Schema::hasColumn('borrower_refunds', 'accrual_posted_at')) {
                $table->timestamp('accrual_posted_at')->nullable();
            }
            if (! Schema::hasColumn('borrower_refunds', 'payout_posted_at')) {
                $table->timestamp('payout_posted_at')->nullable();
            }
            if (! Schema::hasColumn('borrower_refunds', 'disbursement_status')) {
                $table->string('disbursement_status', 30)->nullable();
            }
            if (! Schema::hasColumn('borrower_refunds', 'disbursement_reference')) {
                $table->string('disbursement_reference', 80)->nullable();
            }
            if (! Schema::hasColumn('borrower_refunds', 'disbursement_dispatched_at')) {
                $table->timestamp('disbursement_dispatched_at')->nullable();
            }
            if (! Schema::hasColumn('borrower_refunds', 'disbursement_error')) {
                $table->text('disbursement_error')->nullable();
            }
            if (! Schema::hasColumn('borrower_refunds', 'disbursement_mobile_money_account_id')) {
                $table->foreignId('disbursement_mobile_money_account_id')->nullable()->constrained('mobile_money_accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('borrower_refunds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('disbursement_mobile_money_account_id');
            $table->dropConstrainedForeignId('payout_journal_entry_id');
            $table->dropConstrainedForeignId('accrual_journal_entry_id');
            $table->dropColumn([
                'accrual_posted_at',
                'payout_posted_at',
                'disbursement_status',
                'disbursement_reference',
                'disbursement_dispatched_at',
                'disbursement_error',
            ]);
        });
    }
};
