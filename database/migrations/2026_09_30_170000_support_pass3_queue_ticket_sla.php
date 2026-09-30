<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('resolved_at');
            }
            if (! Schema::hasColumn('support_tickets', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('first_response_at');
            }
            if (! Schema::hasColumn('support_tickets', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('assigned_at');
            }
            if (! Schema::hasColumn('support_tickets', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('escalated_at');
            }
            if (! Schema::hasColumn('support_tickets', 'sla_warned_at')) {
                $table->timestamp('sla_warned_at')->nullable()->after('sla_due_at');
            }
        });

        Schema::table('support_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_conversations', 'waiting_since')) {
                $table->timestamp('waiting_since')->nullable()->after('last_message_at');
            }
            if (! Schema::hasColumn('support_conversations', 'waiting_nudge_level')) {
                $table->unsignedTinyInteger('waiting_nudge_level')->default(0)->after('waiting_since');
            }
            if (! Schema::hasColumn('support_conversations', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('waiting_nudge_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            foreach (['first_response_at', 'assigned_at', 'escalated_at', 'sla_due_at', 'sla_warned_at'] as $col) {
                if (Schema::hasColumn('support_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('support_conversations', function (Blueprint $table): void {
            foreach (['waiting_since', 'waiting_nudge_level', 'accepted_at'] as $col) {
                if (Schema::hasColumn('support_conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
