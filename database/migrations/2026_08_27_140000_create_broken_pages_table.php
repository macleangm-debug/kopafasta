<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broken_pages', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->string('url', 500)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('method', 10)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('exception', 191)->nullable();
            $table->string('message', 1000)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_role', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('locale', 10)->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['resolved_at', 'status']);
            $table->index(['fingerprint', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broken_pages');
    }
};
