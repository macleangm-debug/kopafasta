<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('support_conversations', 'handling_state')) {
                $table->string('handling_state', 40)->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('support_conversations', 'automation_meta')) {
                $table->json('automation_meta')->nullable()->after('handling_state');
            }
            if (! Schema::hasColumn('support_conversations', 'resolution_kind')) {
                $table->string('resolution_kind', 40)->nullable()->after('automation_meta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('support_conversations', 'resolution_kind')) {
                $table->dropColumn('resolution_kind');
            }
            if (Schema::hasColumn('support_conversations', 'automation_meta')) {
                $table->dropColumn('automation_meta');
            }
            if (Schema::hasColumn('support_conversations', 'handling_state')) {
                $table->dropColumn('handling_state');
            }
        });
    }
};
