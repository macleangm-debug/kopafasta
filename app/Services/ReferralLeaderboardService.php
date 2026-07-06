<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Collection;

class ReferralLeaderboardService
{
    public function __construct(
        private readonly GamificationSettingsService $settings,
        private readonly ReferralService $referrals,
    ) {}

    /** @return Collection<int, array{rank: int, display_name: string, member_no: string, count: int}> */
    public function topThisMonth(int $limit = null): Collection
    {
        $config = $this->settings->group('leaderboard');
        if (! ($config['enabled'] ?? true)) {
            return collect();
        }

        $limit ??= (int) ($config['limit'] ?? 10);
        $mask = (bool) ($config['mask_names'] ?? true);

        $start = now()->startOfMonth();

        $rows = Customer::query()
            ->select('customers.id', 'customers.first_name', 'customers.member_no', 'customers.customer_number')
            ->selectRaw('COUNT(referred.id) as successful_count')
            ->join('customers as referred', 'referred.referred_by_customer_id', '=', 'customers.id')
            ->whereNotNull('referred.membership_issued_at')
            ->where('referred.membership_issued_at', '>=', $start)
            ->groupBy('customers.id', 'customers.first_name', 'customers.member_no', 'customers.customer_number')
            ->orderByDesc('successful_count')
            ->limit($limit)
            ->get();

        return $rows->values()->map(function ($row, int $index) use ($mask) {
            $firstName = trim((string) ($row->first_name ?? 'Member'));
            $display = $mask
                ? $this->maskName($firstName)
                : $firstName;

            return [
                'rank'         => $index + 1,
                'display_name' => $display,
                'member_no'    => (string) ($row->member_no ?: $row->customer_number ?: '—'),
                'count'        => (int) $row->successful_count,
            ];
        });
    }

    public function rankFor(Customer $customer): ?int
    {
        $leaderboard = $this->topThisMonth(100);
        $match = $leaderboard->first(fn (array $row) => ($row['member_no'] ?? '') === ($customer->member_no ?: $customer->customer_number));

        return $match['rank'] ?? null;
    }

    private function maskName(string $firstName): string
    {
        if ($firstName === '') {
            return 'Member *****';
        }

        return $firstName.' *****';
    }
}
