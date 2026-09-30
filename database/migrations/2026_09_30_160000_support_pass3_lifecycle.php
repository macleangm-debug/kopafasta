<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_conversations', 'resolution_category')) {
                $table->string('resolution_category', 64)->nullable()->after('topic');
            }
            if (! Schema::hasColumn('support_conversations', 'resolution_note')) {
                $table->text('resolution_note')->nullable()->after('resolution_category');
            }
            if (! Schema::hasColumn('support_conversations', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable()->after('resolution_note');
            }
            if (! Schema::hasColumn('support_conversations', 'rated_at')) {
                $table->timestamp('rated_at')->nullable()->after('rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            foreach (['resolution_category', 'resolution_note', 'rating', 'rated_at'] as $col) {
                if (Schema::hasColumn('support_conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
