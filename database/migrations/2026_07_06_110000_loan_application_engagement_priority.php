<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_applications') && ! Schema::hasColumn('loan_applications', 'engagement_priority')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->unsignedTinyInteger('engagement_priority')->default(0)->after('submitted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('loan_applications', 'engagement_priority')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->dropColumn('engagement_priority');
            });
        }
    }
};
