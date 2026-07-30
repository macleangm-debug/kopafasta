<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('loan_groups')) {
            return;
        }

        Schema::table('loan_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_groups', 'application_status')) {
                $table->string('application_status', 40)->nullable();
            }
            if (! Schema::hasColumn('loan_groups', 'scoring_snapshot')) {
                $table->json('scoring_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_groups')) {
            return;
        }

        Schema::table('loan_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_groups', 'scoring_snapshot')) {
                $table->dropColumn('scoring_snapshot');
            }
            if (Schema::hasColumn('loan_groups', 'application_status')) {
                $table->dropColumn('application_status');
            }
        });
    }
};
