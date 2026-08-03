<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'lga_officer_name')) {
                $table->string('lga_officer_name')->nullable()->after('street');
            }
            if (! Schema::hasColumn('customers', 'lga_officer_position')) {
                $table->string('lga_officer_position', 120)->nullable()->after('lga_officer_name');
            }
            if (! Schema::hasColumn('customers', 'lga_officer_phone')) {
                $table->string('lga_officer_phone', 30)->nullable()->after('lga_officer_position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            foreach (['lga_officer_name', 'lga_officer_position', 'lga_officer_phone'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
