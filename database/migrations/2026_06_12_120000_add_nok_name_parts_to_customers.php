<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'nok_first_name')) {
                $table->string('nok_first_name')->nullable();
            }
            if (! Schema::hasColumn('customers', 'nok_middle_name')) {
                $table->string('nok_middle_name')->nullable();
            }
            if (! Schema::hasColumn('customers', 'nok_last_name')) {
                $table->string('nok_last_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['nok_last_name', 'nok_middle_name', 'nok_first_name'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
