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
    public function quote(Customer $customer, LoanApplication $application, bool $useWallet = false, ?string $promoCode = null): array
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
                'total_discount' => 0,
                'wallet_applied' => 0,
                'cash_due'       => 0,
                'wallet_usable'  => 0,
                'wallet_allowed' => false,
                'currency'       => $cfg['currency'],
            ];
        }

        return app(PaymentGateService::class)->quote(
            $customer,
            $base,
            'post_approval_fee',
            $useWallet,
            $promoCode,
        );
    }

    /**
     * @return array{payment: CustomerPayment|null, quote: array<string, mixed>}
     */
    public function processMobileMoney(
        Customer $customer,
        LoanApplication $application,
        string $paymentReference,
        bool $useWallet = false,
        ?string $mobileNumber = null,
        ?string $promoCode = null,
    ): array {
        $quote = $this->quote($customer, $application, $useWallet, $promoCode);
        $cashDue = (int) ($quote['cash_due'] ?? $quote['after_discount']);

        if ($cashDue <= 0) {
            app(PostApprovalFeeService::class)->markAllPaid($application, $customer, $useWallet);

            return [
                'payment' => null,
                'quote'   => $quote,
            ];
        }

        if ($existing = $this->existingPayment($application)) {
            return ['payment' => $existing, 'quote' => $quote];
        }

        $payInLive = app(\App\Services\PayInService::class)->isLiveCollectionEnabled();
        $dummyGateway = $this->usesDummyGateway();

        if (! $dummyGateway && ! $payInLive) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_method' => [__('borrower.payments.aggregator_required')],
            ]);
        }

        if ($payInLive && ! filled($mobileNumber ?: $customer->phone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mobile_number' => [__('borrower.payments.mobile_number_required')],
            ]);
        }

        // Settle discounts only when payment will be instant (dummy); live waits for aggregator.
        if ($dummyGateway && ! $payInLive) {
            app(PaymentGateService::class)->settle(
                $customer,
                $quote,
                'post_approval_fee',
                LoanApplication::class,
                (int) $application->id,
                $useWallet,
            );
        }

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'post_approval_fee',
            'payment_method' => 'mobile_money',
            'amount'         => $cashDue,
            'loan_product'   => $application->product,
            'reference'      => $paymentReference,
            'source'         => $application,
            'mobile_number'  => $mobileNumber ?: $customer->phone,
            'auto_verify'    => $dummyGateway && ! $payInLive,
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
        ?string $paymentDate = null,
        ?string $promoCode = null,
    ): array {
        $quote = $this->quote($customer, $application, $useWallet, $promoCode);
        $cashDue = (int) ($quote['cash_due'] ?? $quote['after_discount']);

        if ($cashDue <= 0) {
            app(PostApprovalFeeService::class)->markAllPaid($application, $customer, $useWallet);

            return [
                'payment' => null,
                'quote'   => $quote,
            ];
        }

        if ($existing = $this->existingPayment($application)) {
            return ['payment' => $existing, 'quote' => $quote];
        }

        if ($this->usesDummyGateway()) {
            app(PaymentGateService::class)->settle(
                $customer,
                $quote,
                'post_approval_fee',
                LoanApplication::class,
                (int) $application->id,
                $useWallet,
            );
        }

        $payment = app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => 'post_approval_fee',
            'payment_method' => 'bank_transfer',
            'amount'         => $cashDue,
            'loan_product'   => $application->product,
            'reference'      => $paymentReference,
            'source'         => $application,
            'payment_date'   => $paymentDate,
            'auto_verify'    => $this->usesDummyGateway(),
        ]);

        return ['payment' => $payment, 'quote' => $quote];
    }
}
