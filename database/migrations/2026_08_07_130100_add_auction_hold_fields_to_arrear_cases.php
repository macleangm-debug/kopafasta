<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arrear_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('arrear_cases', 'repossessed_at')) {
                $table->timestamp('repossessed_at')->nullable()->after('last_follow_up_at');
            }
            if (! Schema::hasColumn('arrear_cases', 'auction_eligible_at')) {
                $table->timestamp('auction_eligible_at')->nullable()->after('repossessed_at');
            }
            if (! Schema::hasColumn('arrear_cases', 'auction_status')) {
                $table->string('auction_status', 40)->nullable()->after('auction_eligible_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('arrear_cases', function (Blueprint $table) {
            foreach (['auction_status', 'auction_eligible_at', 'repossessed_at'] as $col) {
                if (Schema::hasColumn('arrear_cases', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
