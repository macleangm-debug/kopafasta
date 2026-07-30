<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_histories', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_histories', 'referral_discount_amount')) {
                $table->decimal('referral_discount_amount', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('membership_histories', 'wallet_amount_used')) {
                $table->decimal('wallet_amount_used', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('membership_histories', 'cash_amount_paid')) {
                $table->decimal('cash_amount_paid', 15, 2)->nullable();
            }
        });

        Schema::table('loan_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_applications', 'referral_discount_amount')) {
                $table->decimal('referral_discount_amount', 15, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('membership_histories', function (Blueprint $table): void {
            foreach (['referral_discount_amount', 'wallet_amount_used', 'cash_amount_paid'] as $column) {
                if (Schema::hasColumn('membership_histories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('loan_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_applications', 'referral_discount_amount')) {
                $table->dropColumn('referral_discount_amount');
            }
        });
    }
};
