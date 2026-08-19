<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
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
    public function quote(Customer $customer, int $assetCount = 1): array
    {
        $base = (float) quoted_valuation_fee($customer, $assetCount);
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

        if ($payInLive && ! filled($phone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mobile_number' => [__('borrower.payments.mobile_number_required')],
            ]);
        }

        // Instant dummy only — live / configured PayIn settles on verify.
        if (! $awaitsPsp) {
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
            'auto_verify'    => ! $awaitsPsp,
            'apply_context'  => [
                'loan_product_id' => $product->id,
                'use_wallet' => $useWallet,
                'return_url' => route('site.borrower.apply', [
                    'product' => $product->id,
                    'resume' => 1,
                    'step_key' => 'valuation_fee',
                ]),
                'settled' => ! $awaitsPsp,
            ],
        ]);

        return $this->feeStateFromPayment($payment, $amount, 'mobile_money');
    }

    /** @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null} */
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

        $autoVerify = $this->usesDummyGateway() && ! app(PayInService::class)->isConfigured();

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'valuation_fee',
            'payment_method' => 'bank_transfer',
            'amount'         => $amount,
            'loan_product'   => $product,
            'reference'      => $paymentReference,
            'auto_verify'    => $autoVerify,
            'apply_context'  => [
                'loan_product_id' => $product->id,
                'use_wallet' => false,
                'return_url' => route('site.borrower.apply', [
                    'product' => $product->id,
                    'resume' => 1,
                    'step_key' => 'valuation_fee',
                ]),
                'settled' => $autoVerify,
            ],
        ]);

        return $this->feeStateFromPayment($payment, $amount, 'bank');
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
        return $this->isFeeSatisfied($feeState, $requiredAmount);
    }

    /**
     * @return array{status: string, reference: string|null, channel: string, amount: int, paid_at: string|null, payment_id?: int, wait_url?: string|null}
     */
    private function feeStateFromPayment(CustomerPayment $payment, int $amount, string $channel): array
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
            'amount'     => $amount,
            'paid_at'    => $pending ? null : now()->toIso8601String(),
            'wait_url'   => $pending
                ? route('site.borrower.payments.show', $payment)
                : null,
        ];
    }
}
