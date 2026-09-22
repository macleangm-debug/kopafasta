<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('loan_products', 'use_general_grace_period')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->boolean('use_general_grace_period')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('loan_products', 'use_general_grace_period')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->dropColumn('use_general_grace_period');
            });
        }
    }
};
