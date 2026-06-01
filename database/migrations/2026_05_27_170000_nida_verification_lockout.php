<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'nida_mismatch_attempts')) {
                $table->unsignedTinyInteger('nida_mismatch_attempts')->default(0)->after('nida_verified_source');
            }
            if (! Schema::hasColumn('customers', 'nida_locked_until')) {
                $table->timestamp('nida_locked_until')->nullable()->after('nida_mismatch_attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            foreach (['nida_mismatch_attempts', 'nida_locked_until'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
