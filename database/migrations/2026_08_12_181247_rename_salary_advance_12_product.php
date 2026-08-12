<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('loan_products')
            ->where('code', 'SAL-12')
            ->where('name', 'Salary Advance 12')
            ->update(['name' => 'Salary Advance']);
    }

    public function down(): void
    {
        DB::table('loan_products')
            ->where('code', 'SAL-12')
            ->where('name', 'Salary Advance')
            ->update(['name' => 'Salary Advance 12']);
    }
};
