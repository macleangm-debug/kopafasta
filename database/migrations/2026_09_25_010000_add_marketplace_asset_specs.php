<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_assets')) {
            return;
        }

        Schema::table('marketplace_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_assets', 'specs')) {
                $table->json('specs')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_assets') || ! Schema::hasColumn('marketplace_assets', 'specs')) {
            return;
        }

        Schema::table('marketplace_assets', function (Blueprint $table): void {
            $table->dropColumn('specs');
        });
    }
};
