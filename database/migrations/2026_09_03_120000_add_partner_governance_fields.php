<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            if (! Schema::hasColumn('partners', 'performance_status')) {
                $table->string('performance_status', 40)->nullable()->after('affiliate_performance_status');
            }
            if (! Schema::hasColumn('partners', 'suspend_kind')) {
                $table->string('suspend_kind', 40)->nullable()->after('performance_status');
            }
        });

        Schema::table('recovery_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('recovery_assignments', 'sla_reminder_meta')) {
                $table->json('sla_reminder_meta')->nullable();
            }
        });

        $existing = \App\Models\Setting::get('partners.terms');
        $terms = is_array($existing) ? $existing : config('partners.terms', []);
        if (empty($terms['launched_at'])) {
            $terms['launched_at'] = now()->toIso8601String();
            \App\Models\Setting::set('partners.terms', $terms);
        }
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table): void {
            if (Schema::hasColumn('partners', 'suspend_kind')) {
                $table->dropColumn('suspend_kind');
            }
            if (Schema::hasColumn('partners', 'performance_status')) {
                $table->dropColumn('performance_status');
            }
        });

        Schema::table('recovery_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('recovery_assignments', 'sla_reminder_meta')) {
                $table->dropColumn('sla_reminder_meta');
            }
        });
    }
};
