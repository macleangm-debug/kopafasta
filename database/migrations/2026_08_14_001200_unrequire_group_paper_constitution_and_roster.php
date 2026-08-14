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
            ->where(function ($query) {
                $query->whereIn('name', ['Group constitution', 'Group member roster'])
                    ->orWhere('name', 'like', '%group constitution%')
                    ->orWhere('name', 'like', '%group member roster%');
            })
            ->update(['is_required' => false]);
    }

    public function down(): void
    {
        // Intentionally empty — paper constitution / roster must not block screening.
    }
};
