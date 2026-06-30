<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partners')) {
            Schema::table('partners', function (Blueprint $table): void {
                if (! Schema::hasColumn('partners', 'affiliate_lifecycle_status')) {
                    $table->string('affiliate_lifecycle_status', 30)->nullable()->after('affiliate_kyc_status');
                }
                if (! Schema::hasColumn('partners', 'affiliate_evaluation_snapshot')) {
                    $table->json('affiliate_evaluation_snapshot')->nullable()->after('affiliate_lifecycle_status');
                }
                if (! Schema::hasColumn('partners', 'affiliate_leaderboard_rank')) {
                    $table->unsignedInteger('affiliate_leaderboard_rank')->nullable()->after('affiliate_evaluation_snapshot');
                }
                if (! Schema::hasColumn('partners', 'affiliate_lifecycle_note')) {
                    $table->string('affiliate_lifecycle_note', 500)->nullable()->after('affiliate_leaderboard_rank');
                }
            });
        }

        if (! Schema::hasTable('affiliate_evaluations')) {
            Schema::create('affiliate_evaluations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('kpi_score', 5, 2)->default(0);
                $table->decimal('risk_score', 5, 2)->default(0);
                $table->decimal('fraud_score', 5, 2)->default(0);
                $table->string('recommendation', 30)->default('none');
                $table->string('action_taken', 30)->nullable();
                $table->json('metrics')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('evaluated_at');
                $table->timestamps();

                $table->index(['partner_id', 'period_end']);
            });
        }

        if (Schema::hasTable('partners') && Schema::hasColumn('partners', 'affiliate_lifecycle_status')) {
            DB::table('partners')
                ->where('category', 'affiliate')
                ->whereNull('affiliate_lifecycle_status')
                ->update([
                    'affiliate_lifecycle_status' => DB::raw("CASE WHEN affiliate_kyc_status IN ('verified', 'approved') THEN 'active' ELSE 'pending_kyc' END"),
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_evaluations');

        if (Schema::hasTable('partners')) {
            Schema::table('partners', function (Blueprint $table): void {
                foreach ([
                    'affiliate_lifecycle_status',
                    'affiliate_evaluation_snapshot',
                    'affiliate_leaderboard_rank',
                    'affiliate_lifecycle_note',
                ] as $column) {
                    if (Schema::hasColumn('partners', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
