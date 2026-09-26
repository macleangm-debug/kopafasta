<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_payout_requests')) {
            return;
        }

        Schema::table('partner_payout_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_payout_requests', 'request_number')) {
                $table->string('request_number', 24)->nullable()->after('id');
            }
            if (! Schema::hasColumn('partner_payout_requests', 'payment_reference')) {
                $table->string('payment_reference', 32)->nullable();
            }
            if (! Schema::hasColumn('partner_payout_requests', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (! Schema::hasColumn('partner_payout_requests', 'payout_account_label')) {
                $table->string('payout_account_label', 160)->nullable();
            }
        });

        $rows = DB::table('partner_payout_requests')->whereNull('request_number')->get(['id']);
        foreach ($rows as $row) {
            DB::table('partner_payout_requests')
                ->where('id', $row->id)
                ->update(['request_number' => 'WDR-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_payout_requests')) {
            return;
        }

        Schema::table('partner_payout_requests', function (Blueprint $table): void {
            foreach (['request_number', 'payment_reference', 'paid_at', 'payout_account_label'] as $column) {
                if (Schema::hasColumn('partner_payout_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
