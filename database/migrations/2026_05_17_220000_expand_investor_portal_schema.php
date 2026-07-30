<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lenders', function (Blueprint $table): void {
            if (! Schema::hasColumn('lenders', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('lenders', 'available_balance')) {
                $table->decimal('available_balance', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('lenders', 'risk_preference')) {
                $table->string('risk_preference', 20)->default('medium'); // low | medium | high
            }
            if (! Schema::hasColumn('lenders', 'auto_invest')) {
                $table->boolean('auto_invest')->default(false);
            }
        });

        Schema::table('funding_pools', function (Blueprint $table): void {
            if (! Schema::hasColumn('funding_pools', 'pool_type')) {
                $table->string('pool_type', 30)->default('business'); // salary | business | car | emergency
            }
            if (! Schema::hasColumn('funding_pools', 'risk_level')) {
                $table->string('risk_level', 20)->default('medium'); // low | medium | high
            }
            if (! Schema::hasColumn('funding_pools', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('funding_pools', 'active_borrowers')) {
                $table->unsignedInteger('active_borrowers')->default(0);
            }
            if (! Schema::hasColumn('funding_pools', 'repayment_rate')) {
                $table->decimal('repayment_rate', 5, 2)->default(0); // %
            }
            if (! Schema::hasColumn('funding_pools', 'default_rate')) {
                $table->decimal('default_rate', 5, 2)->default(0); // %
            }
            if (! Schema::hasColumn('funding_pools', 'min_investment')) {
                $table->decimal('min_investment', 18, 2)->default(50000);
            }
            if (! Schema::hasColumn('funding_pools', 'is_public')) {
                $table->boolean('is_public')->default(true);
            }
        });

        if (! Schema::hasTable('lender_transactions')) {
            Schema::create('lender_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
                $table->foreignId('funding_pool_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('lender_investment_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference', 40)->unique();
                $table->string('type', 20); // deposit | withdrawal | investment | return | fee
                $table->decimal('amount', 18, 2);
                $table->string('status', 20)->default('completed'); // pending | completed | failed
                $table->string('channel', 30)->default('bank'); // bank | mobile_money | stablecoin | system
                $table->string('payment_reference', 80)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->index(['lender_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lender_transactions');

        Schema::table('funding_pools', function (Blueprint $table): void {
            foreach (['pool_type','risk_level','description','active_borrowers','repayment_rate','default_rate','min_investment','is_public'] as $col) {
                if (Schema::hasColumn('funding_pools', $col)) $table->dropColumn($col);
            }
        });

        Schema::table('lenders', function (Blueprint $table): void {
            if (Schema::hasColumn('lenders', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            foreach (['available_balance','risk_preference','auto_invest'] as $col) {
                if (Schema::hasColumn('lenders', $col)) $table->dropColumn($col);
            }
        });
    }
};
