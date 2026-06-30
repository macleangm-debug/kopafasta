<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_events') && ! Schema::hasColumn('affiliate_events', 'device_fingerprint')) {
            Schema::table('affiliate_events', function (Blueprint $table): void {
                $table->string('device_fingerprint', 64)->nullable()->after('device_type');
                $table->index('device_fingerprint');
            });
        }

        if (Schema::hasTable('partners')) {
            Schema::table('partners', function (Blueprint $table): void {
                if (! Schema::hasColumn('partners', 'affiliate_risk_flag')) {
                    $table->string('affiliate_risk_flag', 20)->default('low')->after('affiliate_lifecycle_note');
                }
                if (! Schema::hasColumn('partners', 'affiliate_fraud_snapshot')) {
                    $table->json('affiliate_fraud_snapshot')->nullable()->after('affiliate_risk_flag');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('affiliate_events') && Schema::hasColumn('affiliate_events', 'device_fingerprint')) {
            Schema::table('affiliate_events', function (Blueprint $table): void {
                $table->dropIndex(['device_fingerprint']);
                $table->dropColumn('device_fingerprint');
            });
        }

        if (Schema::hasTable('partners')) {
            Schema::table('partners', function (Blueprint $table): void {
                foreach (['affiliate_risk_flag', 'affiliate_fraud_snapshot'] as $column) {
                    if (Schema::hasColumn('partners', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
