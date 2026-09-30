<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_conversations', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('rated_at');
            }
            if (! Schema::hasColumn('support_conversations', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('resolved_at');
            }
            if (! Schema::hasColumn('support_conversations', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->after('closed_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_conversations', 'rating_requested_at')) {
                $table->timestamp('rating_requested_at')->nullable()->after('resolved_by');
            }
        });

        // Immutable SLA snapshot fields at ticket create (settings changes do not rewrite these).
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'sla_target_minutes')) {
                $table->unsignedInteger('sla_target_minutes')->nullable()->after('sla_due_at');
            }
            if (! Schema::hasColumn('support_tickets', 'sla_approaching_pct')) {
                $table->unsignedTinyInteger('sla_approaching_pct')->nullable()->after('sla_target_minutes');
            }
            if (! Schema::hasColumn('support_tickets', 'time_to_resolve_minutes')) {
                $table->unsignedInteger('time_to_resolve_minutes')->nullable()->after('resolved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            if (Schema::hasColumn('support_conversations', 'resolved_by')) {
                $table->dropConstrainedForeignId('resolved_by');
            }
            foreach (['rating_requested_at', 'closed_at', 'resolved_at'] as $col) {
                if (Schema::hasColumn('support_conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            foreach (['time_to_resolve_minutes', 'sla_approaching_pct', 'sla_target_minutes'] as $col) {
                if (Schema::hasColumn('support_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
