<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            if (! Schema::hasColumn('partners', 'affiliate_premium')) {
                $table->boolean('affiliate_premium')->default(false)->after('affiliate_code');
            }
        });

        DB::table('partners')
            ->where('category', 'affiliate')
            ->update([
                'coverage_type' => 'nationwide',
                'regions' => json_encode([]),
            ]);
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            if (Schema::hasColumn('partners', 'affiliate_premium')) {
                $table->dropColumn('affiliate_premium');
            }
        });
    }
};
