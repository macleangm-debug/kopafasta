<?php

namespace App\Services;

use App\Models\CustomerAsset;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\ValuationAssignment;
use Illuminate\Support\Collection;

/**
 * Canonical collateral-card payload for every surface.
 * FSV / LTV / coverage / insurance / security come from existing services — never recomputed here.
 */
class CollateralCardService
{
    public const VIEWER_BORROWER = 'borrower';

    public const VIEWER_SCREENING = 'screening';

    public const VIEWER_COMMITTEE = 'committee';

    public const VIEWER_MANAGEMENT = 'management';

    public const VIEWER_VALUER = 'valuer';

    public const VIEWER_RECOVERY = 'recovery';

    public const PREVIEW_LIMIT = 8;

    public function __construct(
        private CollateralCoverageService $coverage,
        private CollateralSecureService $secure,
        private CustomerAssetService $assets,
    ) {}

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function forAsset(
        CustomerAsset $asset,
        ?LoanApplication $application = null,
        string $viewer = self::VIEWER_BORROWER,
        array $extra = [],
    ): array {
        $asset->loadMissing('customer');
        if ($application) {
            $application->loadMissing(['collateralAssets.customerAsset', 'valuationAssignments.vendor']);
        }
        $pledge = $this->pledgeFor($asset, $application);
        $base = $asset->toCollateralCard($extra);

        $onLoan = false;
        $secured = false;
        if ($application) {
            $onLoanIds = $this->assets->onLoanAssetIds($application);
            $onLoan = in_array((int) $asset->id, $onLoanIds, true);
            $csStatus = (string) ($this->secure->state($application)['status'] ?? '');
            $secured = $onLoan && $csStatus === CollateralSecureService::STATUS_SECURED;
        }

        $completedValuation = $application
            ? $application->valuationAssignments
                ->first(fn ($a) => $a->status === ValuationAssignment::STATUS_COMPLETED)
            : null;

        $fsv = $pledge && filled($pledge->forced_sale_value) ? (float) $pledge->forced_sale_value : null;
        $market = $pledge && filled($pledge->market_value) ? (float) $pledge->market_value : null;
        if ($fsv === null && $completedValuation && filled($completedValuation->forced_sale_value)) {
            $fsv = (float) $completedValuation->forced_sale_value;
            if ($market === null && filled($completedValuation->market_value)) {
                $market = (float) $completedValuation->market_value;
            }
        }
        $valued = $fsv !== null && $fsv > 0;

        $ltvPercent = null;
        $coverAmount = null;
        if ($valued) {
            $assetType = (string) ($pledge?->asset_type ?: $asset->asset_type ?: 'default');
            $ltvPercent = $pledge && (float) ($pledge->ltv_percent ?: 0) > 0
                ? (float) $pledge->ltv_percent
                : $this->coverage->ltvPercentFor($assetType);
            $ownMax = (float) ($pledge?->max_loan_amount ?? 0);
            $coverAmount = $ownMax > 0
                ? $ownMax
                : $this->coverage->maxLoanFromForcedSale($fsv, $assetType);
        }

        $insuranceCheck = ['ok' => true, 'reason' => null, 'expiry' => $asset->detail('insurance_expires_at')];
        if ($application) {
            $insuranceCheck = $this->secure->insuranceCheck($application, $asset);
        } else {
            $expiryRaw = $asset->detail('insurance_expires_at');
            if (filled($expiryRaw)) {
                try {
                    $expiry = \Carbon\Carbon::parse((string) $expiryRaw)->startOfDay();
                    if ($expiry->lt(now()->startOfDay())) {
                        $insuranceCheck = ['ok' => false, 'reason' => 'expired', 'expiry' => $expiry->toDateString()];
                    }
                } catch (\Throwable) {
                    $insuranceCheck = ['ok' => false, 'reason' => 'invalid', 'expiry' => null];
                }
            } elseif ($asset->isVehicleLike() && ! $asset->hasVehicleInsurance()) {
                $insuranceCheck = ['ok' => false, 'reason' => 'missing', 'expiry' => null];
            }
        }

        $insured = $asset->hasVehicleInsurance()
            || (($base['insurance_type'] ?? null) === 'comprehensive' && filled($base['insurance_expires_at'] ?? null));
        $insuranceProblem = ! ($insuranceCheck['ok'] ?? true);

        $ownerName = trim((string) ($asset->customer?->full_name ?? ''));
        $ownerRole = (string) ($extra['owner_role'] ?? '');
        $belongsTo = (string) ($base['belongs_to'] ?? '');
        if ($belongsTo === '' && ($ownerName !== '' || $ownerRole !== '')) {
            $belongsTo = trim($ownerName.($ownerRole !== '' ? ' · '.$ownerRole : ''), ' ·');
        }

        $showValuation = $valued && in_array($viewer, [
            self::VIEWER_SCREENING,
            self::VIEWER_COMMITTEE,
            self::VIEWER_MANAGEMENT,
        ], true);
        $showLtv = $showValuation && in_array($viewer, [
            self::VIEWER_SCREENING,
            self::VIEWER_COMMITTEE,
            self::VIEWER_MANAGEMENT,
        ], true);
        $showValuerName = $showValuation && $viewer !== self::VIEWER_BORROWER;

        $badges = [];
        if ($insured && ! $insuranceProblem) {
            $badges[] = ['label' => __('borrower.collateral_secure.badge_insured'), 'tone' => 'emerald'];
        }
        if ($valued) {
            $badges[] = ['label' => __('borrower.collateral_secure.badge_valued'), 'tone' => 'sky'];
        }
        if ($secured) {
            $badges[] = ['label' => __('borrower.collateral_secure.badge_secured'), 'tone' => 'emerald'];
        }
        if ($onLoan && $viewer !== self::VIEWER_BORROWER) {
            $badges[] = ['label' => 'On this loan', 'tone' => 'brand'];
        } elseif (! empty($extra['source_label']) && ! in_array((string) $extra['source_label'], ['On this loan', 'Saved'], true)) {
            $badges[] = ['label' => (string) $extra['source_label'], 'tone' => 'amber'];
        }

        $ownershipStatus = filled($asset->metadata['ownership_document_path'] ?? null)
            ? 'Ownership on file'
            : null;

        $insuranceWarning = null;
        if ($insuranceProblem) {
            $insuranceWarning = match ($insuranceCheck['reason'] ?? '') {
                'expired' => 'Insurance expired',
                'expiring_soon', 'buffer' => 'Insurance expiring',
                'invalid' => 'Insurance date is invalid',
                'missing' => 'Insurance missing',
                default => 'Insurance needs attention',
            };
        }

        return array_merge($base, [
            'belongs_to' => $belongsTo !== '' ? $belongsTo : ($base['belongs_to'] ?? null),
            'owner_name' => $ownerName !== '' ? $ownerName : null,
            'owner_role' => $ownerRole !== '' ? $ownerRole : null,
            'ownership_status' => $ownershipStatus,
            'make' => $base['make'] ?: $asset->detail('brand'),
            'model' => $asset->detail('model'),
            'serial' => $asset->detail('serial_number'),
            'insurer' => $asset->detail('insurer') ?: $asset->detail('insurance_company') ?: $asset->detail('insurance_provider'),
            'viewer' => $viewer,
            'badges' => $badges,
            'on_this_loan' => $onLoan,
            'secured' => $secured,
            'valued' => $valued,
            'insured' => $insured,
            'show' => [
                'identity' => true,
                'ownership' => true,
                'insurance' => in_array($viewer, [
                    self::VIEWER_BORROWER,
                    self::VIEWER_SCREENING,
                    self::VIEWER_COMMITTEE,
                    self::VIEWER_MANAGEMENT,
                    self::VIEWER_VALUER,
                    self::VIEWER_RECOVERY,
                ], true),
                'insurance_warning' => $insuranceProblem,
                'valuation' => $showValuation,
                'ltv' => $showLtv,
                'valuer' => $showValuerName,
            ],
            'insurance_warning' => $insuranceWarning,
            'insurance_ok' => ! $insuranceProblem,
            'valuation' => $showValuation ? [
                'market_value' => $market,
                'forced_sale_value' => $fsv,
                'ltv_percent' => $ltvPercent,
                'cover_amount' => $coverAmount,
                'valued_at' => $completedValuation?->completed_at?->timezone(config('app.timezone'))->format('d M Y')
                    ?? $pledge?->updated_at?->timezone(config('app.timezone'))->format('d M Y'),
                'valuer' => $completedValuation?->vendor?->name,
            ] : null,
        ], $extra);
    }

