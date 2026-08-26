<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\MarketingAudience;
use App\Models\PlusSubscription;
use Illuminate\Database\Eloquent\Builder;

class MarketingAudienceService
{
    /** @param  array<string, mixed>  $filters */
    public function query(array $filters): Builder
    {
        $query = Customer::query();

        $country = strtoupper((string) ($filters['country_code'] ?? ''));
        if ($country !== '') {
            $query->where('country_code', $country);
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $grades = array_values(array_filter((array) ($filters['grades'] ?? [])));
        if ($grades !== []) {
            $query->whereIn('grade', $grades);
        }

        $plus = (string) ($filters['plus'] ?? 'any');
        if ($plus === 'subscribed') {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('plus_subscriptions')
                    ->whereColumn('plus_subscriptions.customer_id', 'customers.id')
                    ->where('plus_subscriptions.status', 'active')
                    ->where('plus_subscriptions.expires_at', '>', now());
            });
        } elseif ($plus === 'not_subscribed') {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('plus_subscriptions')
                    ->whereColumn('plus_subscriptions.customer_id', 'customers.id')
                    ->where('plus_subscriptions.status', 'active')
                    ->where('plus_subscriptions.expires_at', '>', now());
            });
        }

        $borrowing = (string) ($filters['borrowing'] ?? 'any');
        if ($borrowing === 'active_loan') {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('loans')
                    ->whereColumn('loans.customer_id', 'customers.id')
                    ->where('loans.status', 'active');
            });
        } elseif ($borrowing === 'completed_loan') {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')->from('loans')
                    ->whereColumn('loans.customer_id', 'customers.id')
                    ->whereIn('loans.status', ['closed', 'completed', 'settled']);
            });
        } elseif ($borrowing === 'never_borrowed') {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')->from('loans')->whereColumn('loans.customer_id', 'customers.id');
            });
        }

        $affiliate = (string) ($filters['affiliate'] ?? 'any');
        if ($affiliate === 'referred') {
            $query->whereNotNull('affiliate_partner_id');
        } elseif ($affiliate === 'not_referred') {
            $query->whereNull('affiliate_partner_id');
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filters */
    public function estimate(array $filters): int
    {
        return $this->query($filters)->count();
    }

    public function refresh(MarketingAudience $audience): MarketingAudience
    {
        $count = $this->estimate($audience->filters ?? []);
        $audience->update([
            'estimated_count' => $count,
            'estimated_at' => now(),
        ]);

        return $audience->fresh();
    }

    /** @return array<string, mixed> */
    public function dimensions(): array
    {
        return config('marketing.audience_dimensions', []);
    }
}
