<?php

namespace App\Services\Plus;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PlusSubscription;
use App\Models\Setting;
use App\Services\CustomerPaymentService;

class PlusService
{
    public function config(): array
    {
        $stored = Setting::get('kopafasta_plus.config');

        return is_array($stored) ? array_replace_recursive(config('kopafasta_plus'), $stored) : config('kopafasta_plus');
    }

    public function isActive(Customer $customer): bool
    {
        return PlusSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function current(Customer $customer): ?PlusSubscription
    {
        return PlusSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();
    }

    public function priceFor(Customer $customer): array
    {
        $country = strtoupper((string) ($customer->country_code ?? 'TZ'));
        $plan = $this->config()['plans']['monthly'];

        return $plan['prices'][$country] ?? $plan['prices']['TZ'];
    }

    /**
     * Create (or reuse) a Kopafasta Plus payment obligation and leave method
     * selection to payments.show. Never talks to a PSP.
     */
    public function startCheckout(Customer $customer): CustomerPayment
    {
        $price = $this->priceFor($customer);
        $amount = round((float) ($price['amount'] ?? 0), 2);
        abort_if($amount <= 0, 422, 'Kopafasta Plus is not priced in Settings.');

        $open = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'kopafasta_plus')
            ->whereIn('status', ['awaiting_payment', 'pending_verification', 'processing'])
            ->where('amount', $amount)
            ->latest('id')
            ->first();

        if ($open) {
            return $open;
        }

        return app(CustomerPaymentService::class)->create([
            'customer' => $customer,
            'payment_type' => 'kopafasta_plus',
            'payment_method' => 'mobile_money',
            'amount' => $amount,
            'currency' => $price['currency'] ?? 'TZS',
            'mobile_number' => $customer->phone,
            'auto_verify' => false,
            'description' => 'Kopafasta Plus',
        ]);
    }

    public function activate(Customer $customer, array $attrs = []): PlusSubscription
    {
        $ref = $attrs['payment_reference'] ?? $attrs['payment_reference'] ?? null;
        if (filled($ref)) {
            $already = PlusSubscription::query()->where('payment_reference', $ref)->first();
            if ($already) {
                return $already;
            }
        }

        $price = $this->priceFor($customer);
        $days = (int) ($attrs['days'] ?? $this->config()['plans']['monthly']['period_days'] ?? 30);
        $days = max(1, $days);

        $existing = $this->current($customer);
        $start = now();
        $expires = ($existing?->expires_at && $existing->expires_at->isFuture())
            ? $existing->expires_at->copy()->addDays($days)
            : now()->addDays($days);

        if ($existing) {
            $existing->update([
                'status' => 'active',
                'expires_at' => $expires,
                'price_paid' => $attrs['price_paid'] ?? $attrs['price_paid'] ?? $price['amount'],
                'currency' => $price['currency'],
                'payment_reference' => $ref ?: $existing->payment_reference,
                'complimentary' => (bool) ($attrs['complimentary'] ?? $existing->complimentary),
            ]);
            $subscription = $existing->fresh();
        } else {
            $subscription = PlusSubscription::query()->create([
                'customer_id' => $customer->id,
                'plan' => 'monthly',
                'status' => 'active',
                'country_code' => strtoupper((string) ($customer->country_code ?? 'TZ')),
                'currency' => $price['currency'],
                'price_paid' => $attrs['price_paid'] ?? $attrs['price_paid'] ?? $price['amount'],
                'complimentary' => (bool) ($attrs['complimentary'] ?? false),
                'starts_at' => $start,
                'expires_at' => $expires,
                'payment_reference' => $ref,
            ]);
        }

        return $subscription;
    }

    public function grantComplimentary(Customer $customer, string $reason, ?int $actorUserId = null, ?int $days = null): PlusSubscription
    {
        $days = $days ?? (int) ($this->config()['complimentary_days'] ?? 30);
        $days = max(1, $days);

        $subscription = $this->activate($customer, [
            'complimentary' => true,
            'price_paid' => 0,
            'days' => $days,
        ]);

        $entitlements = (array) ($subscription->entitlements ?? []);
        $entitlements['complimentary_grants'] = array_values(array_merge(
            $entitlements['complimentary_grants'] ?? [],
            [[
                'reason' => $reason,
                'actor_user_id' => $actorUserId,
                'days' => $days,
                'granted_at' => now()->toIso8601String(),
            ]]
        ));

        $subscription->update([
            'complimentary' => true,
            'entitlements' => $entitlements,
        ]);

        return $subscription->fresh();
    }

    public function cancel(Customer $customer): void
    {
        PlusSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
    }

    public function eligibleOffers(Customer $customer): \Illuminate\Support\Collection
    {
        $grade = (string) ($customer->grade ?: 'bronze');
        $country = strtoupper((string) ($customer->country_code ?? 'TZ'));
        $plus = $this->isActive($customer);

        return \App\Models\PlusOffer::query()
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get()
            ->filter(function ($offer) use ($grade, $country, $plus) {
                if ($offer->plus_only && ! $plus) {
                    return false;
                }
                $offerCountry = strtoupper((string) ($offer->country_code ?? ''));
                if ($offerCountry !== '' && $offerCountry !== $country) {
                    return false;
                }
                $grades = $offer->eligible_grades;
                if (is_array($grades) && $grades !== [] && ! in_array($grade, $grades, true)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    public function awardReward(Customer $customer, string $kind, int $points, string $reason, string $source = 'plus'): \App\Models\PlusRewardLedger
    {
        $blocked = ['loan', 'borrow', 'disbursement', 'application', 'drawdown'];
        foreach ($blocked as $word) {
            if (str_contains(strtolower($kind.' '.$reason.' '.$source), $word)) {
                throw new \InvalidArgumentException('Rewards must not incentivize borrowing.');
            }
        }

        return \App\Models\PlusRewardLedger::query()->create([
            'customer_id' => $customer->id,
            'kind' => $kind,
            'points' => $points,
            'reason' => $reason,
            'source' => $source,
        ]);
    }

    public function redeemReward(Customer $customer, int $points, string $reason): \App\Models\PlusRewardLedger
    {
        $points = abs($points);
        abort_unless($points > 0, 422, 'Enter points to redeem.');
        abort_unless($this->rewardBalance($customer) >= $points, 422, 'Not enough points.');

        return $this->awardReward($customer, 'redeem', -$points, $reason, 'redeem');
    }

    public function rewardBalance(Customer $customer): int
    {
        return (int) \App\Models\PlusRewardLedger::query()
            ->where('customer_id', $customer->id)
            ->sum('points');
    }
}
