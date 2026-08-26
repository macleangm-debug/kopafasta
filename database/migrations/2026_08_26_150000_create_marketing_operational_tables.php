<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_audiences')) {
            Schema::create('marketing_audiences', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->json('filters')->nullable();
                $table->unsignedInteger('estimated_count')->default(0);
                $table->timestamp('estimated_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketing_personas')) {
            Schema::create('marketing_personas', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 80)->nullable()->unique();
                $table->string('name');
                $table->string('role', 20)->default('borrower');
                $table->text('summary')->nullable();
                $table->json('traits')->nullable();
                $table->json('defaults')->nullable();
                $table->boolean('restricted')->default(false);
                $table->boolean('is_system')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketing_demo_sessions')) {
            Schema::create('marketing_demo_sessions', function (Blueprint $table): void {
                $table->id();
                $table->string('token', 64)->unique();
                $table->string('status', 20)->default('active');
                $table->string('who', 20);
                $table->string('persona_key', 80)->nullable();
                $table->string('scenario_key', 80)->nullable();
                $table->string('display_name');
                $table->json('payload')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('ended_reason', 40)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketing_demo_events')) {
            Schema::create('marketing_demo_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('marketing_demo_session_id')->constrained('marketing_demo_sessions')->cascadeOnDelete();
                $table->string('event', 40);
                $table->json('meta')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_demo_events');
        Schema::dropIfExists('marketing_demo_sessions');
        Schema::dropIfExists('marketing_personas');
        Schema::dropIfExists('marketing_audiences');
    }
};
