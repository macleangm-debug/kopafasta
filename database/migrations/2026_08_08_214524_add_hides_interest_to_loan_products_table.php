<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_products', 'hides_interest')) {
                $table->boolean('hides_interest')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            if (Schema::hasColumn('loan_products', 'hides_interest')) {
                $table->dropColumn('hides_interest');
            }
        });
    }
};
