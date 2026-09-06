<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lending_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40);
            $table->string('status', 24)->default('draft'); // draft|approved|superseded
            $table->string('title');
            $table->string('jurisdiction')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->string('settings_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('supersedes_id')->nullable();
            $table->json('snapshot');
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_at']);
            $table->foreign('supersedes_id')->references('id')->on('lending_policy_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lending_policy_versions');
    }
};
