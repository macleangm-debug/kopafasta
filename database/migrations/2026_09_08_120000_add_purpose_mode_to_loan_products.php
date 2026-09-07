<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'purpose_mode')) {
                $table->string('purpose_mode', 20)->default('free')->after('category');
            }
            if (! Schema::hasColumn('loan_products', 'fixed_purpose')) {
                $table->string('fixed_purpose', 40)->nullable()->after('purpose_mode');
            }
        });

        if (Schema::hasTable('loan_products')) {
            DB::table('loan_products')
                ->where('code', 'KB')
                ->update([
                    'purpose_mode' => 'fixed',
                    'fixed_purpose' => 'agriculture',
                ]);
            DB::table('loan_products')
                ->where('code', 'EL')
                ->update([
                    'purpose_mode' => 'fixed',
                    'fixed_purpose' => 'education',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_products', 'fixed_purpose')) {
                $table->dropColumn('fixed_purpose');
            }
            if (Schema::hasColumn('loan_products', 'purpose_mode')) {
                $table->dropColumn('purpose_mode');
            }
        });
    }
};
