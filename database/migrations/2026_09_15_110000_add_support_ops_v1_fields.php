<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('guest_name')->nullable()->after('customer_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->string('source')->default('admin')->after('guest_phone');
            $table->string('contact_kind')->nullable()->after('source'); // customer|guest
        });

        Schema::create('support_ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event'); // created, assigned, opened, response, internal_note, reassigned, status_changed, escalated, resolved, reopened
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_events');

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'guest_name',
                'guest_email',
                'guest_phone',
                'source',
                'contact_kind',
            ]);
        });
    }
};
