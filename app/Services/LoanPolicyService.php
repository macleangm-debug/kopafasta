<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanTopUpRequest;
use App\Models\MarketplaceAsset;
use App\Models\RepaymentSchedule;
use App\Models\RestructureRequest;
use App\Models\Setting;

class LoanPolicyService
{
    /** @return array<string, mixed> */
    public function settings(): array
    {
        $loan = Setting::group('loan');

        return [
            'max_active_applications_per_product' => (int) ($loan['max_active_applications_per_product'] ?? 1),
            'max_active_loans'                    => (int) ($loan['max_active_loans'] ?? 1),
            'max_active_guarantees'                 => (int) ($loan['max_active_guarantees'] ?? 5),
            'allow_asset_reuse'                     => (bool) ($loan['allow_asset_reuse'] ?? false),
            'top_up_min_successful_repayments'      => (int) ($loan['top_up_min_successful_repayments'] ?? 6),
            'allow_restructure'                     => (bool) ($loan['allow_restructure'] ?? false),
            'max_restructures'                      => (int) ($loan['max_restructures'] ?? 2),
            'restructure_cooldown_days'             => (int) ($loan['restructure_cooldown_days'] ?? 30),
            'payment_holiday_accrue_interest'       => (bool) ($loan['payment_holiday_accrue_interest'] ?? true),
            'payment_holiday_max_months'            => (int) ($loan['payment_holiday_max_months'] ?? 3),
            'guarantor_required_above'              => (float) ($loan['guarantor_required_above'] ?? 0),
            'collateral_requirement_mode'           => $this->normalizeCollateralMode($loan['collateral_requirement_mode'] ?? null),
            'collateral_required_above'             => (float) ($loan['collateral_required_above'] ?? 0),
            'min_guarantors'                        => (int) ($loan['min_guarantors'] ?? 1),
        ];
    }

    public function requiresGuarantorForApplication(LoanProduct $product, float $requestedAmount): bool
    {
        if (is_group_loan_product($product)) {
            return false;
        }

        if ($product->requires_guarantor) {
            return true;
        }

        $threshold = $this->settings()['guarantor_required_above'];

        return $threshold > 0 && $requestedAmount >= $threshold;
    }

    public function requiresCollateralForApplication(LoanProduct $product, float $requestedAmount): bool
    {
        if ($this->productAlwaysRequiresCollateral($product)) {
            return true;
        }

        $settings = $this->settings();

        return match ($settings['collateral_requirement_mode']) {
            'never' => false,
            'always' => true,
            default => $settings['collateral_required_above'] > 0
                && $requestedAmount >= $settings['collateral_required_above'],
        };
    }

    public function applicationRequiresCollateral(LoanApplication $application): bool
    {
        $application->loadMissing('product');
        if (! $application->product) {
            return false;
        }

        return $this->requiresCollateralForApplication(
            $application->product,
            (float) $application->requested_amount,
        );
    }

    public function productAlwaysRequiresCollateral(LoanProduct $product): bool
    {
        if ($product->requires_collateral) {
            return true;
        }

        $category = strtolower((string) ($product->category ?? ''));
        if (in_array($category, ['asset_finance', 'asset_lending'], true)) {
            return true;
        }

        $assetCode = strtoupper((string) config('asset_marketplace.asset_loan_product_code', 'AL'));

        return strtoupper((string) ($product->code ?? '')) === $assetCode;
    }

    private function normalizeCollateralMode(mixed $mode): string
    {
        $value = strtolower(trim((string) $mode));

        return in_array($value, ['never', 'always', 'above_amount'], true)
            ? $value
            : 'above_amount';
    }

    public function canSubmitApplication(Customer $customer, LoanProduct $product, ?LoanApplication $excluding = null): ?string
    {
        if ($blocked = $this->canOpenAdditionalLoan($customer)) {
            return $blocked;
        }

        $blocking = $this->blockingApplicationForProduct($customer, $product, $excluding);
        if ($blocking) {
            $max = $this->settings()['max_active_applications_per_product'];

            return __('borrower.policy.max_active_applications', [
                'product' => $product->localizedName() ?: $product->name,
                'max'     => $max,
            ]);
        }

        return null;
    }

