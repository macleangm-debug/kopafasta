<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;

class PostApprovalFeePaymentService
{
    public function usesDummyGateway(): bool
    {
        return payment_gateway_is_dummy();
    }

    public function generatePaymentReference(LoanApplication $application): string
    {
        $applicationNumber = $application->application_number;

        if ($applicationNumber) {
            $suffix = 1;
            do {
                $candidate = $suffix === 1
                    ? $applicationNumber.'-PAF'
                    : $applicationNumber.'-PAF-'.$suffix;
                $suffix++;
            } while (CustomerPayment::where('reference', $candidate)->exists());

            return $candidate;
        }

        return app(CustomerPaymentService::class)->generateReference();
    }

    public function existingPayment(LoanApplication $application): ?CustomerPayment
    {
        $payment = CustomerPayment::query()
            ->where('payment_type', 'post_approval_fee')
            ->where('source_type', LoanApplication::class)
            ->where('source_id', $application->id)
            ->whereIn('status', ['pending_verification', 'paid', 'verified'])
            ->latest('id')
            ->first();

        if ($payment?->isVerified()) {
            $this->reconcileVerifiedPayment($application);
        }

        return $payment;
    }

    /** Mark fee rows paid when a verified payment exists but fees were not updated. */
    public function reconcileVerifiedPayment(LoanApplication $application): void
    {
        if (app(PostApprovalFeeService::class)->allPaid($application)) {
            app(LoanAgreementService::class)->ensureLoanContractAfterFees($application->fresh());

            return;
        }

        $payment = CustomerPayment::query()
            ->where('payment_type', 'post_approval_fee')
            ->where('source_type', LoanApplication::class)
            ->where('source_id', $application->id)
            ->whereIn('status', ['paid', 'verified'])
            ->latest('id')
            ->first();

        if ($payment) {
            app(PostApprovalFeeService::class)->markAllPaid($application->fresh(), $payment->customer);
        }
    }

    /** @return array<string, mixed> */
    public function quote(Customer $customer, LoanApplication $application, bool $useWallet = false): array
    {
        $application->loadMissing('postApprovalFees', 'product');
        $base = (float) $application->postApprovalFees
            ->where('status', '!=', 'paid')
            ->sum('calculated_amount');
        $cfg = MembershipService::config();

        if ($base <= 0) {
            return [
                'base'           => 0,
                'after_discount' => 0,
                'discount'       => 0,
                'wallet_applied' => 0,
                'cash_due'       => 0,
                'wallet_usable'  => false,
                'currency'       => $cfg['currency'],
            ];
        }

        $referrals = app(ReferralService::class);

        if ($referrals->referrer($customer)) {
            $quote = $referrals->quoteFee($customer, $base, $useWallet, 'post_approval_fee', LoanApplication::class, (int) $application->id);

            return array_merge($quote, [
                'currency'      => $cfg['currency'],
                'wallet_usable' => $referrals->canUseWalletFor('post_approval_fee'),
            ]);
        }

        $affiliateQuote = app(AffiliateService::class)->quoteFee($customer, $base, 'post_approval_fee');
        $walletQuote = $referrals->quoteFee($customer, $affiliateQuote['after_discount'], $useWallet, 'post_approval_fee', applyDiscount: false);

        return array_merge($affiliateQuote, [
            'wallet_usable'  => $walletQuote['wallet_usable'],
            'wallet_applied' => $walletQuote['wallet_applied'],
            'cash_due'       => max(0, round($affiliateQuote['after_discount'] - $walletQuote['wallet_applied'], 2)),
            'currency'       => $cfg['currency'],
        ]);
    }

    /**
     * @return array{payment: CustomerPayment, quote: array<string, mixed>}
     */
    public function processMobileMoney(
        Customer $customer,
        LoanApplication $application,
        string $paymentReference,
        bool $useWallet = false,
        ?string $mobileNumber = null,
    ): array {
        $quote = $this->quote($customer, $application, $useWallet);
        $amount = (int) $quote['after_discount'];

        if ($amount <= 0) {
            app(PostApprovalFeeService::class)->markAllPaid($application, $customer, $useWallet);

            return [
                'payment' => null,
                'quote'   => $quote,
            ];
        }

        if ($existing = $this->existingPayment($application)) {
            return ['payment' => $existing, 'quote' => $quote];
        }

        $this->settleDiscounts($customer, $application, $quote, $useWallet);

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'post_approval_fee',
            'payment_method' => 'mobile_money',
            'amount'         => $amount,
            'loan_product'   => $application->product,
            'reference'      => $paymentReference,
            'source'         => $application,
            'mobile_number'  => $mobileNumber,
            'auto_verify'    => true,
        ]);

        return ['payment' => $payment, 'quote' => $quote];
    }

    /**
     * @return array{payment: CustomerPayment, quote: array<string, mixed>}
     */
    public function processBankPending(
        Customer $customer,
        LoanApplication $application,
        string $paymentReference,
        bool $useWallet = false,
        ?string $paymentDate = null,
    ): array {
        $quote = $this->quote($customer, $application, $useWallet);
        $amount = (int) $quote['after_discount'];

        if ($amount <= 0) {
            app(PostApprovalFeeService::class)->markAllPaid($application, $customer, $useWallet);

            return [
                'payment' => null,
                'quote'   => $quote,
            ];
        }

        if ($existing = $this->existingPayment($application)) {
            return ['payment' => $existing, 'quote' => $quote];
        }

        $this->settleDiscounts($customer, $application, $quote, $useWallet);

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'post_approval_fee',
            'payment_method' => 'bank_transfer',
            'amount'         => $amount,
            'loan_product'   => $application->product,
            'reference'      => $paymentReference,
            'source'         => $application,
            'payment_date'   => $paymentDate,
            'auto_verify'    => $this->usesDummyGateway(),
        ]);

        return ['payment' => $payment, 'quote' => $quote];
    }

    /** @param array<string, mixed> $quote */
    private function settleDiscounts(Customer $customer, LoanApplication $application, array $quote, bool $useWallet): void
    {
        $base = (float) ($quote['base'] ?? 0);
        $referrals = app(ReferralService::class);

        if ($referrals->referrer($customer)) {
            $referrals->settleFee($customer, $base, $useWallet, 'post_approval_fee', LoanApplication::class, (int) $application->id);

            return;
        }

        app(AffiliateService::class)->accrueCommission(
            $customer,
            $base,
            'post_approval_fee',
            LoanApplication::class,
            (int) $application->id,
        );

        if ($useWallet && $referrals->canUseWalletFor('post_approval_fee')) {
            $walletApplied = (float) ($quote['wallet_applied'] ?? 0);
            if ($walletApplied > 0) {
                $referrals->debit(
                    $customer,
                    $walletApplied,
                    'Applied to post-approval fee',
                    LoanApplication::class,
                    (int) $application->id,
                );
            }
        }
    }
}
