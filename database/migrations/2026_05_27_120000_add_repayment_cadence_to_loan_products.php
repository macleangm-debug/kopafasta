<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            $table->string('repayment_cadence', 20)->default('weekly')->after('tenure_max_months');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            $table->dropColumn('repayment_cadence');
        });
    }
};
