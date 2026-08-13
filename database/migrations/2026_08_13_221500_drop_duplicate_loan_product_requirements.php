<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_product_requirements')) {
            return;
        }

        DB::table('loan_product_requirements')
            ->whereIn('name', [
                'Passport photo',
                'Source of income proof',
                '3 months bank statement',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Intentionally empty — these rows duplicated Face / Income verification.
    }
};
