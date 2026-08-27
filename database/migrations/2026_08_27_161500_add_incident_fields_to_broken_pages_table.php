<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('broken_pages')) {
            return;
        }

        Schema::table('broken_pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('broken_pages', 'fingerprint')) {
                $table->string('fingerprint', 64)->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('broken_pages', 'referrer')) {
                $table->string('referrer', 500)->nullable();
            }
            if (! Schema::hasColumn('broken_pages', 'user_role')) {
                $table->string('user_role', 40)->nullable();
            }
            if (! Schema::hasColumn('broken_pages', 'occurrence_count')) {
                $table->unsignedInteger('occurrence_count')->default(1);
            }
            if (! Schema::hasColumn('broken_pages', 'first_seen_at')) {
                $table->timestamp('first_seen_at')->nullable();
            }
            if (! Schema::hasColumn('broken_pages', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('broken_pages')) {
            return;
        }

        Schema::table('broken_pages', function (Blueprint $table): void {
            foreach (['fingerprint', 'referrer', 'user_role', 'occurrence_count', 'first_seen_at', 'last_seen_at'] as $column) {
                if (Schema::hasColumn('broken_pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
