<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'nok_ward')) {
                $table->string('nok_ward')->nullable()->after('nok_district');
            }
            if (! Schema::hasColumn('customers', 'nok_street')) {
                $table->string('nok_street')->nullable()->after('nok_ward');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'nok_street')) {
                $table->dropColumn('nok_street');
            }
            if (Schema::hasColumn('customers', 'nok_ward')) {
                $table->dropColumn('nok_ward');
            }
        });
    }
};
