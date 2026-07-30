<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('charges_fees') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE charges_fees MODIFY COLUMN charge_when ENUM(
                'application',
                'post_approval',
                'disbursement',
                'repayment',
                'late',
                'event'
            ) NOT NULL DEFAULT 'disbursement'");
        }

        Schema::table('loan_product_post_approval_fees', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_product_post_approval_fees', 'charges_fee_id')) {
                $table->foreignId('charges_fee_id')
                    ->nullable()
                    
                    ->constrained('charges_fees')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_product_post_approval_fees', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_product_post_approval_fees', 'charges_fee_id')) {
                $table->dropConstrainedForeignId('charges_fee_id');
            }
        });
    }
};
