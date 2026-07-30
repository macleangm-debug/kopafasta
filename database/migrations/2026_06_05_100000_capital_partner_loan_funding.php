<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'uses_capital_partner')) {
                $table->boolean('uses_capital_partner')->default(true);
            }
        });

        if (Schema::hasTable('loan_products')) {
            DB::table('loan_products')->update(['uses_capital_partner' => true]);
            DB::table('loan_products')
                ->whereIn('category', ['asset_finance', 'asset_lending'])
                ->orWhere('code', 'AST-36')
                ->update(['uses_capital_partner' => false]);
        }

        if (! Schema::hasTable('loan_capital_allocations')) {
            Schema::create('loan_capital_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
                $table->foreignId('funding_pool_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('lender_investment_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('allocated_principal', 18, 2);
                $table->decimal('allocation_percent', 8, 4);
                $table->decimal('partner_interest_share_percent', 5, 2)->default(60);
                $table->decimal('company_interest_share_percent', 5, 2)->default(40);
                $table->decimal('interest_earned_partner', 18, 2)->default(0);
                $table->decimal('interest_earned_company', 18, 2)->default(0);
                $table->decimal('outstanding_exposure', 18, 2)->default(0);
                $table->timestamps();
                $table->unique(['loan_id', 'lender_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_capital_allocations');

        Schema::table('loan_products', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_products', 'uses_capital_partner')) {
                $table->dropColumn('uses_capital_partner');
            }
        });
    }
};
