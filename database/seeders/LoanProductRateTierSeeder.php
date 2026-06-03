<?php

namespace Database\Seeders;

use App\Models\ChargesFee;
use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;
use App\Services\LoanRateTierTemplateService;
use Illuminate\Database\Seeder;

class LoanProductRateTierSeeder extends Seeder
{
    /**
     * Seeds amount-band tiers for every loan product.
     * Smallest band uses loan_products.interest_rate as the maximum monthly rate.
     *
     * Set LOAN_RATE_TIER_SEED_REPLACE=true to rebuild tiers for all products.
     */
    public function run(): void
    {
        $service = app(LoanRateTierTemplateService::class);
        $defaultAppFee = (int) (ChargesFee::query()->where('code', 'APP_FEE')->value('amount') ?? 5000);
        $forceReplace = filter_var(env('LOAN_RATE_TIER_SEED_REPLACE', false), FILTER_VALIDATE_BOOL);

        LoanProduct::query()->each(function (LoanProduct $product) use ($service, $defaultAppFee, $forceReplace) {
            $updates = [];

            if (! $product->application_fee_amount) {
                $updates['application_fee_amount'] = $defaultAppFee;
            }

            if ($updates !== []) {
                $product->update($updates);
            }

            $replace = $forceReplace || $this->tiersNeedRefresh($product);

            if ($replace) {
                $service->applyDefaults($product, replaceExisting: true);
            } else {
                $service->applyDefaults($product, replaceExisting: false);
            }
        });
    }

    protected function tiersNeedRefresh(LoanProduct $product): bool
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, LoanProductRateTier> $tiers */
        $tiers = $product->rateTiers()->orderBy('sort_order')->get();

        if ($tiers->isEmpty()) {
            return true;
        }

        $first = $tiers->first();
        $last = $tiers->last();

        if ((int) $first->min_amount !== (int) $product->min_amount) {
            return true;
        }

        if ((int) $last->max_amount !== (int) $product->max_amount) {
            return true;
        }

        $maxRate = (float) ($product->interest_rate ?? 0);

        return $maxRate > 0
            && abs((float) $first->monthly_rate - $maxRate) > 0.002;
    }
}
