<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'grade')) {
                $table->string('grade', 20)->default('bronze')->index();
            }
            if (! Schema::hasColumn('customers', 'calculated_grade')) {
                $table->string('calculated_grade', 20)->nullable();
            }
            if (! Schema::hasColumn('customers', 'grade_score')) {
                $table->unsignedTinyInteger('grade_score')->default(0);
            }
            if (! Schema::hasColumn('customers', 'grade_status')) {
                $table->string('grade_status', 30)->default('ok')->index();
            }
            if (! Schema::hasColumn('customers', 'grade_integrity')) {
                $table->string('grade_integrity', 20)->default('normal')->index();
            }
            if (! Schema::hasColumn('customers', 'grade_review_until')) {
                $table->timestamp('grade_review_until')->nullable();
            }
            if (! Schema::hasColumn('customers', 'grade_next_review_at')) {
                $table->timestamp('grade_next_review_at')->nullable();
            }
            if (! Schema::hasColumn('customers', 'grade_rule_version')) {
                $table->unsignedInteger('grade_rule_version')->nullable();
            }
            if (! Schema::hasColumn('customers', 'grade_override')) {
                $table->string('grade_override', 20)->nullable();
            }
            if (! Schema::hasColumn('customers', 'grade_override_reason')) {
                $table->text('grade_override_reason')->nullable();
            }
            if (! Schema::hasColumn('customers', 'grade_override_by')) {
                $table->foreignId('grade_override_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'grade_override_expires_at')) {
                $table->timestamp('grade_override_expires_at')->nullable();
            }
        });

        Schema::create('grade_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version');
            $table->json('rules');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique('version');
        });

        Schema::create('customer_grade_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rule_version')->nullable();
            $table->string('trigger', 60)->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('component_scores')->nullable();
            $table->string('calculated_grade', 20);
            $table->string('effective_grade', 20);
            $table->string('previous_grade', 20)->nullable();
            $table->string('grade_status', 30)->default('ok');
            $table->string('integrity_status', 20)->default('normal');
            $table->json('facts');
            $table->json('gates_passed')->nullable();
            $table->json('gates_failed')->nullable();
            $table->json('integrity_signals')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('customer_grade_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('from_grade', 20)->nullable();
            $table->string('to_grade', 20);
            $table->string('event', 40);
            $table->unsignedInteger('rule_version')->nullable();
            $table->text('reason')->nullable();
            $table->json('facts')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plus_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 40)->default('monthly');
            $table->string('status', 20)->index();
            $table->string('country_code', 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('price_paid', 14, 2)->default(0);
            $table->boolean('complimentary')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('entitlements')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::create('plus_lessons', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->index();
            $table->string('title_en');
            $table->string('title_sw')->nullable();
            $table->text('intro_en')->nullable();
            $table->text('intro_sw')->nullable();
            $table->string('action_en')->nullable();
            $table->string('action_sw')->nullable();
            $table->unsignedTinyInteger('duration_minutes')->default(7);
            $table->string('video_en_path')->nullable();
            $table->string('video_sw_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('audience', 40)->default('plus_members');
            $table->json('channels')->nullable();
            $table->boolean('notified')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plus_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plus_lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('action_done_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'plus_lesson_id']);
        });

        Schema::create('plus_money_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->decimal('inflow', 14, 2)->default(0);
            $table->decimal('outflow', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('plus_business_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->decimal('sold', 14, 2)->default(0);
            $table->decimal('spent', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('plus_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('title');
            $table->decimal('target_amount', 14, 2)->default(0);
            $table->decimal('saved_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('plus_offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('tier', 20)->default('standard');
            $table->boolean('plus_only')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasTable('loan_products') && ! Schema::hasColumn('loan_products', 'eligible_grades')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->json('eligible_grades')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plus_offers');
        Schema::dropIfExists('plus_goals');
        Schema::dropIfExists('plus_business_entries');
        Schema::dropIfExists('plus_money_entries');
        Schema::dropIfExists('plus_lesson_progress');
        Schema::dropIfExists('plus_lessons');
        Schema::dropIfExists('plus_subscriptions');
        Schema::dropIfExists('customer_grade_histories');
        Schema::dropIfExists('customer_grade_evaluations');
        Schema::dropIfExists('grade_rule_versions');
    }
};
