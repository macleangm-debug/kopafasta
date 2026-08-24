<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plus_goals', function (Blueprint $table) {
            if (! Schema::hasColumn('plus_goals', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });

        Schema::table('plus_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('plus_offers', 'country_code')) {
                $table->string('country_code', 2)->nullable();
            }
            if (! Schema::hasColumn('plus_offers', 'eligible_grades')) {
                $table->json('eligible_grades')->nullable();
            }
            if (! Schema::hasColumn('plus_offers', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
            if (! Schema::hasColumn('plus_offers', 'ends_at')) {
                $table->timestamp('ends_at')->nullable();
            }
        });

        if (! Schema::hasTable('plus_reward_ledgers')) {
            Schema::create('plus_reward_ledgers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 40);
                $table->integer('points')->default(0);
                $table->string('reason');
                $table->string('source', 40)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('grade_watch_actions')) {
            Schema::create('grade_watch_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->string('action', 40);
                $table->text('reason');
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_watch_actions');
        Schema::dropIfExists('plus_reward_ledgers');
    }
};
