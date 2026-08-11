<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanProduct;

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
            $base = (float) quoted_origination_fee($customer, $product);
        }
        $cfg = MembershipService::config();

        [$effectivePromo, $effectiveAffiliate] = $this->resolvePromoOrAffiliate($promoCode, $affiliateCode);

        if ($base <= 0) {
            return [
                'base'             => 0,
                'after_discount'   => 0,
                'discount'         => 0,
                'total_discount'   => 0,
                'wallet_applied'   => 0,
                'cash_due'         => 0,
                'wallet_usable'    => 0,
                'wallet_allowed'   => false,
                'has_referrer'     => false,
                'currency'         => $cfg['currency'],
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

    /**
     * @return array{0: ?string, 1: ?string} [promoCode, affiliateCode]
     */
    public function resolvePromoOrAffiliate(?string $promoCode, ?string $affiliateCode = null): array
    {
        $code = filled($affiliateCode) ? $affiliateCode : $promoCode;
        if (blank($code)) {
            return [null, null];
        }

        $code = strtoupper(trim((string) $code));

        if (app(AffiliateService::class)->findByCode($code)) {
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
                'status'    => 'waived',
                'reference' => null,
                'channel'   => 'waived',
                'amount'    => 0,
                'paid_at'   => now()->toIso8601String(),
            ];
        }

        $payIn = app(PayInService::class);
        $payInLive = $payIn->isLiveCollectionEnabled();
        $dummyGateway = $this->usesDummyGateway();
        $phone = $mobileNumber ?: $customer->phone;
        $awaitsPsp = $payIn->isConfigured() || $payInLive || ! $dummyGateway;

        if (! $dummyGateway && ! $payInLive) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_method' => [__('borrower.payments.aggregator_required')],
            ]);
        }

        // Instant dummy (no aggregator): settle now. Otherwise settle on verify.
        if (! $awaitsPsp) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);
        }

        [$effectivePromo, $effectiveAffiliate] = $this->resolvePromoOrAffiliate($promoCode, $affiliateCode);

        $draftPayload = app(LoanApplicationDraftService::class)->find($customer, $product->id)?->payload;
        $nextStep = $this->nextStepAfterApplicationFee($customer, $product, is_array($draftPayload) ? $draftPayload : null);
        $groups = app(GroupLendingService::class);
        $resolvedMemberCount = $groups->isGroupProduct($product)
            ? max(1, (int) ($groupMemberCount ?: $groups->memberCountFromPayload(is_array($draftPayload) ? ($draftPayload['group'] ?? null) : null)))
            : null;

        $applyContext = [
            'loan_product_id' => $product->id,
            'use_wallet' => $useWallet,
            'promo_code' => $effectivePromo,
            'affiliate_code' => $effectiveAffiliate,
            'group_member_count' => $resolvedMemberCount,
            'group_fee_breakdown' => $resolvedMemberCount
                ? $groups->applicationFeeBreakdown($customer, $product, $resolvedMemberCount)
                : null,
            'next_step_key' => $nextStep,
            'return_url' => $this->resumeUrlAfterFee($customer, $product, is_array($draftPayload) ? $draftPayload : null, $nextStep),
            'settled' => ! $awaitsPsp,
        ];

        $existing = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'application_fee')
            ->where('loan_product_id', $product->id)
            ->whereIn('status', ['awaiting_payment', 'processing', 'pending_verification'])
            ->latest('id')
            ->first();

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
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount'         => $cashDue,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'mobile_number'  => $phone,
            'auto_verify'    => ! $awaitsPsp,
            'apply_context'  => $applyContext,
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
        $quote = $this->quote($customer, $product, $useWallet, $promoCode, $groupMemberCount, $affiliateCode);
        $cashDue = (int) ($quote['cash_due'] ?? $quote['after_discount']);

        if ($cashDue <= 0) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);

            return [
                'status'    => 'waived',
                'reference' => $paymentReference,
                'channel'   => 'bank',
                'amount'    => 0,
                'paid_at'   => now()->toIso8601String(),
            ];
        }

        // Bank always needs verification unless dummy gateway (sandbox instant).
        $autoVerify = $this->usesDummyGateway() && ! app(PayInService::class)->isConfigured();

        if ($autoVerify) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);
        }

        [$effectivePromo, $effectiveAffiliate] = $this->resolvePromoOrAffiliate($promoCode, $affiliateCode);

        $draftPayload = app(LoanApplicationDraftService::class)->find($customer, $product->id)?->payload;
        $nextStep = $this->nextStepAfterApplicationFee($customer, $product, is_array($draftPayload) ? $draftPayload : null);
        $groups = app(GroupLendingService::class);
        $resolvedMemberCount = $groups->isGroupProduct($product)
            ? max(1, (int) ($groupMemberCount ?: $groups->memberCountFromPayload(is_array($draftPayload) ? ($draftPayload['group'] ?? null) : null)))
            : null;

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'bank_transfer',
            'amount'         => $cashDue,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'auto_verify'    => $autoVerify,
            'apply_context'  => [
                'loan_product_id' => $product->id,
                'use_wallet' => $useWallet,
                'promo_code' => $effectivePromo,
                'affiliate_code' => $effectiveAffiliate,
                'group_member_count' => $resolvedMemberCount,
                'group_fee_breakdown' => $resolvedMemberCount
                    ? $groups->applicationFeeBreakdown($customer, $product, $resolvedMemberCount)
                    : null,
                'next_step_key' => $nextStep,
                'return_url' => $this->resumeUrlAfterFee($customer, $product, is_array($draftPayload) ? $draftPayload : null, $nextStep),
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
        $payment = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'application_fee')
            ->where(function ($q) use ($product) {
                $q->where('loan_product_id', $product->id)
                    ->orWhere('provider_meta->apply_context->loan_product_id', $product->id);
            })
            ->whereIn('status', ['paid', 'verified'])
            ->latest('id')
            ->first();

        if (! $payment) {
            return null;
        }

        $feeState = [
            'status'     => 'paid',
            'reference'  => $payment->reference,
            'payment_id' => $payment->id,
            'channel'    => $payment->payment_method === 'mobile_money' ? 'mobile_money' : 'bank',
            'amount'     => (int) round((float) $payment->amount),
            'paid_at'    => ($payment->paid_at ?? now())->toIso8601String(),
        ];

        $drafts = app(LoanApplicationDraftService::class);
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
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}
     */
    private function feeStateFromPayment(CustomerPayment $payment, int $cashDue, string $channel): array
    {
        $pending = in_array($payment->status, ['awaiting_payment', 'processing', 'pending_verification'], true);
        $isBank = $channel === 'bank';

        return [
            'status'     => $pending ? ($isBank ? 'pending' : 'processing') : 'paid',
            'reference'  => $payment->reference,
            'payment_id' => $payment->id,
            'channel'    => $this->usesDummyGateway()
                ? ($isBank ? 'dummy_bank' : 'dummy_mobile_money')
                : ($isBank ? 'bank' : 'mobile_money'),
            'amount'     => $cashDue,
            'paid_at'    => $pending ? null : now()->toIso8601String(),
            // Always hand off to the shared payments.show gate.
            'wait_url'   => route('site.borrower.payments.show', $payment),
        ];
    }
}
