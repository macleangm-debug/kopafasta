<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'short_description')) {
                $table->string('short_description', 90)->nullable()->after('description');
            }
            if (! Schema::hasColumn('loan_products', 'short_description_sw')) {
                $table->string('short_description_sw', 90)->nullable()->after('short_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_products', 'short_description_sw')) {
                $table->dropColumn('short_description_sw');
            }
            if (Schema::hasColumn('loan_products', 'short_description')) {
                $table->dropColumn('short_description');
            }
        });
    }
};
