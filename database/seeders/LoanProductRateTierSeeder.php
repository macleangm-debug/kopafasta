<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;
use Illuminate\Database\Seeder;

class LoanProductRateTierSeeder extends Seeder
{
    public function run(): void
    {
        $tierSets = [
            'IL' => [
                ['min_amount' => 100000, 'max_amount' => 999999, 'monthly_rate' => 0.0950, 'sort_order' => 1],
                ['min_amount' => 1000000, 'max_amount' => 4999999, 'monthly_rate' => 0.0850, 'sort_order' => 2],
                ['min_amount' => 5000000, 'max_amount' => 50000000, 'monthly_rate' => 0.0750, 'sort_order' => 3],
            ],
            'EM' => [
                ['min_amount' => 50000, 'max_amount' => 499999, 'monthly_rate' => 0.1000, 'sort_order' => 1],
                ['min_amount' => 500000, 'max_amount' => 3000000, 'monthly_rate' => 0.0900, 'sort_order' => 2],
            ],
            'BP' => [
                ['min_amount' => 500000, 'max_amount' => 4999999, 'monthly_rate' => 0.0920, 'sort_order' => 1],
                ['min_amount' => 5000000, 'max_amount' => 50000000, 'monthly_rate' => 0.0820, 'sort_order' => 2],
            ],
        ];

        foreach ($tierSets as $code => $tiers) {
            $product = LoanProduct::where('code', $code)->first();
            if (! $product) {
                continue;
            }

            LoanProductRateTier::query()->where('loan_product_id', $product->id)->delete();

            foreach ($tiers as $tier) {
                LoanProductRateTier::create(array_merge($tier, ['loan_product_id' => $product->id]));
            }
        }
    }
}
