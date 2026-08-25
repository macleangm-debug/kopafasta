<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plus_money_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('plus_money_entries', 'category')) {
                $table->string('category', 40)->nullable()->after('outflow');
            }
            if (! Schema::hasColumn('plus_money_entries', 'note')) {
                $table->string('note', 160)->nullable()->after('category');
            }
        });

        Schema::table('plus_business_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('plus_business_entries', 'category')) {
                $table->string('category', 40)->nullable()->after('spent');
            }
            if (! Schema::hasColumn('plus_business_entries', 'note')) {
                $table->string('note', 160)->nullable()->after('category');
            }
        });

        Schema::table('plus_goals', function (Blueprint $table) {
            if (! Schema::hasColumn('plus_goals', 'target_date')) {
                $table->date('target_date')->nullable()->after('saved_amount');
            }
            if (! Schema::hasColumn('plus_goals', 'status')) {
                $table->string('status', 20)->default('active')->after('target_date');
            }
        });

        if (! Schema::hasTable('plus_offer_events')) {
            Schema::create('plus_offer_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('plus_offer_id')->constrained('plus_offers')->cascadeOnDelete();
                $table->string('event', 20);
                $table->timestamps();
                $table->index(['customer_id', 'plus_offer_id', 'event']);
            });
        }

        if (! Schema::hasTable('plus_subject_categories')) {
            Schema::create('plus_subject_categories', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 40)->unique();
                $table->string('title_en');
                $table->string('title_sw');
                $table->unsignedSmallInteger('sort')->default(0);
                $table->string('status', 20)->default('published');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plus_subjects')) {
            Schema::create('plus_subjects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plus_subject_category_id')->constrained()->cascadeOnDelete();
                $table->string('slug')->unique();
                $table->string('title_en');
                $table->string('title_sw');
                $table->text('intro_en')->nullable();
                $table->text('intro_sw')->nullable();
                $table->text('body_en')->nullable();
                $table->text('body_sw')->nullable();
                $table->unsignedTinyInteger('duration_minutes')->default(4);
                $table->string('content_type', 20)->default('article');
                $table->string('action_en')->nullable();
                $table->string('action_sw')->nullable();
                $table->string('action_route')->nullable();
                $table->string('icon', 8)->nullable();
                $table->boolean('featured')->default(false);
                $table->string('status', 20)->default('draft')->index();
                $table->string('country_code', 2)->nullable();
                $table->json('eligible_grades')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['plus_subject_category_id', 'status']);
            });
        }

        if (! Schema::hasTable('plus_subject_progress')) {
            Schema::create('plus_subject_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('plus_subject_id')->constrained()->cascadeOnDelete();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('saved_at')->nullable();
                $table->timestamp('action_clicked_at')->nullable();
                $table->unsignedTinyInteger('last_position')->nullable();
                $table->boolean('helpful')->nullable();
                $table->string('locale', 8)->nullable();
                $table->timestamps();
                $table->unique(['customer_id', 'plus_subject_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plus_subject_progress');
        Schema::dropIfExists('plus_subjects');
        Schema::dropIfExists('plus_subject_categories');
        Schema::dropIfExists('plus_offer_events');
    }
};
