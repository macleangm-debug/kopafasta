<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanProduct;

class ValuationFeePaymentService
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
    public function quote(Customer $customer): array
    {
        $base = (float) quoted_valuation_fee($customer);
        $cfg = MembershipService::config();

        if ($base <= 0) {
            return [
                'base'           => 0,
                'after_discount' => 0,
                'discount'       => 0,
                'wallet_applied' => 0,
                'cash_due'       => 0,
                'wallet_usable'  => false,
                'has_referrer'   => false,
                'currency'       => $cfg['currency'],
            ];
        }

        $referrals = app(ReferralService::class);
        if ($referrals->referrer($customer)) {
            $quote = $referrals->quoteFee($customer, $base, false, 'valuation_fee');

            return array_merge($quote, [
                'currency'      => $cfg['currency'],
                'wallet_usable' => $referrals->canUseWalletFor('valuation_fee'),
            ]);
        }

        $affiliateQuote = app(AffiliateService::class)->quoteFee($customer, $base, 'valuation_fee');
        $walletQuote = $referrals->quoteFee($customer, $affiliateQuote['after_discount'], false, 'valuation_fee', applyDiscount: false);

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
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}
     */
    public function processMobileMoney(
        Customer $customer,
        LoanProduct $product,
        string $paymentReference,
        bool $useWallet = false,
        ?string $mobileNumber = null,
    ): array {
        $quote = $this->quote($customer);
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

        $payInLive = app(\App\Services\PayInService::class)->isLiveCollectionEnabled();
        $dummyGateway = $this->usesDummyGateway();
        $phone = $mobileNumber ?: $customer->phone;

        if (! $dummyGateway && ! $payInLive) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_method' => [__('borrower.payments.aggregator_required')],
            ]);
        }

        if ($payInLive && ! filled($phone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mobile_number' => [__('borrower.payments.mobile_number_required')],
            ]);
        }

        // Settle discounts only in dummy instant mode — live waits for aggregator confirmation.
        if ($dummyGateway && ! $payInLive) {
            $referrals = app(ReferralService::class);
            if ($referrals->referrer($customer)) {
                $referrals->settleFee($customer, (float) $quote['base'], $useWallet, 'valuation_fee');
            } else {
                if ($useWallet && $referrals->canUseWalletFor('valuation_fee')) {
                    $walletQuote = $referrals->quoteFee($customer, $quote['after_discount'], true, 'valuation_fee', applyDiscount: false);
                    if ($walletQuote['wallet_applied'] > 0) {
                        $referrals->debit($customer, $walletQuote['wallet_applied'], 'Applied to valuation fee');
                    }
                }

                app(AffiliateService::class)->accrueCommission(
                    $customer,
                    (float) $quote['base'],
                    'valuation_fee',
                );
            }
        }

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'valuation_fee',
            'payment_method' => 'mobile_money',
            'amount'         => $amount,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'mobile_number'  => $phone,
            'auto_verify'    => $dummyGateway && ! $payInLive,
        ]);

        $pending = in_array($payment->status, ['awaiting_payment', 'processing', 'pending_verification'], true);

        return [
            'status'     => $pending ? 'processing' : 'paid',
            'reference'  => $payment->reference,
            'payment_id' => $payment->id,
            'channel'    => $this->usesDummyGateway() ? 'dummy_mobile_money' : 'mobile_money',
            'amount'     => $amount,
            'paid_at'    => $pending ? null : now()->toIso8601String(),
            'wait_url'   => $pending ? route('site.borrower.payments.show', $payment) : null,
        ];
    }

    /** @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null} */
    public function processBankPending(Customer $customer, LoanProduct $product, string $paymentReference): array
    {
        $quote = $this->quote($customer);
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
            'payment_type'   => 'valuation_fee',
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
