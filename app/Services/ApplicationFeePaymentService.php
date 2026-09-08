<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Validation\ValidationException;

class ApplicationFeePaymentService
{
    public function usesDummyGateway(): bool
    {
        return payment_gateway_is_dummy();
    }

    public function generatePaymentReference(): string
    {
        return app(CustomerPaymentService::class)->generateReference();
    }

    /**
     * Canonical application-fee decision for this customer + product.
     *
     * @param  array<string, mixed>|null  $draftPayload
     * @return array{
     *     status: 'not_applicable'|'due'|'initiated'|'paid'|'failed',
     *     required: bool,
     *     amount: int,
     *     basis: 'flat'|'per_member'|'origination',
     *     member_count: int|null,
     *     payment: ?CustomerPayment,
     *     wait_url: ?string
     * }
     */
    public function obligation(
        Customer $customer,
        LoanProduct $product,
        ?array $draftPayload = null,
        ?LoanApplication $application = null,
    ): array {
        $groups = app(GroupLendingService::class);
        $isGroup = $groups->isGroupProduct($product);
        $memberCount = $isGroup
            ? max(1, $groups->memberCountFromPayload(is_array($draftPayload['group'] ?? null) ? $draftPayload['group'] : null))
            : null;
        $amount = $this->requiredAmount($customer, $product, $draftPayload);
        $basis = $isGroup
            ? 'per_member'
            : (product_includes_valuation_fee($product) ? 'origination' : 'flat');

        $empty = [
            'required' => $amount > 0,
            'amount' => $amount,
            'basis' => $basis,
            'member_count' => $memberCount,
            'payment' => null,
            'wait_url' => null,
        ];

        if ($amount <= 0) {
            return ['status' => 'not_applicable'] + $empty;
        }

        // Canonical settlement only: verified CustomerPayment (or explicit waive).
        // Never trust draft/session/frontend paid flags alone — cancel→quote must stay blocked.
        $payment = $this->latestFeePayment($customer, $product, $application, $draftPayload);
        $draftState = is_array($draftPayload['application_fee'] ?? null) ? $draftPayload['application_fee'] : null;
        $draftStatus = (string) ($draftState['status'] ?? '');

        if ($payment && in_array($payment->status, ['paid', 'verified'], true)) {
            return ['status' => 'paid', 'payment' => $payment] + $empty;
        }

        if ($draftStatus === 'waived' && (int) ($draftState['amount'] ?? 0) <= 0) {
            return ['status' => 'paid'] + $empty;
        }

        if ($application && in_array((string) ($application->application_fee_status ?? ''), ['paid', 'waived', 'charged'], true)) {
            // Application row is authoritative after submit; drafts must still prove payment.
            return ['status' => 'paid', 'payment' => $payment] + $empty;
        }

        if ($payment && in_array($payment->status, ['awaiting_payment', 'processing', 'pending_verification'], true)) {
            return [
                'status' => 'initiated',
                'payment' => $payment,
                'wait_url' => route('site.borrower.payments.show', $payment),
            ] + $empty;
        }

        if ($payment && in_array($payment->status, ['failed', 'expired', 'cancelled'], true)) {
            return [
                'status' => 'failed',
                'payment' => $payment,
                'wait_url' => route('site.borrower.payments.show', $payment),
            ] + $empty;
        }

        return ['status' => 'due'] + $empty;
    }

    /** @param  array<string, mixed>|null  $draftPayload */
    public function requiredAmount(Customer $customer, LoanProduct $product, ?array $draftPayload = null): int
    {
        $groups = app(GroupLendingService::class);
        if ($groups->isGroupProduct($product)) {
            $count = $groups->memberCountFromPayload(is_array($draftPayload['group'] ?? null) ? $draftPayload['group'] : null);

            return $groups->quotedApplicationFee($customer, $product, $count);
        }

        if (product_includes_valuation_fee($product)) {
            return quoted_origination_fee(
                $customer,
                $product,
                selected_collateral_count((array) ($draftPayload['form'] ?? [])),
            );
        }

        return quoted_application_fee($customer, $product);
    }

    public function isSatisfiedFor(
        Customer $customer,
        LoanProduct $product,
        ?array $draftPayload = null,
        ?LoanApplication $application = null,
    ): bool {
        return in_array($this->obligation($customer, $product, $draftPayload, $application)['status'], ['not_applicable', 'paid'], true);
    }