    public function blockingApplicationForProduct(Customer $customer, LoanProduct $product, ?LoanApplication $excluding = null): ?LoanApplication
    {
        $max = $this->settings()['max_active_applications_per_product'];
        $query = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->where('loan_product_id', $product->id)
            ->whereNotIn('status', ['rejected', 'disbursed', 'withdrawn'])
            ->latest('id');

        if ($excluding) {
            $query->where('id', '!=', $excluding->id);
        }

        $count = (clone $query)->count();
        if ($count < $max) {
            return null;
        }

        return $query->first();
    }

    public function activeLoanCount(Customer $customer): int
    {
        return Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring', 'defaulted'])
            ->count();
    }

    public function canOpenAdditionalLoan(Customer $customer): ?string
    {
        $max = $this->settings()['max_active_loans'];
        $count = $this->activeLoanCount($customer);

        if ($count >= $max) {
            return __('borrower.policy.max_active_loans', ['max' => $max]);
        }

        return null;
    }

    public function activeGuaranteeCount(Customer $guarantor): int
    {
        return GuarantorInvitation::query()
            ->where('guarantor_customer_id', $guarantor->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->whereHas('customerGuarantor', fn ($q) => $q->whereIn('status', ['pending', 'accepted']))
            ->where(function ($query) {
                $query->whereNull('loan_application_id')
                    ->orWhereHas('application', fn ($app) => $app->whereNotIn('status', ['rejected', 'disbursed', 'withdrawn']));
            })
            ->count();
    }

    public function activeGuaranteeExposure(Customer $guarantor): float
    {
        return (float) GuarantorInvitation::query()
            ->where('guarantor_customer_id', $guarantor->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->whereHas('customerGuarantor', fn ($q) => $q->whereIn('status', ['pending', 'accepted']))
            ->where(function ($query) {
                $query->whereNull('loan_application_id')
                    ->orWhereHas('application', fn ($app) => $app->whereNotIn('status', ['rejected', 'disbursed', 'withdrawn']));
            })
            ->sum('requested_amount');
    }

    public function canAcceptGuarantee(Customer $guarantor, ?float $requestedAmount = null): ?string
    {
        $max = $this->settings()['max_active_guarantees'];
        $count = $this->activeGuaranteeCount($guarantor);

        if ($count >= $max) {
            return __('borrower.policy.max_active_guarantees', ['max' => $max]);
        }

        if ($requestedAmount !== null && $requestedAmount > 0) {
            $estimatedEmi = round($requestedAmount / 12, 2);
            $affordability = app(AffordabilityService::class)->evaluateForGuarantor($guarantor, $estimatedEmi);
            if ($affordability['verdict'] === 'fail') {
                return __('borrower.policy.guarantor_affordability_failed');
            }
        }

        return null;
    }

    public function canRestructureLoan(Loan $loan): ?string
    {
        if (! $this->settings()['allow_restructure']) {
            return __('borrower.policy.restructure_disabled');
        }

        if (! in_array($loan->status, ['active', 'disbursed', 'arrears'], true)) {
            return __('borrower.policy.restructure_after_disbursement');
        }

        return null;
    }

    public function canSubmitRestructureRequest(Loan $loan): ?string
    {
        $blocked = $this->canRestructureLoan($loan);
        if ($blocked) {
            return $blocked;
        }

        if (RestructureRequest::query()->where('loan_id', $loan->id)->where('status', 'pending')->exists()) {
            return __('borrower.policy.restructure_pending');
        }

        $settings = $this->settings();
        $approvedCount = RestructureRequest::query()
            ->where('loan_id', $loan->id)
            ->where('status', 'approved')
            ->count();

        if ($approvedCount >= $settings['max_restructures']) {
            return __('borrower.policy.restructure_max_reached', ['max' => $settings['max_restructures']]);
        }

        $lastApproved = RestructureRequest::query()
            ->where('loan_id', $loan->id)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->latest('approved_at')
            ->first();

        if ($lastApproved?->approved_at) {
            $daysSince = $lastApproved->approved_at->diffInDays(now());
            if ($daysSince < $settings['restructure_cooldown_days']) {
                $remaining = $settings['restructure_cooldown_days'] - $daysSince;

                return __('borrower.policy.restructure_cooldown', ['days' => $remaining]);
            }
        }

        return null;
    }

    public function canRequestTopUp(Loan $loan): ?string
    {
        if (! in_array($loan->status, ['active', 'disbursed', 'arrears'], true)) {
            return __('borrower.policy.top_up_after_disbursement');
        }

        if ($loan->status === 'arrears') {
            return __('borrower.policy.top_up_no_arrears');
        }

        $minPaid = $this->settings()['top_up_min_successful_repayments'];
        $paidCount = RepaymentSchedule::query()
            ->where('loan_id', $loan->id)
            ->where('status', 'paid')
            ->count();

        if ($paidCount < $minPaid) {
            return __('borrower.policy.top_up_repayments_required', ['count' => $minPaid]);
        }

        return null;
    }

    public function canSubmitTopUpRequest(Loan $loan): ?string
    {
        $blocked = $this->canRequestTopUp($loan);
        if ($blocked) {
            return $blocked;
        }

        if (LoanTopUpRequest::query()->where('loan_id', $loan->id)->where('status', 'pending')->exists()) {
            return __('borrower.policy.top_up_pending');
        }

        return null;
    }

    public function canUseAsset(MarketplaceAsset $asset, ?Customer $customer = null): ?string
    {
        if (! $asset->is_active) {
            return __('borrower.policy.asset_inactive');
        }

        if (! $asset->isAvailable()) {
            $ownsActive = $customer && AssetReservation::query()
                ->where('customer_id', $customer->id)
                ->where('marketplace_asset_id', $asset->id)
                ->whereNotIn('status', ['released', 'cancelled'])
                ->exists();

            if (! $ownsActive) {
                return __('borrower.policy.asset_locked');
            }
        }

        if (! $this->settings()['allow_asset_reuse']) {
            $previouslyDisbursed = AssetReservation::query()
                ->where('marketplace_asset_id', $asset->id)
                ->whereHas('loanApplication', fn ($q) => $q->where('status', 'disbursed'))
                ->exists();

            if ($previouslyDisbursed) {
                return __('borrower.policy.asset_no_reuse');
            }
        }

        return null;
    }

    public function topUpAvailableAmount(Loan $loan, Customer $customer): float
    {
        $limit = app(LoanQualificationService::class)->calculate($customer)['amount'] ?? 0;

        return max(0, (float) $limit - (float) $loan->outstanding_balance);
    }

    /** @return array{count: int, exposure: float, max: int} */
    public function guarantorExposureSummary(Customer $guarantor): array
    {
        $settings = $this->settings();

        return [
            'count'    => $this->activeGuaranteeCount($guarantor),
            'exposure' => $this->activeGuaranteeExposure($guarantor),
            'max'      => $settings['max_active_guarantees'],
        ];
    }

    public function assertGuarantorNotOverLimit(?Customer $memberGuarantor, ?float $requestedAmount = null): ?string
    {
        if (! $memberGuarantor) {
            return null;
        }

        return $this->canAcceptGuarantee($memberGuarantor, $requestedAmount);
    }

    public function expireSupersededGuarantorLinks(Customer $borrower, ?int $exceptInvitationId = null): void
    {
        GuarantorInvitation::query()
            ->where('customer_id', $borrower->id)
            ->whereNull('loan_application_id')
            ->whereIn('status', ['pending', 'accepted'])
            ->when($exceptInvitationId, fn ($q) => $q->where('id', '!=', $exceptInvitationId))
            ->each(function (GuarantorInvitation $invitation): void {
                $invitation->update(['status' => 'expired', 'expires_at' => now()]);
                $invitation->customerGuarantor?->update(['status' => 'rejected']);
            });
    }
}
