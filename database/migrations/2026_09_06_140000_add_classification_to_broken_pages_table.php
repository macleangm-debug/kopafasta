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
            if (! Schema::hasColumn('broken_pages', 'category')) {
                $table->string('category', 40)->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('broken_pages', 'classification_notes')) {
                $table->string('classification_notes', 500)->nullable()->after('category');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('broken_pages')) {
            return;
        }

        Schema::table('broken_pages', function (Blueprint $table): void {
            foreach (['classification_notes', 'category'] as $column) {
                if (Schema::hasColumn('broken_pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