    public function blocksWizardStep(string $stepKey): bool
    {
        return in_array($stepKey, [
            'guarantor',
            'product_questions',
            'education_details',
            'review',
            'signature',
            'submit',
        ], true);
    }

    /**
     * Resolve the fee payment for this exact draft/application obligation only.
     * Never inherit an unbound prior payment by customer + product alone.
     *
     * @param  array<string, mixed>|null  $draftPayload
     */
    private function latestFeePayment(
        Customer $customer,
        LoanProduct $product,
        ?LoanApplication $application = null,
        ?array $draftPayload = null,
    ): ?CustomerPayment {
        $query = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'application_fee')
            ->where(function ($q) use ($product) {
                $q->where('loan_product_id', $product->id)
                    ->orWhere('provider_meta->apply_context->loan_product_id', $product->id);
            });

        $fee = is_array($draftPayload['application_fee'] ?? null) ? $draftPayload['application_fee'] : [];
        $paymentId = (int) ($fee['payment_id'] ?? 0);
        $reference = trim((string) ($fee['reference'] ?? ''));
        $draftReference = trim((string) (
            $draftPayload['draft_reference']
            ?? data_get($draftPayload, 'application_fee.draft_reference')
            ?? ''
        ));

        if ($paymentId > 0) {
            $found = (clone $query)->where('id', $paymentId)->first();

            return $this->paymentBoundToCurrentObligation($found, $application, $draftReference) ? $found : null;
        }

        if ($reference !== '') {
            $found = (clone $query)->where('reference', $reference)->first();

            return $this->paymentBoundToCurrentObligation($found, $application, $draftReference) ? $found : null;
        }

        if ($application) {
            $bound = (clone $query)
                ->where('source_type', LoanApplication::class)
                ->where('source_id', $application->id)
                ->latest('id')
                ->first();
            if ($bound) {
                return $bound;
            }
        }

        if ($draftReference !== '') {
            return (clone $query)
                ->where('provider_meta->apply_context->draft_reference', $draftReference)
                ->latest('id')
                ->first();
        }

