<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('branch_id');
            }
        });

        if (Schema::hasTable('asset_auction_settlements')) {
            return;
        }

        Schema::create('asset_auction_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arrear_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recovery_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('outstanding_before', 15, 2);
            $table->decimal('recovery_costs', 15, 2)->default(0);
            $table->decimal('auction_proceeds', 15, 2);
            $table->decimal('outstanding_applied', 15, 2)->default(0);
            $table->decimal('recovery_applied', 15, 2)->default(0);
            $table->decimal('borrower_refund', 15, 2)->default(0);
            $table->decimal('remaining_balance', 15, 2)->default(0);
            $table->boolean('loan_closed')->default(false);
            $table->foreignId('repayment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_auction_settlements');

        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'country_code')) {
                $table->dropColumn('country_code');
            }
        });
    }
};
