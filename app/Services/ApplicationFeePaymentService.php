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
    public function quote(Customer $customer, LoanProduct $product): array
    {
        $base = (float) quoted_application_fee($customer, $product);
        $cfg = MembershipService::config();

        if ($base <= 0) {
            return [
                'base'             => 0,
                'after_discount'   => 0,
                'discount'         => 0,
                'wallet_applied'   => 0,
                'cash_due'         => 0,
                'wallet_usable'    => false,
                'has_referrer'     => false,
                'currency'         => $cfg['currency'],
            ];
        }

        $referrals = app(ReferralService::class);
        if ($referrals->referrer($customer)) {
            $quote = $referrals->quoteFee($customer, $base, false, 'application_fee');

            return array_merge($quote, [
                'currency'      => $cfg['currency'],
                'wallet_usable' => $referrals->canUseWalletFor('application_fee'),
            ]);
        }

        $affiliateQuote = app(AffiliateService::class)->quoteFee($customer, $base, 'application_fee');
        $walletQuote = $referrals->quoteFee($customer, $affiliateQuote['after_discount'], false, 'application_fee', applyDiscount: false);

        return array_merge($affiliateQuote, [
            'wallet_usable'  => $walletQuote['wallet_usable'],
            'wallet_applied' => $walletQuote['wallet_applied'],
            'cash_due'       => max(0, round($affiliateQuote['after_discount'] - $walletQuote['wallet_applied'], 2)),
            'has_referrer'   => false,
            'referrer'       => null,
            'currency'       => $cfg['currency'],
        ]);
    }

    /**
     * @return array{status: string, reference: string, channel: string, amount: int, paid_at: string|null}
     */
    public function processMobileMoney(
        Customer $customer,
        LoanProduct $product,
        string $paymentReference,
        bool $useWallet = false,
    ): array {
        $quote = $this->quote($customer, $product);
        $amount = (int) $quote['after_discount'];

        if ($amount <= 0) {
            return [
                'status'    => 'waived',
                'reference' => null,
                'channel'   => 'waived',
                'amount'    => 0,
                'paid_at'   => now()->toIso8601String(),
            ];
        }

        $referrals = app(ReferralService::class);
        if ($referrals->referrer($customer)) {
            $referrals->settleFee($customer, (float) $quote['base'], $useWallet, 'application_fee');
        } else {
            $walletApplied = 0.0;
            if ($useWallet && $referrals->canUseWalletFor('application_fee')) {
                $walletQuote = $referrals->quoteFee($customer, $quote['after_discount'], true, 'application_fee', applyDiscount: false);
                $walletApplied = $walletQuote['wallet_applied'];
                if ($walletApplied > 0) {
                    $referrals->debit($customer, $walletApplied, 'Applied to loan application fee');
                }
            }

            app(AffiliateService::class)->accrueCommission(
                $customer,
                (float) $quote['base'],
                'application_fee',
            );
        }

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount'         => $amount,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'auto_verify'    => true,
        ]);

        return [
            'status'    => 'paid',
            'reference' => $payment->reference,
            'channel'   => $this->usesDummyGateway() ? 'dummy_mobile_money' : 'mobile_money',
            'amount'    => $amount,
            'paid_at'   => now()->toIso8601String(),
        ];
    }

    /** @return array{status: string, reference: string, channel: string, amount: int, paid_at: string|null} */
    public function processBankPending(Customer $customer, LoanProduct $product, string $paymentReference): array
    {
        $quote = $this->quote($customer, $product);
        $amount = (int) $quote['after_discount'];

        if ($amount <= 0) {
            return [
                'status'    => 'waived',
                'reference' => $paymentReference,
                'channel'   => 'bank',
                'amount'    => 0,
                'paid_at'   => now()->toIso8601String(),
            ];
        }

        $autoVerify = $this->usesDummyGateway();

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'bank_transfer',
            'amount'         => $amount,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'auto_verify'    => $autoVerify,
        ]);

        return [
            'status'    => $autoVerify ? 'paid' : 'pending',
            'reference' => $payment->reference,
            'channel'   => $autoVerify ? 'dummy_bank' : 'bank',
            'amount'    => $amount,
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
