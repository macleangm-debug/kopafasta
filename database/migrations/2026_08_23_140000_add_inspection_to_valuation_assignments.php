<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('valuation_assignments')) {
            return;
        }

        Schema::table('valuation_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('valuation_assignments', 'inspection')) {
                $table->json('inspection')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('valuation_assignments')) {
            return;
        }

        Schema::table('valuation_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('valuation_assignments', 'inspection')) {
                $table->dropColumn('inspection');
            }
        });
    }
};
