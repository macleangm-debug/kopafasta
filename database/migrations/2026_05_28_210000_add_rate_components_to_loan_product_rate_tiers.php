<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_product_rate_tiers', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_product_rate_tiers', 'bot_regulated_rate')) {
                $table->decimal('bot_regulated_rate', 8, 4)->nullable()->after('max_amount');
            }
            if (! Schema::hasColumn('loan_product_rate_tiers', 'processing_fee_rate')) {
                $table->decimal('processing_fee_rate', 8, 4)->default(0)->after('bot_regulated_rate');
            }
            if (! Schema::hasColumn('loan_product_rate_tiers', 'service_fee_rate')) {
                $table->decimal('service_fee_rate', 8, 4)->default(0)->after('processing_fee_rate');
            }
            if (! Schema::hasColumn('loan_product_rate_tiers', 'administration_fee_rate')) {
                $table->decimal('administration_fee_rate', 8, 4)->default(0)->after('service_fee_rate');
            }
        });

        if (! Schema::hasColumn('loan_product_rate_tiers', 'bot_regulated_rate')) {
            return;
        }

        DB::table('loan_product_rate_tiers')->orderBy('id')->each(function (object $tier): void {
            if ($tier->bot_regulated_rate !== null && (float) $tier->bot_regulated_rate > 0) {
                return;
            }

            $total = (float) $tier->monthly_rate;
            $bot = min(0.035, $total);
            $processing = min(0.05, max(0, $total - $bot) * 0.45);
            $risk = min(0.035, max(0, $total - $bot - $processing) * 0.9);
            $insurance = max(0, round($total - $bot - $processing - $risk, 4));

            DB::table('loan_product_rate_tiers')->where('id', $tier->id)->update([
                'bot_regulated_rate'      => $bot,
                'processing_fee_rate'     => $processing,
                'service_fee_rate'        => $risk,
                'administration_fee_rate' => $insurance,
                'monthly_rate'            => round($bot + $processing + $risk + $insurance, 4),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('loan_product_rate_tiers', function (Blueprint $table): void {
            foreach (['bot_regulated_rate', 'processing_fee_rate', 'service_fee_rate', 'administration_fee_rate'] as $column) {
                if (Schema::hasColumn('loan_product_rate_tiers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
