<?php

namespace App\Services;

use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;
use App\Support\RatePercent;
use Illuminate\Support\Collection;

class LoanRateTierRepairService
{
    /** @return array{tiers_fixed: int, products_updated: int, skipped: int} */
    public function repairAll(bool $dryRun = false): array
    {
        $tiersFixed = 0;
        $productsUpdated = 0;
        $skipped = 0;

        LoanProductRateTier::query()->orderBy('id')->chunkById(100, function ($tiers) use ($dryRun, &$tiersFixed, &$skipped): void {
            foreach ($tiers as $tier) {
                if ($this->repairTier($tier, $dryRun)) {
                    $tiersFixed++;
                } else {
                    $skipped++;
                }
            }
        });

        LoanProduct::query()->where('interest_rate', '>', 1)->orderBy('id')->chunkById(50, function ($products) use ($dryRun, &$productsUpdated): void {
            foreach ($products as $product) {
                $normalized = RatePercent::toDecimal((float) $product->interest_rate);
                if ($normalized <= 0 || $normalized > 1) {
                    continue;
                }

                if (! $dryRun) {
                    $product->update(['interest_rate' => $normalized]);
                }
                $productsUpdated++;
            }
        });

        $productIds = LoanProductRateTier::query()
            ->select('loan_product_id')
            ->distinct()
            ->pluck('loan_product_id');

        foreach ($productIds as $productId) {
            $product = LoanProduct::find($productId);
            if (! $product) {
                continue;
            }

            $range = app(DisplayedRateService::class)->borrowerRateRange($product);
            $fallback = max($range['min'], $range['max']);
            if ($fallback > 0 && $fallback <= 1 && abs((float) $product->interest_rate - $fallback) > 0.0001) {
                if (! $dryRun) {
                    $product->update(['interest_rate' => $fallback]);
                }
            }
        }

        return [
            'tiers_fixed'       => $tiersFixed,
            'products_updated'  => $productsUpdated,
            'skipped'           => $skipped,
        ];
    }

    public function repairTier(LoanProductRateTier $tier, bool $dryRun = false): bool
    {
        if (! $this->tierNeedsRepair($tier)) {
            return false;
        }

        $bot = min(RatePercent::toDecimal($tier->bot_regulated_rate), LoanProductRateTier::BOT_MAX);
        $processing = RatePercent::toDecimal($tier->processing_fee_rate);
        $risk = RatePercent::toDecimal($tier->service_fee_rate);
        $insurance = RatePercent::toDecimal($tier->administration_fee_rate);
        $monthly = LoanProductRateTier::totalFromComponents($bot, $processing, $risk, $insurance);

        if ($monthly > 1 && (float) $tier->monthly_rate > 1) {
            $monthly = RatePercent::toDecimal($tier->monthly_rate);
        }

        if ($monthly <= 0 || $monthly > 1) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $tier->update([
            'bot_regulated_rate'      => $bot,
            'processing_fee_rate'     => $processing,
            'service_fee_rate'        => $risk,
            'administration_fee_rate' => $insurance,
            'monthly_rate'            => $monthly,
        ]);

        return true;
    }

    public function tierNeedsRepair(LoanProductRateTier $tier): bool
    {
        if ((float) $tier->monthly_rate > 1) {
            return true;
        }

        foreach (['bot_regulated_rate', 'processing_fee_rate', 'service_fee_rate', 'administration_fee_rate'] as $field) {
            if ((float) ($tier->{$field} ?? 0) > 1) {
                return true;
            }
        }

        $expected = LoanProductRateTier::totalFromComponents(
            min(RatePercent::toDecimal($tier->bot_regulated_rate), LoanProductRateTier::BOT_MAX),
            RatePercent::toDecimal($tier->processing_fee_rate),
            RatePercent::toDecimal($tier->service_fee_rate),
            RatePercent::toDecimal($tier->administration_fee_rate),
        );

        return abs($expected - (float) $tier->monthly_rate) > 0.02;
    }

    /** @return Collection<int, LoanProductRateTier> */
    public function corruptTiers(): Collection
    {
        return LoanProductRateTier::query()->get()->filter(fn (LoanProductRateTier $tier) => $this->tierNeedsRepair($tier));
    }
}
