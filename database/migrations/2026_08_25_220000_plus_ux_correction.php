<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plus_money_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('plus_money_entries', 'other_label')) {
                $table->string('other_label', 80)->nullable()->after('category');
            }
        });

        Schema::table('plus_business_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('plus_business_entries', 'other_label')) {
                $table->string('other_label', 80)->nullable()->after('category');
            }
        });

        if (! Schema::hasTable('plus_goal_contributions')) {
            Schema::create('plus_goal_contributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plus_goal_id')->constrained('plus_goals')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();
                $table->index(['plus_goal_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('plus_monthly_reports')) {
            Schema::create('plus_monthly_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->date('period_month');
                $table->json('payload');
                $table->unsignedSmallInteger('version')->default(1);
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamps();
                $table->unique(['customer_id', 'period_month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plus_monthly_reports');
        Schema::dropIfExists('plus_goal_contributions');
        Schema::table('plus_money_entries', function (Blueprint $table) {
            if (Schema::hasColumn('plus_money_entries', 'other_label')) {
                $table->dropColumn('other_label');
            }
        });
        Schema::table('plus_business_entries', function (Blueprint $table) {
            if (Schema::hasColumn('plus_business_entries', 'other_label')) {
                $table->dropColumn('other_label');
            }
        });
    }
};
