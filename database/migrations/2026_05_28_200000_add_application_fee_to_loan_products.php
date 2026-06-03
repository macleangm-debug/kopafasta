<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'application_fee_amount')) {
                $table->unsignedInteger('application_fee_amount')->nullable()->after('max_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_products', 'application_fee_amount')) {
                $table->dropColumn('application_fee_amount');
            }
        });
    }
};
