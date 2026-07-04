<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_payout_requests')) {
            if (! Schema::hasColumn('partner_payout_requests', 'source_type')
                && Schema::hasColumn('partner_payout_requests', 'wallet_type')) {
                Schema::table('partner_payout_requests', function (Blueprint $table): void {
                    $table->string('source_type', 64)->nullable()->after('partner_id');
                });

                DB::table('partner_payout_requests')->update([
                    'source_type' => DB::raw('wallet_type'),
                ]);
            }

            if (! Schema::hasColumn('partner_payout_requests', 'source_type')) {
                Schema::table('partner_payout_requests', function (Blueprint $table): void {
                    $table->string('source_type', 64)->nullable()->after('partner_id');
                });
            }

            if (! Schema::hasColumn('partner_payout_requests', 'reviewed_by')
                && Schema::hasColumn('partner_payout_requests', 'reviewed_by_user_id')) {
                Schema::table('partner_payout_requests', function (Blueprint $table): void {
                    $table->unsignedBigInteger('reviewed_by')->nullable()->after('notes');
                });

                DB::table('partner_payout_requests')->update([
                    'reviewed_by' => DB::raw('reviewed_by_user_id'),
                ]);
            }

            if (! Schema::hasColumn('partner_payout_requests', 'admin_notes')
                && Schema::hasColumn('partner_payout_requests', 'review_notes')) {
                Schema::table('partner_payout_requests', function (Blueprint $table): void {
                    $table->text('admin_notes')->nullable()->after('notes');
                });

                DB::table('partner_payout_requests')->update([
                    'admin_notes' => DB::raw('review_notes'),
                ]);
            }

            if (! Schema::hasColumn('partner_payout_requests', 'admin_notes')) {
                Schema::table('partner_payout_requests', function (Blueprint $table): void {
                    $table->text('admin_notes')->nullable()->after('notes');
                });
            }
        }

        if (Schema::hasTable('partner_payments')) {
            Schema::table('partner_payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('partner_payments', 'dispute_reason')) {
                    $table->text('dispute_reason')->nullable()->after('status');
                }
                if (! Schema::hasColumn('partner_payments', 'disputed_at')) {
                    $table->timestamp('disputed_at')->nullable()->after('dispute_reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('partner_payments')) {
            Schema::table('partner_payments', function (Blueprint $table): void {
                foreach (['dispute_reason', 'disputed_at'] as $column) {
                    if (Schema::hasColumn('partner_payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
