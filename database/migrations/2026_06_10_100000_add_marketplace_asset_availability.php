<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_assets', 'availability_status')) {
                $table->string('availability_status', 20)->default('available')->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_assets', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_assets', 'availability_status')) {
                $table->dropColumn('availability_status');
            }
        });
    }
};
