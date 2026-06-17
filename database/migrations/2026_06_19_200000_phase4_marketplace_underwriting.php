<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_assets')) {
            Schema::table('marketplace_assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('marketplace_assets', 'insurance_expires_at')) {
                    $table->date('insurance_expires_at')->nullable()->after('insurance_policy_number');
                }
                if (! Schema::hasColumn('marketplace_assets', 'waiting_period_days')) {
                    $table->unsignedSmallInteger('waiting_period_days')->nullable()->after('max_tenure_months');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_assets')) {
            Schema::table('marketplace_assets', function (Blueprint $table): void {
                foreach (['insurance_expires_at', 'waiting_period_days'] as $column) {
                    if (Schema::hasColumn('marketplace_assets', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