    /**
     * @return array{
     *     count: int,
     *     total_fsv: float,
     *     required_security: float,
     *     cover_amount: float,
     *     coverage_ratio: float|null,
     *     preview_limit: int,
     *     cards: Collection<int, array<string, mixed>>
     * }
     */
    public function portfolio(LoanApplication $application, string $viewer = self::VIEWER_SCREENING): array
    {
        $application->loadMissing(['collateralAssets.customerAsset.customer', 'valuationAssignments.vendor']);
        $onLoanIds = $this->assets->onLoanAssetIds($application);
        $pledges = $application->collateralAssets
            ->filter(function (LoanApplicationAsset $row) use ($onLoanIds) {
                if (($row->uw_status ?? '') === LoanApplicationAsset::UW_DECLINED) {
                    return false;
                }
                $cid = (int) ($row->customer_asset_id ?? 0);

                return $cid > 0 && in_array($cid, $onLoanIds, true) && $row->customerAsset;
            })
            ->values();

        $cards = $pledges->map(function (LoanApplicationAsset $pledge) use ($application, $viewer) {
            $asset = $pledge->customerAsset;
            $ownerId = (int) ($asset->customer_id ?? 0);
            $ownerRole = $ownerId === (int) $application->customer_id
                ? (filled($application->loan_group_id) ? 'Group leader' : 'Borrower')
                : 'Member';

            return $this->forAsset($asset, $application, $viewer, [
                'owner_role' => $ownerRole,
                'source_label' => 'On this loan',
            ]);
        })->values();

        $stored = $this->coverage->forApplication($application);
        $valuedPledges = $pledges->filter(fn (LoanApplicationAsset $row) => filled($row->forced_sale_value));
        $totalFsv = $stored['forced_sale_value'] ?? (float) $valuedPledges->sum(fn (LoanApplicationAsset $row) => (float) $row->forced_sale_value);
        $coverAmount = $stored['max_loan_amount'] ?? (float) $valuedPledges->sum(function (LoanApplicationAsset $row) {
            $ownMax = (float) ($row->max_loan_amount ?? 0);
            if ($ownMax > 0) {
                return $ownMax;
            }

            return $this->coverage->maxLoanFromForcedSale(
                (float) $row->forced_sale_value,
                (string) ($row->asset_type ?: $row->customerAsset?->asset_type ?: 'default'),
            );
        });
        $required = (float) ($stored['requested_amount'] ?? $application->requested_amount ?? 0);
        $ratio = $required > 0 && $coverAmount > 0 ? round($coverAmount / $required, 1) : null;

        return [
            'count' => $cards->count(),
            'total_fsv' => (float) $totalFsv,
            'required_security' => $required,
            'cover_amount' => (float) $coverAmount,
            'coverage_ratio' => $ratio,
            'preview_limit' => self::PREVIEW_LIMIT,
            'cards' => $cards,
        ];
    }

    private function pledgeFor(CustomerAsset $asset, ?LoanApplication $application): ?LoanApplicationAsset
    {
        if (! $application) {
            return null;
        }

        $application->loadMissing('collateralAssets.customerAsset');

        return $application->collateralAssets->first(
            fn (LoanApplicationAsset $row) => (int) $row->customer_asset_id === (int) $asset->id
        );
    }
}
