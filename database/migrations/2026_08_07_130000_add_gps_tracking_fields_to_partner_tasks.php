<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_tasks', 'gps_provider')) {
                $table->string('gps_provider', 40)->nullable()->after('gps_serial');
            }
            if (! Schema::hasColumn('partner_tasks', 'gps_device_id')) {
                $table->string('gps_device_id', 80)->nullable()->after('gps_provider');
            }
            if (! Schema::hasColumn('partner_tasks', 'gps_tracking_url')) {
                $table->string('gps_tracking_url', 500)->nullable()->after('gps_device_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_tasks', function (Blueprint $table) {
            foreach (['gps_tracking_url', 'gps_device_id', 'gps_provider'] as $col) {
                if (Schema::hasColumn('partner_tasks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