        // No draft/application binding → fresh obligation (do not inherit orphans).
        return null;
    }

    private function paymentBoundToCurrentObligation(
        ?CustomerPayment $payment,
        ?LoanApplication $application,
        string $draftReference,
    ): bool {
        if (! $payment) {
            return false;
        }

        if ($application) {
            return $payment->source_type === LoanApplication::class
                && (int) $payment->source_id === (int) $application->id;
        }

        $paymentDraftRef = trim((string) data_get($payment->provider_meta, 'apply_context.draft_reference'));

        // When either side is draft-anchored, they must match. Never inherit another draft's fee.
        if ($draftReference !== '' || $paymentDraftRef !== '') {
            return $draftReference !== '' && $paymentDraftRef === $draftReference;
        }

        // Legacy unbound citation (payment_id/reference on draft fee state) — allow once only.
        return true;
    }

    /**
     * Abandon open fee attempts for a discarded draft. Keep rows for audit.
     */
    public function abandonOpenFeePaymentsForDraft(
        Customer $customer,
        ?int $loanProductId,
        ?string $draftReference,
    ): void {
        if (! $loanProductId && blank($draftReference)) {
            return;
        }

        $query = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'application_fee')
            ->whereIn('status', ['awaiting_payment', 'processing', 'pending_verification']);

        if ($loanProductId) {
            $query->where(function ($q) use ($loanProductId) {
                $q->where('loan_product_id', $loanProductId)
                    ->orWhere('provider_meta->apply_context->loan_product_id', $loanProductId);
            });
        }

        if (filled($draftReference)) {
            $query->where('provider_meta->apply_context->draft_reference', $draftReference);
        } else {
            // Discard without reference: cancel unbound open fees for this product only.
            $query->whereNull('source_id');
        }

        $query->each(function (CustomerPayment $payment): void {
            $meta = (array) ($payment->provider_meta ?? []);
            $meta['abandoned_at'] = now()->toIso8601String();
            $meta['abandon_reason'] = 'draft_discarded';
            $payment->update([
                'status' => 'cancelled',
                'provider_meta' => $meta,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function quote(
        Customer $customer,
        LoanProduct $product,
        bool $useWallet = false,
        ?string $promoCode = null,
        ?int $groupMemberCount = null,
        ?string $affiliateCode = null,
    ): array {
        $groups = app(GroupLendingService::class);
        if ($groups->isGroupProduct($product)) {
            // Always resolve roster size so payments.show charges fee × members (settings hub).
            if (! $groupMemberCount || $groupMemberCount < 1) {
                $draft = app(LoanApplicationDraftService::class)->find($customer, $product->id);
                $groupMemberCount = $groups->memberCountFromPayload($draft?->payload['group'] ?? null);
            }
            $base = (float) $groups->quotedApplicationFee($customer, $product, max(1, $groupMemberCount));
        } else {
            $assetCount = 1;
            if (product_includes_valuation_fee($product)) {
                $draft = app(LoanApplicationDraftService::class)->find($customer, $product->id);
                $assetCount = selected_collateral_count((array) ($draft?->payload['form'] ?? []));
            }
            $base = (float) quoted_origination_fee($customer, $product, $assetCount);
        }
        $cfg = MembershipService::config();

        [$effectivePromo, $effectiveAffiliate] = $this->resolvePromoOrAffiliate($promoCode, $affiliateCode, $customer);

        if ($base <= 0) {
            return [
                'base' => 0,
                'after_discount' => 0,
                'discount' => 0,
                'total_discount' => 0,
                'wallet_applied' => 0,
                'cash_due' => 0,
                'wallet_usable' => 0,
                'wallet_allowed' => false,
                'has_referrer' => false,
                'currency' => $cfg['currency'],
            ];
        }

        return app(PaymentGateService::class)->quote(
            $customer,
            $base,
            'application_fee',
            $useWallet,
            $effectivePromo,
            $effectiveAffiliate,
        );
    }

    /**
     * Wizard stage to open after application fee is confirmed — for every loan product.
     * Fee sits after the product setup step(s); resume on whatever comes next in the plan.
     *
     * @param  array<string, mixed>|null  $draftPayload
     */
    public function nextStepAfterApplicationFee(Customer $customer, LoanProduct $product, ?array $draftPayload = null): string
    {
        $amount = (float) (
            $draftPayload['form']['amount']
            ?? $draftPayload['inputs']['amount']
            ?? $draftPayload['form']['requested_amount']
            ?? $draftPayload['inputs']['requested_amount']
            ?? 0
        );

        $plan = app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product, $amount);
        $setupKeys = ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'group_members'];

        $lastSetupIndex = -1;
        foreach ($plan as $index => $step) {
            if (in_array($step['key'] ?? '', $setupKeys, true)) {
                $lastSetupIndex = $index;
            }
        }

        if ($lastSetupIndex >= 0 && isset($plan[$lastSetupIndex + 1]['key'])) {
            return (string) $plan[$lastSetupIndex + 1]['key'];
        }

        foreach ($plan as $step) {
            $key = (string) ($step['key'] ?? '');
            if ($key !== '' && ! in_array($key, $setupKeys, true)) {
                return $key;
            }
        }

        return 'review';
    }

    /**
     * Resume URL after PSP confirms the fee — lands on the next wizard stage, not quote.
     *
     * @param  array<string, mixed>|null  $draftPayload
     */
    public function resumeUrlAfterFee(Customer $customer, LoanProduct $product, ?array $draftPayload = null, ?string $stepKey = null): string
    {
        $next = $stepKey ?: $this->nextStepAfterApplicationFee($customer, $product, $draftPayload);

        return route('site.borrower.apply', [
            'product' => $product->id,
            'resume' => 1,
            'step_key' => $next,
        ]);
    }

    public function quoteResumeUrl(Customer $customer, LoanProduct $product): string
    {
        $setup = app(LoanApplicationDraftService::class)->lastSetupStepKeyForProduct($product);

        return route('site.borrower.apply', [
            'product' => $product->id,
            'resume' => 1,
            'step_key' => $setup ?: 'quote',
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string} [promoCode, affiliateCode]
     */
    public function resolvePromoOrAffiliate(?string $promoCode, ?string $affiliateCode = null, ?Customer $customer = null): array
    {
        // Only resolve codes the borrower explicitly entered. Affiliated customers keep
        // attribution/commission via affiliate_vendor_id — never inject KITONGA into promo UX.
        unset($customer);

        $affiliates = app(AffiliateService::class);
        $code = filled($affiliateCode) ? $affiliateCode : $promoCode;
        if (blank($code)) {
            return [null, null];
        }

        $code = strtoupper(trim((string) $code));

        if ($affiliates->findByCode($code)) {
            return [null, $code];
        }

        return [$code, null];
    }

    /**
     * Open the shared payments.show gate (method + USSD live there).
     * Wallet/promo settle only after PSP confirmation.
     *
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}
     */
    public function openSharedGate(
        Customer $customer,
        LoanProduct $product,
        string $paymentReference,
        bool $useWallet = false,
        ?string $promoCode = null,
        ?int $groupMemberCount = null,
        ?string $affiliateCode = null,
        ?string $mobileNumber = null,
    ): array {
        return $this->processMobileMoney(
            $customer,
            $product,
            $paymentReference,
            $useWallet,
            $promoCode,
            $groupMemberCount,
            $affiliateCode,
            $mobileNumber ?: $customer->phone,
        );
    }

    /**
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}
     */
    public function processMobileMoney(
        Customer $customer,
        LoanProduct $product,
        string $paymentReference,
        bool $useWallet = false,
        ?string $promoCode = null,
        ?int $groupMemberCount = null,
        ?string $affiliateCode = null,
        ?string $mobileNumber = null,
    ): array {
        if ($settled = $this->alreadySettledFeeState($customer, $product)) {
            return $settled;
        }

        if ($resume = $this->resumeOutstandingFeeState($customer, $product)) {
            return $resume;
        }

        $quote = $this->quote($customer, $product, $useWallet, $promoCode, $groupMemberCount, $affiliateCode);
        $cashDue = (int) ($quote['cash_due'] ?? $quote['after_discount']);

        if ($cashDue <= 0) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);
            app(LoanApplicationDraftService::class)->saveApplicationFee($customer, $product->id, [
                'status' => 'waived',
                'reference' => null,
                'channel' => 'waived',
                'amount' => 0,
                'paid_at' => now()->toIso8601String(),
            ]);
            app(LoanApplicationDraftService::class)->advancePastApplicationFee($customer, $product->id);

            return [
                'status' => 'waived',
                'reference' => null,
                'channel' => 'waived',
                'amount' => 0,
                'paid_at' => now()->toIso8601String(),
            ];
        }

        $payIn = app(PayInService::class);
        $payInLive = $payIn->isLiveCollectionEnabled();
        $dummyGateway = $this->usesDummyGateway();
        $stagingPayments = app(\App\Services\Staging\StagingPaymentsService::class);
        $phone = $mobileNumber ?: $customer->phone;
        $awaitsPsp = $payIn->isConfigured() || $payInLive || ! $dummyGateway || $stagingPayments->shouldAwaitProvider();

        if (! $dummyGateway && ! $payInLive && ! $stagingPayments->isSimulator()) {
            throw ValidationException::withMessages([
                'payment_method' => [__('borrower.payments.aggregator_required')],
            ]);
        }

        // Instant dummy (no aggregator): settle now. Otherwise settle on verify.
        if (! $awaitsPsp) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);
        }

        [$effectivePromo, $effectiveAffiliate] = $this->resolvePromoOrAffiliate($promoCode, $affiliateCode, $customer);

        $draft = $this->ensureDraftForFee($customer, $product);
        $draftPayload = is_array($draft?->payload) ? $draft->payload : null;
        $nextStep = $this->nextStepAfterApplicationFee($customer, $product, $draftPayload);
        $groups = app(GroupLendingService::class);
        $resolvedMemberCount = $groups->isGroupProduct($product)
            ? max(1, (int) ($groupMemberCount ?: $groups->memberCountFromPayload(is_array($draftPayload['group'] ?? null) ? $draftPayload['group'] : null)))
            : null;

        $applyContext = [
            'loan_product_id' => $product->id,
            'draft_reference' => $draft?->draft_reference,
            'use_wallet' => $useWallet,
            'promo_code' => $effectivePromo,
            'affiliate_code' => $effectiveAffiliate,
            'group_member_count' => $resolvedMemberCount,
            'group_fee_breakdown' => $resolvedMemberCount
                ? $groups->applicationFeeBreakdown($customer, $product, $resolvedMemberCount)
                : null,
            'next_step_key' => $nextStep,
            'return_url' => $this->resumeUrlAfterFee($customer, $product, $draftPayload, $nextStep),
            'back_url' => $this->quoteResumeUrl($customer, $product),
            'gross_amount' => (float) ($quote['base'] ?? $cashDue),
            'settled' => ! $awaitsPsp,
        ];

        $existingQuery = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'application_fee')
            ->where('loan_product_id', $product->id)
            ->whereIn('status', ['awaiting_payment', 'processing', 'pending_verification']);

        $draftRef = (string) ($draft?->draft_reference ?? '');
        if ($draftRef !== '') {
            $existingQuery->where('provider_meta->apply_context->draft_reference', $draftRef);
        } else {
            // Never inherit unbound orphan fees onto a fresh obligation.
            $existingQuery->whereRaw('1 = 0');
        }

        $existing = $existingQuery->latest('id')->first();

        if ($existing) {
            $meta = $existing->provider_meta ?? [];
            $meta['apply_context'] = $applyContext;
            $existing->update([
                'amount' => $cashDue,
                'mobile_number' => $phone ?: $existing->mobile_number,
                'provider_meta' => $meta,
            ]);

            if ($existing->isPayInWaiting() || $existing->status === 'processing') {
                try {
                    $existing = app(CustomerPaymentService::class)->returnToPaymentGate($existing);
                } catch (\Throwable) {
                    // Keep current state and still open the gate.
                }
            }

            return $this->feeStateFromPayment($existing->fresh(), $cashDue, 'mobile_money');
        }

        $payment = app(CustomerPaymentService::class)->create([
            'customer' => $customer,
            'payment_type' => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount' => $cashDue,
            'loan_product' => $product,
            'reference' => $paymentReference,
            'mobile_number' => $phone,
            'auto_verify' => ! $awaitsPsp,
            'apply_context' => $applyContext,
        ]);

        return $this->feeStateFromPayment($payment, $cashDue, 'mobile_money');
    }

    /**
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}
     */
    public function processBankPending(
        Customer $customer,
        LoanProduct $product,
        string $paymentReference,
        bool $useWallet = false,
        ?string $promoCode = null,
        ?int $groupMemberCount = null,
        ?string $affiliateCode = null,
    ): array {
        if ($settled = $this->alreadySettledFeeState($customer, $product)) {
            return $settled;
        }

        if ($resume = $this->resumeOutstandingFeeState($customer, $product)) {
            return $resume;
        }

        $quote = $this->quote($customer, $product, $useWallet, $promoCode, $groupMemberCount, $affiliateCode);
        $cashDue = (int) ($quote['cash_due'] ?? $quote['after_discount']);

        if ($cashDue <= 0) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);

            return [
                'status' => 'waived',
                'reference' => $paymentReference,
                'channel' => 'bank',
                'amount' => 0,
                'paid_at' => now()->toIso8601String(),
            ];
        }

        // Bank always needs verification unless dummy gateway (sandbox instant).
        $autoVerify = $this->usesDummyGateway()
            && ! app(PayInService::class)->isConfigured()
            && ! app(\App\Services\Staging\StagingPaymentsService::class)->shouldAwaitProvider();

        if ($autoVerify) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);
        }

        [$effectivePromo, $effectiveAffiliate] = $this->resolvePromoOrAffiliate($promoCode, $affiliateCode, $customer);

        $draft = $this->ensureDraftForFee($customer, $product);
        $draftPayload = is_array($draft?->payload) ? $draft->payload : null;
        $nextStep = $this->nextStepAfterApplicationFee($customer, $product, $draftPayload);
        $groups = app(GroupLendingService::class);
        $resolvedMemberCount = $groups->isGroupProduct($product)
            ? max(1, (int) ($groupMemberCount ?: $groups->memberCountFromPayload(is_array($draftPayload['group'] ?? null) ? $draftPayload['group'] : null)))
            : null;

        $payment = app(CustomerPaymentService::class)->create([
            'customer' => $customer,
            'payment_type' => 'application_fee',
            'payment_method' => 'bank_transfer',
            'amount' => $cashDue,
            'loan_product' => $product,
            'reference' => $paymentReference,
            'auto_verify' => $autoVerify,
            'apply_context' => [
                'loan_product_id' => $product->id,
                'draft_reference' => $draft?->draft_reference,
                'use_wallet' => $useWallet,
                'promo_code' => $effectivePromo,
                'affiliate_code' => $effectiveAffiliate,
                'group_member_count' => $resolvedMemberCount,
                'group_fee_breakdown' => $resolvedMemberCount
                    ? $groups->applicationFeeBreakdown($customer, $product, $resolvedMemberCount)
                    : null,
                'next_step_key' => $nextStep,
                'return_url' => $this->resumeUrlAfterFee($customer, $product, $draftPayload, $nextStep),
                'back_url' => $this->quoteResumeUrl($customer, $product),
                'gross_amount' => (float) ($quote['base'] ?? $cashDue),
                'settled' => $autoVerify,
            ],
        ]);

        return $this->feeStateFromPayment($payment, $cashDue, 'bank');
    }

    /**
     * Sync draft fee from a verified CustomerPayment (e.g. after returning from payments.show).
     */
    public function syncDraftFromVerifiedPayment(Customer $customer, LoanProduct $product): ?array
    {
        $drafts = app(LoanApplicationDraftService::class);
        $draft = $drafts->find($customer, $product->id);
        $payload = is_array($draft?->payload) ? $draft->payload : [];
        if ($draft?->draft_reference) {
            $payload['draft_reference'] = $draft->draft_reference;
        }
        $payment = $this->latestFeePayment($customer, $product, null, $payload);

        if (! $payment || ! in_array($payment->status, ['paid', 'verified'], true)) {
            return null;
        }

        $feeState = [
            'status' => 'paid',
            'reference' => $payment->reference,
            'payment_id' => $payment->id,
            'channel' => $payment->payment_method === 'mobile_money' ? 'mobile_money' : 'bank',
            'amount' => (int) round((float) $payment->amount),
            'paid_at' => ($payment->paid_at ?? now())->toIso8601String(),
        ];

        $drafts->saveApplicationFee($customer, $product->id, $feeState);
        if (product_includes_valuation_fee($product)) {
            $drafts->saveValuationFee($customer, $product->id, $feeState);
        }

        $drafts->advancePastApplicationFee(
            $customer,
            $product->id,
            $this->nextStepAfterApplicationFee($customer, $product, $drafts->find($customer, $product->id)?->payload),
        );

        return $feeState;
    }

    public function isFeeSatisfied(?array $feeState, int $requiredAmount): bool
    {
        if ($requiredAmount <= 0) {
            return true;
        }

        if (! is_array($feeState)) {
            return false;
        }

        return in_array($feeState['status'] ?? '', ['paid', 'waived'], true);
    }

    /** Wizard may advance only after PSP / admin confirmation — not on pending bank alone. */
    public function isFeeRecordedForWizard(?array $feeState, int $requiredAmount): bool
    {
        return $this->isFeeSatisfied($feeState, $requiredAmount);
    }

    /**
     * Resume an unpaid application-fee payment instead of minting a second reference.
     *
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}|null
     */
    private function resumeOutstandingFeeState(Customer $customer, LoanProduct $product): ?array
    {
        $draft = app(LoanApplicationDraftService::class)->find($customer, $product->id);
        $draftPayload = is_array($draft?->payload) ? $draft->payload : [];
        if ($draft?->draft_reference) {
            $draftPayload['draft_reference'] = $draft->draft_reference;
        }
        $payment = $this->obligation($customer, $product, $draftPayload)['payment'] ?? null;
        if (! $payment || ! in_array($payment->status, ['awaiting_payment', 'processing', 'pending_verification'], true)) {
            return null;
        }

        $cashDue = $this->canonicalOpenPaymentAmount($customer, $product, $payment);

        return $this->feeStateFromPayment(
            $payment->fresh(),
            $cashDue,
            $payment->payment_method === 'bank_transfer' ? 'bank' : 'mobile_money',
        );
    }

    /**
     * Every fee obligation must be anchored to a draft_reference before payment rows are created.
     */
    private function ensureDraftForFee(Customer $customer, LoanProduct $product): ?\App\Models\LoanApplicationDraft
    {
        $drafts = app(LoanApplicationDraftService::class);
        $draft = $drafts->find($customer, $product->id);
        if (! $draft) {
            $draft = $drafts->save($customer, [
                'phase' => 'application',
                'step' => 0,
                'step_key' => 'quote',
                'loan_product_id' => $product->id,
                'form' => ['loan_product_id' => $product->id],
                'application_started' => true,
            ]);
        }
        if ($draft && blank($draft->draft_reference)) {
            $draft->draft_reference = app(ReferenceNumberService::class)->applicationReference($product);
            $draft->save();
        }

        return $draft?->fresh();
    }

    /**
     * Open obligations keep their reference, but never inherit a silent discount.
     * Refresh to the canonical fee unless the borrower explicitly applied a benefit on this payment.
     */
    private function canonicalOpenPaymentAmount(Customer $customer, LoanProduct $product, CustomerPayment $payment): int
    {
        $hasExplicitBenefit = (float) data_get($payment->provider_meta, 'pricing.promo_discount', 0) > 0
            || (float) data_get($payment->provider_meta, 'pricing.loyalty_discount', 0) > 0
            || (float) data_get($payment->provider_meta, 'pricing.wallet_applied', 0) > 0
            || (bool) data_get($payment->provider_meta, 'pricing.apply_reward', false)
            || filled(data_get($payment->provider_meta, 'pricing.promo_code'));

        if ($hasExplicitBenefit) {
            return (int) round((float) $payment->amount);
        }

        $canonical = (int) round((float) ($this->quote($customer, $product)['cash_due'] ?? 0));
        if ($canonical <= 0) {
            return (int) round((float) $payment->amount);
        }

        if ((int) round((float) $payment->amount) !== $canonical) {
            $meta = is_array($payment->provider_meta) ? $payment->provider_meta : [];
            data_set($meta, 'pricing.gross', $canonical);
            data_set($meta, 'pricing.cash_due', $canonical);
            data_set($meta, 'pricing.affiliate_discount', 0);
            data_set($meta, 'pricing.promo_discount', 0);
            data_set($meta, 'apply_context.gross_amount', $canonical);
            $payment->update([
                'amount' => $canonical,
                'provider_meta' => $meta,
            ]);
        }

        return $canonical;
    }

    /**
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}|null
     */
    private function alreadySettledFeeState(Customer $customer, LoanProduct $product): ?array
    {
        $draft = app(LoanApplicationDraftService::class)->find($customer, $product->id);
        $draftPayload = is_array($draft?->payload) ? $draft->payload : [];
        if ($draft?->draft_reference) {
            $draftPayload['draft_reference'] = $draft->draft_reference;
        }
        $obligation = $this->obligation($customer, $product, $draftPayload);

        if ($obligation['status'] === 'not_applicable') {
            return [
                'status' => 'waived',
                'reference' => null,
                'channel' => 'waived',
                'amount' => 0,
                'paid_at' => now()->toIso8601String(),
            ];
        }

        if ($obligation['status'] !== 'paid') {
            return null;
        }

        $this->syncDraftFromVerifiedPayment($customer, $product);
        $payment = $obligation['payment'];

        return [
            'status' => 'paid',
            'reference' => $payment?->reference,
            'payment_id' => $payment?->id,
            'channel' => $payment?->payment_method === 'mobile_money' ? 'mobile_money' : 'bank',
            'amount' => (int) ($obligation['amount'] ?? 0),
            'paid_at' => optional($payment?->paid_at ?? now())->toIso8601String(),
        ];
    }

    /**
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}
     */
    private function feeStateFromPayment(CustomerPayment $payment, int $cashDue, string $channel): array
    {
        $pending = in_array($payment->status, ['awaiting_payment', 'processing', 'pending_verification'], true);
        $failed = in_array($payment->status, ['failed', 'expired', 'cancelled'], true);
        $settled = in_array($payment->status, ['paid', 'verified'], true);
        $isBank = $channel === 'bank';

        $status = match (true) {
            $pending => $isBank ? 'pending' : 'processing',
            $failed => 'failed',
            $settled => 'paid',
            default => 'failed',
        };

        return [
            'status' => $status,
            'reference' => $payment->reference,
            'payment_id' => $payment->id,
            'channel' => $this->usesDummyGateway()
                ? ($isBank ? 'dummy_bank' : 'dummy_mobile_money')
                : ($isBank ? 'bank' : 'mobile_money'),
            'amount' => $cashDue,
            'paid_at' => $settled ? optional($payment->paid_at ?? now())->toIso8601String() : null,
            // Always hand off to the shared payments.show gate.
            'wait_url' => route('site.borrower.payments.show', $payment),
        ];
    }
}
