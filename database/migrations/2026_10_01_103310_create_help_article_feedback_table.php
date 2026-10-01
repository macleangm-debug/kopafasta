<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_article_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_key', 80);
            $table->string('article_slug', 120);
            $table->string('vote', 8);
            $table->string('locale', 8)->nullable();
            $table->timestamps();

            $table->index(['category_key', 'article_slug']);
            $table->index(['vote', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_article_feedback');
    }
};
