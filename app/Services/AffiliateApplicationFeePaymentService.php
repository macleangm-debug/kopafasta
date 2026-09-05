<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\PartnerApplication;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Affiliate Standard application fee — paid via canonical payment.show before review.
 * Payment verified ≠ approved; only unlocks submission into the pending queue.
 */
class AffiliateApplicationFeePaymentService
{
    public function __construct(
        private readonly PaymentAccountService $accounts,
    ) {}

    public function feeAmount(): float
    {
        $amount = (float) Setting::get(
            'affiliates.application_fee_amount',
            config('affiliates.application_fee_amount', 10_000)
        );

        return app(\App\Services\Staging\StagingPaymentsService::class)
            ->effective('affiliate_application_fee', max(0, $amount));
    }

    public function open(PartnerApplication $application): CustomerPayment
    {
        abort_unless($application->resolvedCategory() === 'affiliate', 422);

        $amount = round($this->feeAmount(), 2);
        $existingId = (int) data_get($application->payload, 'application_fee.payment_id', 0);
        if ($existingId > 0) {
            $existing = CustomerPayment::query()->find($existingId);
            if ($existing && in_array($existing->status, ['awaiting_payment', 'processing', 'pending_verification'], true)) {
                if ($existing->status === 'awaiting_payment' && (float) $existing->amount !== $amount) {
                    $existing->update(['amount' => $amount]);
                }

                return $existing->fresh();
            }
            if ($existing && $existing->isVerified()) {
                return $existing;
            }
        }

        $token = (string) data_get($application->payload, 'application_fee.pay_token');
        if ($token === '') {
            $token = Str::random(48);
        }

        $reference = 'AFF-APP-'.strtoupper(Str::random(8));
        $resolved = $this->accounts->resolve('affiliate_application_fee', 'mobile_money');

        $payment = CustomerPayment::query()->create([
            'reference' => $reference,
            'customer_id' => null,
            'partner_id' => null,
            'payment_type' => 'affiliate_application_fee',
            'payment_method' => 'mobile_money',
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'bank_account_id' => $this->accounts->resolve('affiliate_application_fee', 'bank_transfer')['bank_account']?->id,
            'mobile_money_account_id' => $resolved['mobile_money_account']?->id,
            'payment_instructions' => $resolved['instructions'],
            'source_type' => PartnerApplication::class,
            'source_id' => $application->id,
            'provider_meta' => [
                'awaiting_collection' => true,
                'description' => 'Affiliate application fee',
                'affiliate_application_id' => $application->id,
                'pay_token' => $token,
                'fee_snapshot' => [
                    'amount' => $amount,
                    'settings_key' => 'affiliates.application_fee_amount',
                    'snapshotted_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $payload = is_array($application->payload) ? $application->payload : [];
        $payload['application_fee'] = [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->reference,
            'amount' => $amount,
            'pay_token' => $token,
            'status' => 'awaiting_payment',
            'snapshotted_at' => now()->toIso8601String(),
        ];
        $application->forceFill([
            'status' => 'awaiting_fee',
            'payload' => $payload,
        ])->save();

        return $payment;
    }

    public function findByToken(string $token): ?CustomerPayment
    {
        if ($token === '') {
            return null;
        }

        return CustomerPayment::query()
            ->where('payment_type', 'affiliate_application_fee')
            ->where('provider_meta->pay_token', $token)
            ->latest('id')
            ->first();
    }

    public function authorize(CustomerPayment $payment, string $token): void
    {
        abort_unless(
            $payment->payment_type === 'affiliate_application_fee'
            && hash_equals((string) data_get($payment->provider_meta, 'pay_token'), $token),
            403
        );
    }

    public function markApplicationSubmitted(CustomerPayment $payment): void
    {
        if ($payment->payment_type !== 'affiliate_application_fee') {
            return;
        }

        $application = $payment->source_type === PartnerApplication::class
            ? PartnerApplication::query()->find($payment->source_id)
            : null;

        if (! $application) {
            $id = (int) data_get($payment->provider_meta, 'affiliate_application_id', 0);
            $application = $id > 0 ? PartnerApplication::query()->find($id) : null;
        }

        if (! $application) {
            return;
        }

        $payload = is_array($application->payload) ? $application->payload : [];
        $payload['application_fee'] = array_merge($payload['application_fee'] ?? [], [
            'status' => 'paid',
            'paid_at' => now()->toIso8601String(),
            'payment_id' => $payment->id,
            'payment_reference' => $payment->reference,
            'amount' => (float) $payment->amount,
        ]);

        if (in_array($application->status, ['awaiting_fee', 'draft'], true) || $application->status === '') {
            $application->status = 'pending';
        }

        $application->payload = $payload;
        $application->save();
    }

    public function payUrl(CustomerPayment $payment): string
    {
        $token = (string) data_get($payment->provider_meta, 'pay_token');

        return route('site.affiliate.apply.pay', ['token' => $token]);
    }
}
