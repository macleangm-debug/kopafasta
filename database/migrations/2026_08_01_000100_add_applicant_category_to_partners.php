<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partners')) {
            return;
        }

        Schema::table('partners', function (Blueprint $table): void {
            if (! Schema::hasColumn('partners', 'applicant_category')) {
                $table->string('applicant_category', 30)->default('company')->after('category');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partners') || ! Schema::hasColumn('partners', 'applicant_category')) {
            return;
        }

        Schema::table('partners', function (Blueprint $table): void {
            $table->dropColumn('applicant_category');
        });
    }
};
