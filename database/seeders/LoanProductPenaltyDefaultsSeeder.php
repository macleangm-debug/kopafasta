<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Services\LoanPenaltyPolicy;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class LoanProductPenaltyDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGlobalLoanRules();

        LoanProduct::query()->each(function (LoanProduct $product): void {
            $defaults = LoanPenaltyPolicy::defaultsForProduct($product);

            $product->update([
                'default_grace_days' => $defaults['default_grace_days'],
                'penalty_rate_percent' => $defaults['penalty_rate_percent'],
                'penalty_basis' => $defaults['penalty_basis'],
            ]);
        });
    }

    protected function seedGlobalLoanRules(): void
    {
        $existing = Setting::group('loan');

        Setting::setMany([
            'loan.default_grace_days' => $existing['default_grace_days'] ?? 7,
            'loan.default_penalty_rate' => $existing['default_penalty_rate'] ?? LoanPenaltyPolicy::DEFAULT_PENALTY_RATE_PERCENT_PER_DAY,
            'loan.penalty_basis' => $existing['penalty_basis'] ?? 'per_day',
            'loan.penalty_cap_percent' => $existing['penalty_cap_percent'] ?? LoanPenaltyPolicy::BOT_MAX_PENALTY_CAP_PERCENT,
        ]);
    }
}
