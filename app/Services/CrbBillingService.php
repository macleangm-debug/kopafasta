<?php

namespace App\Services;

use App\Models\CreditHistory;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CrbBillingService
{
    public function costPerRequest(): float
    {
        return max(0, (float) (Setting::group('kyc')['crb_cost_per_request'] ?? 0));
    }

    /** @return array{month: string, requests: int, estimated_cost: float, fresh_reuse_count: int} */
    public function monthlySummary(?CarbonImmutable $month = null): array
    {
        $start = ($month ?? CarbonImmutable::now())->startOfMonth();
        $end = $start->endOfMonth();

        $requests = CreditHistory::query()
            ->whereBetween('checked_at', [$start, $end])
            ->whereIn('source', ['crb', 'crb_stub'])
            ->count();

        $costPerRequest = $this->costPerRequest();

        return [
            'month'            => $start->format('F Y'),
            'requests'         => $requests,
            'estimated_cost'   => round($requests * $costPerRequest, 2),
            'fresh_reuse_count'=> $this->freshReuseCount($start, $end),
        ];
    }

    /** @return Collection<int, array{month: string, requests: int, estimated_cost: float}> */
    public function recentMonths(int $months = 6): Collection
    {
        return collect(range(0, max(0, $months - 1)))
            ->map(fn (int $offset) => $this->monthlySummary(CarbonImmutable::now()->subMonths($offset)->startOfMonth()));
    }

    private function freshReuseCount(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return CreditHistory::query()
            ->whereBetween('checked_at', [$start, $end])
            ->whereIn('source', ['crb', 'crb_stub'])
            ->where('payload->reused', true)
            ->count();
    }
}
