<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApplicationOfferService
{
    public const RECOMMEND_APPROVE = 'approve';

    public const RECOMMEND_COUNTER = 'counter';

    public const RECOMMEND_ASSET = 'asset_alternative';

    public function __construct(
        private readonly AffordabilityService $affordability,
        private readonly GroupAffordabilityService $groupAffordability,
        private readonly NotificationService $notifications,
        private readonly DisplayedRateService $rates,
        private readonly AssetBackedLoanService $assetBacked,
    ) {}

    /** @return array{amount: float, tenure_months: int, installment: float} */
    public function maxCounterOffer(LoanApplication $application): array
    {
        $application->loadMissing(['customer', 'product']);
        $tenure = (int) ($application->requested_tenure_months ?? 12);
        $amount = $this->groupAffordability->maxAffordablePrincipal($application, $tenure);

        $product = $application->product;
        if ($product) {
            $amount = min($amount, (float) $product->max_amount);
            $amount = max($amount, (float) $product->min_amount);
        }

        $ltvCap = $this->assetBacked->maxOfferAmount($application);
        if ($ltvCap !== null && $ltvCap > 0) {
            $amount = min($amount, $ltvCap);
        }

        $amount = floor(max(0, $amount) / 1000) * 1000;
        $rate = $product
            ? $this->rates->displayedMonthlyRate($product, $amount)
            : 0.0;

        return [
            'amount'          => $amount,
            'tenure_months'   => $tenure,
            'installment'     => $this->affordability->estimateInstallment($amount, $rate, $tenure),
        ];
    }

    public function submitRecommendation(
        LoanApplication $application,
        User $user,
        string $type,
        ?float $recommendedAmount,
        ?int $tenureMonths,
        ?string $remarks,
        ?int $alternativeProductId = null,
        ?string $rationale = null,
        ?string $preferredRejectionReasonCode = null,
        ?string $additionalNotes = null,
    ): LoanApplication {
        if (! in_array($type, [self::RECOMMEND_APPROVE, self::RECOMMEND_COUNTER, self::RECOMMEND_ASSET], true)) {
            throw ValidationException::withMessages(['recommendation_type' => 'Invalid recommendation type.']);
        }

        $remarks = trim((string) $remarks);
        if ($remarks === '') {
            throw ValidationException::withMessages(['remarks' => 'Explain why you are making this decision.']);
        }

        $additionalNotes = trim((string) $additionalNotes) ?: null;

        $application->loadMissing(['customer', 'product']);
        $crb = app(CrbCreditCheckService::class)->summaryForCustomer($application->customer, $application);
        $crbRec = strtolower((string) ($crb['recommendation'] ?? ''));

        $rationaleLabels = config('credit_recommendation.rationales', []);
        // Prefer an explicit rationale; otherwise derive align/differ from CRB automatically.
        if ($rationale === null || $rationale === '' || ! array_key_exists($rationale, $rationaleLabels)) {
            $differs = $this->recommendationDiffersFromCrb($type, $crbRec, null);
            $rationale = match (true) {
                $type === self::RECOMMEND_COUNTER => 'counter_capacity',
                $differs => 'differs_risk',
                default => 'aligns_with_crb',
            };
        }

        $screeningPayload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $screeningPayload['recommendation_meta'] = [
            'rationale' => $rationale,
            'rationale_label' => $rationaleLabels[$rationale] ?? $rationale,
            'decision_reason' => $remarks,
            'additional_notes' => $additionalNotes,
            'crb_recommendation' => $crbRec !== '' ? $crbRec : null,
            'differs_from_crb' => $this->recommendationDiffersFromCrb($type, $crbRec, $rationale),
            'preferred_rejection_reason_code' => $preferredRejectionReasonCode,
            'submitted_at' => now()->toIso8601String(),
        ];

        $preferredRejection = filled($preferredRejectionReasonCode)
            && app(LoanRejectionReasonService::class)->isValidCode($preferredRejectionReasonCode)
            ? $preferredRejectionReasonCode
            : null;

        $committeeNotes = $additionalNotes
            ? $remarks."\n\nNotes: ".$additionalNotes
            : $remarks;

        if ($type === self::RECOMMEND_ASSET) {
            if (! app(UnderwritingSettingsService::class)->assetBackedAlternativeEnabled()) {
                throw ValidationException::withMessages([
                    'recommendation_type' => 'Asset-backed alternatives are disabled in underwriting settings.',
                ]);
            }

            $product = $alternativeProductId
                ? LoanProduct::find($alternativeProductId)
                : LoanProduct::where('code', 'AB')->where('is_active', true)->first();

            abort_unless($product, 422, 'Asset-backed product not configured.');

            $application->update([
                'recommendation_type'         => self::RECOMMEND_ASSET,
                'alternative_loan_product_id' => $product->id,
                'offer_status'                => 'pending_asset_conversion',
                'committee_recommendation'    => $committeeNotes,
                'recommended_by'              => $user->id,
                'recommended_at'              => now(),
                'screening_payload'           => $screeningPayload,
                'screening_rejection_reason_code' => $preferredRejection ?? $application->screening_rejection_reason_code,
            ]);

            $this->notifyAssetAlternative($application->fresh(['customer', 'product']), $product);

            return $application->fresh();
        }

        $affordability = $this->affordability->evaluate($application);

        if ($type === self::RECOMMEND_APPROVE) {
            if ($affordability['verdict'] === 'fail') {
                $settings = app(UnderwritingSettingsService::class);
                $message = $settings->automaticRejectionEnabled()
                    ? 'Affordability failed — reject the application or return for documents.'
                    : 'Affordability failed — recommend a counter-offer or asset-backed alternative instead.';

                throw ValidationException::withMessages([
                    'recommendation_type' => $message,
                ]);
            }
            $recommendedAmount = (float) $application->requested_amount;
            $tenureMonths = (int) $application->requested_tenure_months;

            $ltvCap = $this->assetBacked->maxOfferAmount($application);
            if ($ltvCap !== null && $ltvCap > 0 && $recommendedAmount > $ltvCap) {
                throw ValidationException::withMessages([
                    'recommendation_type' => 'Requested amount exceeds the LTV cap of '.format_money($ltvCap).' based on valuation.',
                ]);
            }
        }

        if ($type === self::RECOMMEND_COUNTER) {
            if (! app(UnderwritingSettingsService::class)->counterOffersEnabled()) {
                throw ValidationException::withMessages([
                    'recommendation_type' => 'Counter-offers are disabled in underwriting settings.',
                ]);
            }
            $counter = $this->maxCounterOffer($application);
            $recommendedAmount = $counter['amount'];
            $tenureMonths = $tenureMonths ?: $counter['tenure_months'];

            if ($recommendedAmount <= 0) {
                throw ValidationException::withMessages([
                    'recommended_amount' => 'Could not calculate a counter-offer amount from income data.',
                ]);
            }
        }

        $application->update([
            'recommendation_type'      => $type,
            'recommended_amount'       => $recommendedAmount,
            'committee_recommendation' => $committeeNotes,
            'recommended_by'           => $user->id,
            'recommended_at'           => now(),
            'offer_status'             => null,
            'offered_amount'           => null,
            'offered_tenure_months'    => null,
            'screening_payload'        => $screeningPayload,
            'screening_rejection_reason_code' => $preferredRejection ?? $application->screening_rejection_reason_code,
        ]);

        return $application->fresh();
    }

    private function recommendationDiffersFromCrb(string $type, string $crbRec, ?string $rationale): bool
    {
        if ($rationale && str_starts_with($rationale, 'differs_')) {
            return true;
        }

        if ($crbRec === '') {
            return false;
        }

        return match ($type) {
            self::RECOMMEND_APPROVE => $crbRec !== 'approve',
            self::RECOMMEND_COUNTER, self::RECOMMEND_ASSET => $crbRec === 'approve',
            default => false,
        };
    }

    public function issueOffer(
        LoanApplication $application,
        User $user,
        float $offeredAmount,
        int $offeredTenure,
        ?string $remarks = null,
    ): LoanApplication {
        $application->loadMissing(['customer', 'product']);

        if (! in_array($application->recommendation_type, [self::RECOMMEND_COUNTER, self::RECOMMEND_APPROVE], true)) {
            throw ValidationException::withMessages(['action' => 'Issue a counter-offer only after a credit recommendation.']);
        }

        $product = $application->product;
        if ($product) {
            if ($offeredAmount < (float) $product->min_amount || $offeredAmount > (float) $product->max_amount) {
                throw ValidationException::withMessages([
                    'offered_amount' => 'Amount must be between '.format_money($product->min_amount).' and '.format_money($product->max_amount).'.',
                ]);
            }
        }

        $ltvCap = $this->assetBacked->maxOfferAmount($application);
        if ($ltvCap !== null && $ltvCap > 0 && $offeredAmount > $ltvCap) {
            throw ValidationException::withMessages([
                'offered_amount' => 'Offer cannot exceed the LTV cap of '.format_money($ltvCap).' from collateral valuation.',
            ]);
        }

        $application->update([
            'offered_amount'           => $offeredAmount,
            'offered_tenure_months'    => $offeredTenure,
            'recommended_amount'       => $offeredAmount,
            'offer_status'             => 'pending_borrower',
            'offer_issued_at'          => now(),
            'committee_recommendation' => $remarks ?: $application->committee_recommendation,
            'status'                   => 'awaiting_offer',
        ]);

        $this->notifyOfferIssued($application->fresh(['customer', 'product']));

        return $application->fresh();
    }

    public function acceptOffer(LoanApplication $application, Customer $customer): LoanApplication
    {
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);
        abort_unless($application->offer_status === 'pending_borrower', 422);

        $agreementService = app(LoanAgreementService::class);
        $offer = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        if ($offer && ! $offer->isSigned() && ! $offer->isOfferExpired()) {
            $agreementService->acceptDirectly($offer);
        }

        return $agreementService->advanceAfterOfferAcceptance($application->fresh());
    }

    public function declineOffer(LoanApplication $application, Customer $customer, ?string $reason = null): LoanApplication
    {
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);
        abort_unless($application->offer_status === 'pending_borrower', 422);

        $offer = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        if ($offer && ! $offer->isSigned()) {
            app(LoanAgreementService::class)->declineOfferLetter($offer);
        }

        $application->update([
            'offer_status'          => 'declined',
            'offer_responded_at'    => now(),
            'offer_decline_reason'  => filled($reason) ? $reason : null,
            'status'                => 'withdrawn',
        ]);

        return $application->fresh();
    }

    public function resendDeclinedOffer(LoanApplication $application, User $user): LoanApplication
    {
        abort_unless($this->offerDeclinedByBorrower($application), 422, 'Only declined offers can be resent.');

        app(LoanAgreementService::class)->generateOfferLetter($application, regenerate: true);

        $this->notifyOfferIssued($application->fresh(['customer', 'product']));

        return $application->fresh();
    }

    public function reissueDeclinedOffer(
        LoanApplication $application,
        User $user,
        float $offeredAmount,
        int $offeredTenure,
        ?string $remarks = null,
    ): LoanApplication {
        abort_unless($this->offerDeclinedByBorrower($application), 422, 'Only declined offers can be reissued.');

        $application->loadMissing('product');
        $product = $application->product;

        if ($product) {
            if ($offeredAmount < (float) $product->min_amount || $offeredAmount > (float) $product->max_amount) {
                throw ValidationException::withMessages([
                    'offered_amount' => 'Amount must be between '.format_money($product->min_amount).' and '.format_money($product->max_amount).'.',
                ]);
            }
        }

        $ltvCap = $this->assetBacked->maxOfferAmount($application);
        if ($ltvCap !== null && $ltvCap > 0 && $offeredAmount > $ltvCap) {
            throw ValidationException::withMessages([
                'offered_amount' => 'Offer cannot exceed the LTV cap of '.format_money($ltvCap).' from collateral valuation.',
            ]);
        }

        $application->update([
            'offered_amount'           => $offeredAmount,
            'offered_tenure_months'    => $offeredTenure,
            'recommended_amount'       => $offeredAmount,
            'committee_recommendation' => $remarks ?: $application->committee_recommendation,
        ]);

        app(LoanAgreementService::class)->generateOfferLetter($application, regenerate: true);

        $this->notifyOfferIssued($application->fresh(['customer', 'product']));

        return $application->fresh();
    }

    public function offerDeclinedByBorrower(LoanApplication $application): bool
    {
        if ($application->offer_status === 'declined') {
            return true;
        }

        $offer = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        return ($offer?->isCancelled() ?? false) && ! ($offer?->isSigned() ?? false);
    }

    public function effectiveAmount(LoanApplication $application): float
    {
        return (float) (
            $application->offered_amount
            ?? $application->recommended_amount
            ?? $application->requested_amount
        );
    }

    public function canFinalApprove(LoanApplication $application): bool
    {
        if ($application->offer_status === 'pending_borrower') {
            return false;
        }

        if ($this->pendingAssetConversion($application) || $application->offer_status === 'asset_conversion_fee_due') {
            return false;
        }

        if ($application->recommendation_type === self::RECOMMEND_COUNTER && $application->offer_status !== 'accepted') {
            return false;
        }

        if ($application->recommendation_type === self::RECOMMEND_ASSET) {
            return false;
        }

        return ($application->current_stage ?? '') === 'pre_approval';
    }

    /**
     * Committee can one-click validate the screening decision when it still applies.
     */
    public function canValidateScreening(LoanApplication $application, User $user): bool
    {
        if (($application->current_stage ?? '') !== 'pre_approval') {
            return false;
        }

        if (! filled($application->recommendation_type)) {
            return false;
        }

        if ($application->offer_status === 'pending_borrower') {
            return false;
        }

        $permissions = app(PermissionService::class);

        return match ($application->recommendation_type) {
            self::RECOMMEND_APPROVE => $this->canFinalApprove($application)
                && $permissions->has($user, 'applications.approve'),
            self::RECOMMEND_COUNTER => app(UnderwritingSettingsService::class)->counterOffersEnabled()
                && $application->offer_status !== 'accepted'
                && (float) ($application->recommended_amount ?? 0) > 0
                && $permissions->has($user, 'applications.pre_approve'),
            default => false,
        };
    }

    /**
     * Apply the screening recommendation as the committee decision without re-entering fields.
     *
     * @return array{action: string, message: string}
     */
    public function validateScreeningDecision(LoanApplication $application, User $user): array
    {
        if (! $this->canValidateScreening($application, $user)) {
            throw ValidationException::withMessages([
                'action' => 'This screening decision cannot be validated in one click right now.',
            ]);
        }

        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['committee_meta'] = [
            'validated_screening' => true,
            'validated_at' => now()->toIso8601String(),
            'validated_by' => $user->id,
            'screening_recommendation_type' => $application->recommendation_type,
        ];
        $application->update(['screening_payload' => $payload]);

        if ($application->recommendation_type === self::RECOMMEND_COUNTER) {
            $this->issueOffer(
                $application->fresh(),
                $user,
                (float) $application->recommended_amount,
                (int) ($application->requested_tenure_months ?? 6),
                'Committee validated the screening counter-offer.',
            );

            return [
                'action' => 'issue_offer',
                'message' => 'Screening counter-offer validated and issued to the borrower.',
            ];
        }

        // Approve path — final approve is done by the workflow transition in the controller.
        return [
            'action' => 'approve',
            'message' => 'Screening approval validated — completing final approve.',
        ];
    }

    public function recordCommitteeDivergence(
        LoanApplication $application,
        User $user,
        string $committeeAction,
        ?string $rationale,
        ?string $remarks,
    ): void {
        $rationaleLabels = config('credit_recommendation.committee_rationales', []);
        if (! $rationale || ! array_key_exists($rationale, $rationaleLabels)) {
            throw ValidationException::withMessages([
                'committee_rationale' => 'Select why your decision differs from screening.',
            ]);
        }

        $remarks = trim((string) $remarks);
        if ($remarks === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Add notes explaining how your decision differs from screening.',
            ]);
        }

        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['committee_meta'] = [
            'validated_screening' => false,
            'differs_from_screening' => true,
            'committee_action' => $committeeAction,
            'screening_recommendation_type' => $application->recommendation_type,
            'rationale' => $rationale,
            'rationale_label' => $rationaleLabels[$rationale] ?? $rationale,
            'remarks' => $remarks,
            'decided_at' => now()->toIso8601String(),
            'decided_by' => $user->id,
        ];
        $application->update(['screening_payload' => $payload]);
    }

    public function pendingAssetConversion(LoanApplication $application): bool
    {
        return $application->recommendation_type === self::RECOMMEND_ASSET
            && $application->alternative_loan_product_id
            && in_array($application->offer_status, [null, 'pending_asset_conversion'], true);
    }

    public function needsConversionFee(LoanApplication $application): bool
    {
        return $application->offer_status === 'asset_conversion_fee_due'
            && $application->alternative_loan_product_id;
    }

    /** @return array{status: string, quote: array<string, mixed>} */
    public function acceptAssetConversion(LoanApplication $application, Customer $customer): array
    {
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);
        abort_unless($this->pendingAssetConversion($application), 422);

        $application->loadMissing(['alternativeProduct', 'product']);
        $quote = app(ApplicationFeeCreditService::class)->conversionQuote(
            $application,
            $application->alternativeProduct,
        );

        if (($quote['due'] ?? 0) > 0) {
            $application->update(['offer_status' => 'asset_conversion_fee_due']);

            return ['status' => 'fee_due', 'quote' => $quote];
        }

        $this->completeAssetConversion($application);

        return ['status' => 'converted', 'quote' => $quote];
    }

    public function completeAssetConversion(LoanApplication $application): LoanApplication
    {
        $application->loadMissing(['customer', 'alternativeProduct']);
        $newProduct = $application->alternativeProduct;
        abort_unless($newProduct, 422, 'Asset-backed product not configured.');

        $newFee = app(ApplicationFeeCreditService::class)->quotedFee($application->customer, $newProduct);
        $credit = app(ApplicationFeeCreditService::class)->paidCredit($application->customer, $application);
        $feeStatus = $credit >= $newFee ? 'paid' : ($application->application_fee_status ?? 'unpaid');

        $application->update([
            'loan_product_id'             => $newProduct->id,
            'application_fee_amount'      => $newFee,
            'application_fee_status'      => $feeStatus,
            'recommendation_type'       => null,
            'alternative_loan_product_id' => null,
            'offer_status'                => 'asset_conversion_accepted',
            'offer_responded_at'          => now(),
        ]);

        return $application->fresh(['product']);
    }

    public function declineAssetConversion(LoanApplication $application, Customer $customer): LoanApplication
    {
        abort_unless((int) $application->customer_id === (int) $customer->id, 403);
        abort_unless(
            $this->pendingAssetConversion($application) || $this->needsConversionFee($application),
            422,
        );

        $application->update([
            'recommendation_type'       => null,
            'alternative_loan_product_id' => null,
            'offer_status'              => 'declined',
            'offer_responded_at'        => now(),
            'status'                    => 'withdrawn',
        ]);

        return $application->fresh();
    }

    private function notifyOfferIssued(LoanApplication $application): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $this->notifications->notifyInApp(
            $customer,
            __('borrower.offer.notify_message', [
                'reference' => $application->application_number,
                'requested' => format_money((float) $application->requested_amount),
                'offered'   => format_money((float) ($application->offered_amount ?? 0)),
            ]),
            'application',
            'loan_counter_offer',
            __('borrower.offer.notify_title'),
            route('site.borrower.application.offer', $application->id),
            __('borrower.offer.review_cta'),
        );
    }

    private function notifyAssetAlternative(LoanApplication $application, LoanProduct $product): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $applyUrl = route('site.borrower.application.asset-conversion', $application->id);

        $this->notifications->notifyInApp(
            $customer,
            __('borrower.offer.asset_alternative_message', [
                'reference' => $application->application_number,
                'product'   => $product->name,
            ]),
            'application',
            'asset_alternative_offer',
            __('borrower.offer.asset_alternative_title'),
            $applyUrl,
            __('borrower.offer.asset_alternative_cta'),
        );
    }
}
