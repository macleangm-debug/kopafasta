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

        return $this->priceForCountry($country);
    }

    public function priceForCountry(?string $country = null): array
    {
        $country = strtoupper((string) ($country ?: 'TZ'));
        $plan = $this->config()['plans']['monthly'];

        return $plan['prices'][$country] ?? $plan['prices']['TZ'];
    }

    public function billingCycle(): string
    {
        $plan = $this->config()['plans']['monthly'] ?? [];
        $cycle = $plan['billing_cycle'] ?? null;
        if (in_array($cycle, ['monthly', 'yearly'], true)) {
            return $cycle;
        }

        return ((int) ($plan['period_days'] ?? 365)) <= 31 ? 'monthly' : 'yearly';
    }

    public function periodDays(): int
    {
        $plan = $this->config()['plans']['monthly'] ?? [];
        $cycle = $plan['billing_cycle'] ?? null;
        if ($cycle === 'monthly') {
            return 30;
        }
        if ($cycle === 'yearly') {
            return 365;
        }

        return max(1, (int) ($plan['period_days'] ?? 365));
    }

    /**
     * Publish a short sample lesson and offer so Learn / Offers are not empty
     * while staff write the first real Club content.
     */
    public function ensureSampleContent(): void
    {
        if (! \App\Models\PlusLesson::query()->exists()) {
            \App\Models\PlusLesson::query()->create([
                'month' => now()->format('Y-m'),
                'title_en' => 'Keep a simple money diary',
                'title_sw' => 'Weka daftari rahisi la pesa',
                'intro_en' => "This month’s Club lesson is a short habit, not a lecture.\n\nEach evening, write three numbers: money that came in, money that went out, and what is left. Do this for seven days. You will see which days are heavy and which days are light — and that is the start of control.\n\nKopafasta Plus never asks you to borrow more. The lesson is only about seeing your own money clearly.",
                'intro_sw' => "Somo la Klabu mwezi huu ni tabia fupi, si somo refu.\n\nKila jioni andika namba tatu: pesa zilizoingia, pesa zilizotoka, na salio. Fanya hivi kwa siku saba. Utaona siku nzito na siku nyepesi — ndiyo mwanzo wa kudhibiti pesa.\n\nKopafasta Plus haikuombi ukope zaidi. Somo ni kuona pesa zako wazi.",
                'action_en' => 'Tonight, write today’s in, out, and left.',
                'action_sw' => 'Leo usiku, andika kuingia, kutoka, na salio la leo.',
                'duration_minutes' => 7,
                'audience' => 'plus_members',
                'published_at' => now()->subHour(),
            ]);
            \App\Models\PlusLesson::query()->create([
                'month' => now()->copy()->subMonth()->format('Y-m'),
                'title_en' => 'Separate home money and business money',
                'title_sw' => 'Tenganisha pesa ya nyumbani na pesa ya biashara',
                'intro_en' => "When shop money and house money sit in the same pocket, both suffer.\n\nUse two envelopes, two books, or two mobile wallets: one for the business, one for the home. Pay yourself a small, regular amount from the business envelope. That is your household money.\n\nThis is a Plus Club article. Watch it in 5–10 quiet minutes, then do the action once.",
                'intro_sw' => "Pesaya duka na pesa ya nyumbani zikikaa mfukoni mmoja, zote zinateseka.\n\nTumia bahasha mbili, daftari mbili, au pochi mbili: moja ya biashara, moja ya nyumbani. Jilipe kiasi kidogo, mara kwa mara, kutoka bahasha ya biashara. Hiyo ndiyo pesa ya nyumbani.\n\nHii ni makala ya Klabu ya Plus. Soma dakika 5–10, kisha fanya hatua mara moja.",
                'action_en' => 'Create two labelled places for money before the week ends.',
                'action_sw' => 'Tengeneza sehemu mbili za pesa zenye lebo kabla wiki haijaisha.',
                'duration_minutes' => 8,
                'audience' => 'plus_members',
                'published_at' => now()->subMonth()->endOfMonth(),
            ]);
        }

        if (! \App\Models\PlusOffer::query()->exists()) {
            \App\Models\PlusOffer::query()->create([
                'title' => 'Plus Club — record 7 days of money in and out',
                'body' => 'Write money in and out for seven days. Partner offers for your country will appear here when they are live.',
                'tier' => 'standard',
                'country_code' => 'TZ',
                'eligible_grades' => ['bronze', 'silver', 'gold', 'platinum'],
                'plus_only' => true,
                'active' => true,
            ]);
        } else {
            \App\Models\PlusOffer::query()
                ->where(function ($q) {
                    $q->where('body', 'like', '%does not change your Grade%')
                        ->orWhere('body', 'like', '%haibadilishi Daraja%');
                })
                ->update([
                    'body' => 'Write money in and out for seven days. Partner offers for your country will appear here when they are live.',
                ]);
        }
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

    /** @return list<array{code: string, points: int, title_en: string, title_sw: string, title: string}> */
    public function rewardCatalog(): array
    {
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $items = $this->config()['rewards']['catalog'] ?? config('kopafasta_plus.rewards.catalog', []);

        return collect($items)->map(function (array $item) use ($locale) {
            $item['title'] = $locale === 'sw'
                ? ($item['title_sw'] ?? $item['title_en'] ?? '')
                : ($item['title_en'] ?? '');

            return $item;
        })->values()->all();
    }

    public function recordOfferEvent(Customer $customer, \App\Models\PlusOffer $offer, string $event): void
    {
        \App\Models\PlusOfferEvent::query()->create([
            'customer_id' => $customer->id,
            'plus_offer_id' => $offer->id,
            'event' => $event,
        ]);
    }

    public function hasClaimed(Customer $customer, \App\Models\PlusOffer $offer): bool
    {
        return \App\Models\PlusOfferEvent::query()
            ->where('customer_id', $customer->id)
            ->where('plus_offer_id', $offer->id)
            ->where('event', 'claimed')
            ->exists();
    }
}
