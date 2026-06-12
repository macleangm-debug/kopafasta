<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanProduct;

class ApplicationConversionFeePaymentService
{
    public function usesDummyGateway(): bool
    {
        return payment_gateway_is_dummy();
    }

    /** @return array<string, mixed> */
    public function quote(LoanApplication $application): array
    {
        $application->loadMissing(['customer', 'product', 'alternativeProduct']);
        $newProduct = $application->alternativeProduct;

        if (! $newProduct) {
            return ['due' => 0, 'after_discount' => 0, 'base' => 0, 'credit' => 0, 'currency' => 'TZS'];
        }

        $conversion = app(ApplicationFeeCreditService::class)->conversionQuote($application, $newProduct);
        $due = (int) $conversion['due'];
        $cfg = MembershipService::config();

        if ($due <= 0) {
            return array_merge($conversion, [
                'after_discount' => 0,
                'discount'       => 0,
                'wallet_applied' => 0,
                'cash_due'       => 0,
                'wallet_usable'  => false,
                'currency'       => $cfg['currency'],
            ]);
        }

        $customer = $application->customer;
        $referrals = app(ReferralService::class);

        if ($referrals->referrer($customer)) {
            $feeQuote = $referrals->quoteFee($customer, $due, false, 'application_fee', LoanApplication::class, (int) $application->id);

            return array_merge($conversion, $feeQuote, [
                'currency'      => $cfg['currency'],
                'wallet_usable' => $referrals->canUseWalletFor('application_fee'),
            ]);
        }

        $affiliateQuote = app(AffiliateService::class)->quoteFee($customer, $due, 'application_fee');
        $walletQuote = $referrals->quoteFee($customer, $affiliateQuote['after_discount'], false, 'application_fee', applyDiscount: false);

        return array_merge($conversion, $affiliateQuote, [
            'wallet_usable'  => $walletQuote['wallet_usable'],
            'wallet_applied' => $walletQuote['wallet_applied'],
            'cash_due'       => max(0, round($affiliateQuote['after_discount'] - $walletQuote['wallet_applied'], 2)),
            'currency'       => $cfg['currency'],
        ]);
    }

    /**
     * @return array{payment: CustomerPayment|null, quote: array<string, mixed>}
     */
    public function processMobileMoney(
        Customer $customer,
        LoanApplication $application,
        string $paymentReference,
        bool $useWallet = false,
    ): array {
        $quote = $this->quote($application);
        $amount = (int) ($quote['after_discount'] ?? $quote['due'] ?? 0);

        if ($amount <= 0) {
            app(ApplicationOfferService::class)->completeAssetConversion($application->fresh());

            return ['payment' => null, 'quote' => $quote];
        }

        $this->settleDiscounts($customer, $application, $quote, $useWallet);

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'mobile_money',
            'amount'         => $amount,
            'loan_product'   => $application->alternativeProduct,
            'reference'      => $paymentReference,
            'source'         => $application,
            'auto_verify'    => true,
        ]);

        return ['payment' => $payment, 'quote' => $quote];
    }

    /**
     * @return array{payment: CustomerPayment|null, quote: array<string, mixed>}
     */
    public function processBankPending(
        Customer $customer,
        LoanApplication $application,
        string $paymentReference,
        bool $useWallet = false,
    ): array {
        $quote = $this->quote($application);
        $amount = (int) ($quote['after_discount'] ?? $quote['due'] ?? 0);

        if ($amount <= 0) {
            app(ApplicationOfferService::class)->completeAssetConversion($application->fresh());

            return ['payment' => null, 'quote' => $quote];
        }

        $this->settleDiscounts($customer, $application, $quote, $useWallet);

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'application_fee',
            'payment_method' => 'bank_transfer',
            'amount'         => $amount,
            'loan_product'   => $application->alternativeProduct,
            'reference'      => $paymentReference,
            'source'         => $application,
            'auto_verify'    => $this->usesDummyGateway(),
        ]);

        return ['payment' => $payment, 'quote' => $quote];
    }

    /** @param array<string, mixed> $quote */
    private function settleDiscounts(Customer $customer, LoanApplication $application, array $quote, bool $useWallet): void
    {
        $base = (float) ($quote['due'] ?? $quote['base'] ?? 0);
        $referrals = app(ReferralService::class);

        if ($referrals->referrer($customer)) {
            $referrals->settleFee($customer, $base, $useWallet, 'application_fee', LoanApplication::class, (int) $application->id);

            return;
        }

        app(AffiliateService::class)->accrueCommission(
            $customer,
            $base,
            'application_fee',
            LoanApplication::class,
            (int) $application->id,
        );

        if ($useWallet && $referrals->canUseWalletFor('application_fee')) {
            $walletApplied = (float) ($quote['wallet_applied'] ?? 0);
            if ($walletApplied > 0) {
                $referrals->debit(
                    $customer,
                    $walletApplied,
                    'Applied to asset conversion fee',
                    LoanApplication::class,
                    (int) $application->id,
                );
            }
        }
    }
}
