<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_conversations', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('support_conversations', 'guest_phone')) {
                $table->string('guest_phone', 32)->nullable()->after('guest_name');
            }
            if (! Schema::hasColumn('support_conversations', 'topic')) {
                $table->string('topic')->nullable()->after('channel');
            }
        });

        Schema::table('support_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_automated');
            }
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'support_conversation_id')) {
                $table->foreignId('support_conversation_id')->nullable()->after('customer_id')
                    ->constrained('support_conversations')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'related_type')) {
                $table->string('related_type', 64)->nullable()->after('category');
            }
            if (! Schema::hasColumn('support_tickets', 'related_id')) {
                $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            }
            if (! Schema::hasColumn('support_tickets', 'escalated_to_role')) {
                $table->string('escalated_to_role', 64)->nullable()->after('assigned_to');
            }
            if (! Schema::hasColumn('support_tickets', 'resolution_type')) {
                $table->string('resolution_type', 64)->nullable()->after('resolution_notes');
            }
        });

        if (! Schema::hasTable('support_ticket_ratings')) {
            Schema::create('support_ticket_ratings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->unique()->constrained('support_tickets')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->string('comment')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_ratings');
    }
};
