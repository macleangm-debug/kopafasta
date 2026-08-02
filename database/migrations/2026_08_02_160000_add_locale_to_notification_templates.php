<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('notification_templates', 'locale')) {
                $table->string('locale', 5)->default('en')->after('code');
            }
        });

        Schema::table('notification_templates', function (Blueprint $table): void {
            try {
                $table->dropUnique(['code']);
            } catch (\Throwable) {
            }
        });

        DB::table('notification_templates')
            ->where(function ($q): void {
                $q->whereNull('locale')->orWhere('locale', '');
            })
            ->update(['locale' => 'en']);

        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->unique(['code', 'locale'], 'notification_templates_code_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table): void {
            try {
                $table->dropUnique('notification_templates_code_locale_unique');
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('notification_templates', 'locale')) {
                $table->dropColumn('locale');
            }
        });
    }
};
