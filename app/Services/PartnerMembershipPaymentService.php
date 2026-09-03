<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\Vendor;

class PartnerMembershipPaymentService
{
    public function __construct(
        private readonly PartnerMembershipService $membership,
        private readonly PaymentAccountService $accounts,
        private readonly CustomerPaymentService $payments,
    ) {}

    public function open(Vendor $vendor): CustomerPayment
    {
        $amount = round($this->feeFor($vendor), 2);
        $existing = CustomerPayment::query()
            ->where('partner_id', $vendor->id)
            ->where('payment_type', 'partner_membership')
            ->whereIn('status', ['awaiting_payment', 'processing', 'pending_verification'])
            ->latest('id')
            ->first();

        if ($existing) {
            if ($existing->status === 'awaiting_payment' && (float) $existing->amount !== $amount) {
                $existing->update(['amount' => $amount]);
                $existing = $existing->fresh();
            }

            return $existing;
        }

        $reference = $this->paymentReference($vendor);
        $resolved = $this->accounts->resolve('partner_membership', 'mobile_money');

        return CustomerPayment::query()->create([
            'reference' => $reference,
            'customer_id' => null,
            'partner_id' => $vendor->id,
            'payment_type' => 'partner_membership',
            'payment_method' => 'mobile_money',
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'bank_account_id' => $this->accounts->resolve('partner_membership', 'bank_transfer')['bank_account']?->id,
            'mobile_money_account_id' => $resolved['mobile_money_account']?->id,
            'payment_instructions' => $resolved['instructions'],
            'source_type' => Vendor::class,
            'source_id' => $vendor->id,
            'created_by' => $vendor->user_id,
            'provider_meta' => [
                'awaiting_collection' => true,
                'description' => $vendor->isAffiliate() ? 'Affiliate membership' : 'Partner membership',
                'affiliate' => $vendor->isAffiliate(),
            ],
        ]);
    }

    public function feeFor(Vendor $vendor): float
    {
        if ($vendor->isAffiliate()) {
            return app(AffiliateMembershipService::class)->feeFor($vendor);
        }

        return $this->membership->feeFor($vendor);
    }

    public function paymentReference(Vendor $vendor): string
    {
        if ($vendor->isAffiliate()) {
            return app(AffiliateMembershipService::class)->ensurePaymentReference($vendor);
        }

        return $this->membership->ensurePaymentReference($vendor);
    }

    public function dashboardUrl(Vendor $vendor): string
    {
        return $vendor->isAffiliate()
            ? route('site.affiliate.dashboard')
            : route('site.partner.dashboard');
    }

    public function authorize(CustomerPayment $payment, Vendor $vendor): void
    {
        abort_unless(
            (int) $payment->partner_id === (int) $vendor->id
            && $payment->payment_type === 'partner_membership',
            403
        );
    }
}
