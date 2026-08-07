<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_actions', function (Blueprint $table) {
            if (! Schema::hasColumn('collection_actions', 'recovery_assignment_id')) {
                $table->foreignId('recovery_assignment_id')
                    ->nullable()
                    ->after('arrear_case_id')
                    ->constrained('recovery_assignments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('collection_actions', function (Blueprint $table) {
            if (Schema::hasColumn('collection_actions', 'recovery_assignment_id')) {
                $table->dropConstrainedForeignId('recovery_assignment_id');
            }
        });
    }
};
