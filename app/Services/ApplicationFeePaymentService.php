<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
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
        if ($groups->isGroupProduct($product) && $groupMemberCount) {
            $base = (float) $groups->quotedApplicationFee($customer, $product, $groupMemberCount);
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
     * @return array{status: string, reference: string, channel: string, amount: int, paid_at: string|null}
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

            return [
                'status'    => 'waived',
                'reference' => null,
                'channel'   => 'waived',
                'amount'    => 0,
                'paid_at'   => now()->toIso8601String(),
            ];
        }

        $payInLive = app(\App\Services\PayInService::class)->isLiveCollectionEnabled();
        $phone = $mobileNumber ?: $customer->phone;

        if ($payInLive && ! filled($phone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mobile_number' => ['Enter the mobile money number that will confirm the payment.'],
            ]);
        }

        // Don't settle wallet/promo until PayIn confirms — only settle when not awaiting USSD.
        if (! $payInLive) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);
        }

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount'         => $cashDue,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'mobile_number'  => $phone,
            'auto_verify'    => ! $payInLive,
        ]);

        $pending = in_array($payment->status, ['processing', 'pending_verification'], true);

        return [
            'status'     => $pending ? 'processing' : 'paid',
            'reference'  => $payment->reference,
            'payment_id' => $payment->id,
            'channel'    => $this->usesDummyGateway() ? 'dummy_mobile_money' : 'mobile_money',
            'amount'     => $cashDue,
            'paid_at'    => $pending ? null : now()->toIso8601String(),
            'wait_url'   => $pending
                ? route('site.borrower.payments.show', $payment)
                : null,
        ];
    }

    /** @return array{status: string, reference: string, channel: string, amount: int, paid_at: string|null} */
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

        $autoVerify = $this->usesDummyGateway();

        if ($autoVerify) {
            app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);
        }

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'bank_transfer',
            'amount'         => $cashDue,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'auto_verify'    => $autoVerify,
        ]);

        return [
            'status'    => $autoVerify ? 'paid' : 'pending',
            'reference' => $payment->reference,
            'channel'   => $autoVerify ? 'dummy_bank' : 'bank',
            'amount'    => $cashDue,
            'paid_at'   => $autoVerify ? now()->toIso8601String() : null,
        ];
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

    /** Wizard may advance after bank transfer is submitted (pending verification). */
    public function isFeeRecordedForWizard(?array $feeState, int $requiredAmount): bool
    {
        if ($requiredAmount <= 0) {
            return true;
        }

        if (! is_array($feeState)) {
            return false;
        }

        return in_array($feeState['status'] ?? '', ['paid', 'waived', 'pending'], true);
    }
}
