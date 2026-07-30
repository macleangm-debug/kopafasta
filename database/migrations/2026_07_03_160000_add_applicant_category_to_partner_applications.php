<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_applications', 'applicant_category')) {
                $table->string('applicant_category', 30)->default('individual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('partner_applications', 'applicant_category')) {
                $table->dropColumn('applicant_category');
            }
        });
    }
};
