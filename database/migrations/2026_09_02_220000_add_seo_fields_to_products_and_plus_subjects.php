<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_products', 'seo_title')) {
                $table->string('seo_title', 120)->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'seo_title_sw')) {
                $table->string('seo_title_sw', 120)->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'seo_description')) {
                $table->string('seo_description', 320)->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'seo_description_sw')) {
                $table->string('seo_description_sw', 320)->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'seo_image_path')) {
                $table->string('seo_image_path')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'seo_indexable')) {
                $table->boolean('seo_indexable')->default(true);
            }
        });

        Schema::table('plus_subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('plus_subjects', 'seo_title')) {
                $table->string('seo_title', 120)->nullable();
            }
            if (! Schema::hasColumn('plus_subjects', 'seo_title_sw')) {
                $table->string('seo_title_sw', 120)->nullable();
            }
            if (! Schema::hasColumn('plus_subjects', 'seo_description')) {
                $table->string('seo_description', 320)->nullable();
            }
            if (! Schema::hasColumn('plus_subjects', 'seo_description_sw')) {
                $table->string('seo_description_sw', 320)->nullable();
            }
            if (! Schema::hasColumn('plus_subjects', 'seo_image_path')) {
                $table->string('seo_image_path')->nullable();
            }
            if (! Schema::hasColumn('plus_subjects', 'seo_indexable')) {
                $table->boolean('seo_indexable')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table): void {
            foreach (['seo_title', 'seo_title_sw', 'seo_description', 'seo_description_sw', 'seo_image_path', 'seo_indexable'] as $column) {
                if (Schema::hasColumn('loan_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('plus_subjects', function (Blueprint $table): void {
            foreach (['seo_title', 'seo_title_sw', 'seo_description', 'seo_description_sw', 'seo_image_path', 'seo_indexable'] as $column) {
                if (Schema::hasColumn('plus_subjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
