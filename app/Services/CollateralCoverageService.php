<?php

namespace App\Services;

use App\Models\CustomerAsset;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;

/**
 * After a valuer enters forced-sale value: compare LTV cap to the requested amount,
 * then (only if the amount is covered) check insurance. Distinct from AB origination,
 * where the offer is capped at LTV from the start.
 */
class CollateralCoverageService
{
    public const NEXT_OK = 'ok';

    public const NEXT_INSURANCE = 'insurance_update';

    public const NEXT_ADD_COLLATERAL = 'add_collateral';

    public const NEXT_ASK_GUARANTOR = 'ask_guarantor';

    public const NEXT_REDUCE_AMOUNT = 'reduce_amount';

    public function ltvPercentFor(string $assetType): float
    {
        $mapped = match ($assetType) {
            'vehicle' => 'saloon_car',
            'house', 'land' => 'property',
            'equipment' => 'heavy_machinery',
            default => $assetType,
        };

        return (float) (config("repossession_charges.ltv_percent.{$mapped}")
            ?? config('repossession_charges.ltv_percent.default', 60));
    }

    public function maxLoanFromForcedSale(float $forcedSaleValue, string $assetType): float
    {
        $percent = $this->ltvPercentFor($assetType);

        return round($forcedSaleValue * ($percent / 100), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(LoanApplication $application): array
    {
        $application->loadMissing(['collateralAssets.customerAsset', 'product']);
        $keepIds = app(CustomerAssetService::class)->onLoanAssetIds($application);
        $rows = $application->collateralAssets
            ->filter(function (LoanApplicationAsset $asset) use ($keepIds) {
                if (($asset->uw_status ?? '') === LoanApplicationAsset::UW_DECLINED) {
                    return false;
                }
                if (! filled($asset->forced_sale_value)) {
                    return false;
                }
                $cid = (int) ($asset->customer_asset_id ?? 0);

                return $keepIds === [] ? false : in_array($cid, $keepIds, true);
            });

        $forcedSale = (float) $rows->sum(fn (LoanApplicationAsset $asset) => (float) $asset->forced_sale_value);
        $maxLoan = (float) $rows->sum(function (LoanApplicationAsset $asset) {
            $type = (string) ($asset->asset_type ?: $asset->customerAsset?->asset_type ?: 'default');
            $ownMax = (float) ($asset->max_loan_amount ?? 0);
            if ($ownMax > 0) {
                return $ownMax;
            }

            return $this->maxLoanFromForcedSale((float) ($asset->forced_sale_value ?? 0), $type);
        });
        $first = $rows->first()
            ?? $application->collateralAssets->first(fn (LoanApplicationAsset $asset) => ($asset->uw_status ?? '') !== LoanApplicationAsset::UW_DECLINED);
        $assetType = (string) ($first?->asset_type ?: $first?->customerAsset?->asset_type ?: 'default');
        $ltvPercent = $first && (float) $first->ltv_percent > 0
            ? (float) $first->ltv_percent
            : $this->ltvPercentFor($assetType);
        $requested = (float) ($application->requested_amount ?? 0);
        $sufficient = $forcedSale > 0 && $maxLoan + 0.009 >= $requested;
        $shortfall = $sufficient ? 0.0 : max(0, round($requested - $maxLoan, 2));
        $state = app(CollateralSecureService::class)->state($application);
        $alreadyGuarantor = ($state['source'] ?? '') === 'guarantor';

        $insurance = ['ok' => true, 'reason' => null];
        $next = self::NEXT_OK;
        if ($sufficient) {
            foreach ($rows as $pledge) {
                $asset = $pledge->customerAsset;
                if ($asset instanceof CustomerAsset) {
                    $insurance = app(CollateralSecureService::class)->insuranceCheck($application, $asset);
                    if (! ($insurance['ok'] ?? true)) {
                        $next = self::NEXT_INSURANCE;
                        break;
                    }
                }
            }
        } else {
            $next = $alreadyGuarantor ? self::NEXT_REDUCE_AMOUNT : self::NEXT_ADD_COLLATERAL;
        }

        $coverage = [
            'evaluated_at' => now()->toIso8601String(),
            'asset_type' => $assetType,
            'forced_sale_value' => $forcedSale,
            'ltv_percent' => $ltvPercent,
            'max_loan_amount' => $maxLoan,
            'requested_amount' => $requested,
            'sufficient' => $sufficient,
            'shortfall' => $shortfall,
            'insurance' => $insurance,
            'next' => $next,
            'scenarios' => $this->scenarios($sufficient, $alreadyGuarantor ?? false, $maxLoan, $shortfall),
        ];

        $payload = $application->screening_payload ?? [];
        $payload['collateral_coverage'] = $coverage;
        $application->update(['screening_payload' => $payload]);

        app(CollateralSecureService::class)->applyCoverageOutcome($application, $coverage);

        return $coverage;
    }

    /** @return array<string, mixed>|null */
    public function forApplication(LoanApplication $application): ?array
    {
        $coverage = data_get($application->screening_payload, 'collateral_coverage');

        return is_array($coverage) ? $coverage : null;
    }

    /**
     * Borrower accepts the LTV cap as the requested amount, then insurance is rechecked.
     *
     * @return array<string, mixed>
     */
    public function acceptLtvCap(LoanApplication $application): array
    {
        $coverage = $this->forApplication($application) ?? $this->evaluate($application);
        $maxLoan = (float) ($coverage['max_loan_amount'] ?? 0);
        abort_unless($maxLoan > 0, 422, 'Valuation is not complete.');

        $application->update(['requested_amount' => $maxLoan]);

        return $this->evaluate($application->fresh());
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    private function scenarios(bool $sufficient, bool $alreadyGuarantor, float $maxLoan, float $shortfall): array
    {
        if ($sufficient) {
            return [];
        }

        $rows = [
            [
                'code' => self::NEXT_ADD_COLLATERAL,
                'label' => 'Ask the borrower / group leader to add another asset. They must pick it themselves.',
            ],
        ];
        if (! $alreadyGuarantor) {
            $rows[] = [
                'code' => self::NEXT_ASK_GUARANTOR,
                'label' => 'Ask the guarantor to pledge an asset that covers the shortfall.',
            ];
        }
        $rows[] = [
            'code' => self::NEXT_REDUCE_AMOUNT,
            'label' => 'Reduce the requested amount to '.format_money($maxLoan).' (shortfall '.format_money($shortfall).').',
        ];

        return $rows;
    }
}
