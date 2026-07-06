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
        bool $useStreak = false,
    ): array {
        $groups = app(GroupLendingService::class);
        if ($groups->isGroupProduct($product) && $groupMemberCount) {
            $base = (float) $groups->quotedApplicationFee($customer, $product, $groupMemberCount);
        } else {
            $base = (float) quoted_origination_fee($customer, $product);
        }
        $cfg = MembershipService::config();

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
            $promoCode,
            null,
            $useStreak,
        );
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
        bool $useStreak = false,
    ): array {
        $quote = $this->quote($customer, $product, $useWallet, $promoCode, $groupMemberCount, $useStreak);
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

        app(PaymentGateService::class)->settle($customer, $quote, 'application_fee', null, null, $useWallet);

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount'         => $cashDue,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'auto_verify'    => true,
        ]);

        return [
            'status'    => 'paid',
            'reference' => $payment->reference,
            'channel'   => $this->usesDummyGateway() ? 'dummy_mobile_money' : 'mobile_money',
            'amount'    => $cashDue,
            'paid_at'   => now()->toIso8601String(),
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
        bool $useStreak = false,
    ): array {
        $quote = $this->quote($customer, $product, $useWallet, $promoCode, $groupMemberCount, $useStreak);
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
