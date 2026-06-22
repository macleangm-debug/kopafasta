<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_products') || ! Schema::hasColumn('loan_products', 'repayment_cadence')) {
            return;
        }

        DB::table('loan_products')
            ->where('category', 'group')
            ->where(function ($query): void {
                $query->whereNull('repayment_cadence')
                    ->orWhere('repayment_cadence', '!=', 'monthly');
            })
            ->update(['repayment_cadence' => 'monthly']);
    }

    public function down(): void
    {
        // Intentionally no-op: monthly cadence is the required default for group products.
    }
};
