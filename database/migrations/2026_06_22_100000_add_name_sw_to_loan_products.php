<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'name_sw')) {
                $table->string('name_sw', 150)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_products', 'name_sw')) {
                $table->dropColumn('name_sw');
            }
        });
    }
};
