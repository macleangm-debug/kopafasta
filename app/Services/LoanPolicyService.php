<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Models\Setting;

class LoanPolicyService
{
    /** @return array<string, mixed> */
    public function settings(): array
    {
        $loan = Setting::group('loan');

        return [
            'max_active_applications_per_product' => (int) ($loan['max_active_applications_per_product'] ?? 1),
            'max_active_guarantees'                 => (int) ($loan['max_active_guarantees'] ?? 5),
            'allow_asset_reuse'                     => (bool) ($loan['allow_asset_reuse'] ?? false),
            'top_up_min_successful_repayments'      => (int) ($loan['top_up_min_successful_repayments'] ?? 6),
            'allow_restructure'                     => (bool) ($loan['allow_restructure'] ?? true),
        ];
    }

    public function canSubmitApplication(Customer $customer, LoanProduct $product, ?LoanApplication $excluding = null): ?string
    {
        $max = $this->settings()['max_active_applications_per_product'];
        $query = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->where('loan_product_id', $product->id)
            ->whereNotIn('status', ['rejected', 'disbursed', 'withdrawn']);

        if ($excluding) {
            $query->where('id', '!=', $excluding->id);
        }

        if ($query->count() >= $max) {
            return __('borrower.policy.max_active_applications', [
                'product' => $product->name,
                'max'     => $max,
            ]);
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

    public function canAcceptGuarantee(Customer $guarantor): ?string
    {
        $max = $this->settings()['max_active_guarantees'];
        $count = $this->activeGuaranteeCount($guarantor);

        if ($count >= $max) {
            return __('borrower.policy.max_active_guarantees', ['max' => $max]);
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

    public function assertGuarantorNotOverLimit(?Customer $memberGuarantor): ?string
    {
        if (! $memberGuarantor) {
            return null;
        }

        return $this->canAcceptGuarantee($memberGuarantor);
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
