<?php

namespace App\Services;

use App\Models\AffiliateEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AffiliateMarketingAttributionReportService
{
    /**
     * @return array{
     *     totals: array<string, int>,
     *     funnel: array<string, float>,
     *     by_source: array<string, int>,
     *     by_campaign: array<string, int>,
     *     by_medium: array<string, int>,
     *     by_device: array<string, int>,
     *     daily: list<array<string, mixed>>
     * }
     */
    public function report(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();

        $base = AffiliateEvent::query()->whereBetween('created_at', [$from, $to]);

        $clicks = (int) (clone $base)->where('event_type', 'click')->count();
        $registrations = (int) (clone $base)->where('event_type', 'registration')->count();
        $applications = (int) (clone $base)->where('event_type', 'application')->count();

        return [
            'totals' => [
                'clicks'        => $clicks,
                'registrations' => $registrations,
                'applications'  => $applications,
            ],
            'funnel' => [
                'click_to_registration' => $clicks > 0 ? round(($registrations / $clicks) * 100, 2) : 0.0,
                'registration_to_application' => $registrations > 0 ? round(($applications / $registrations) * 100, 2) : 0.0,
            ],
            'by_source'   => $this->groupCount($base, 'utm_source'),
            'by_campaign' => $this->groupCount($base, 'utm_campaign'),
            'by_medium'   => $this->groupCount($base, 'utm_medium'),
            'by_device'   => $this->groupCount($base, 'device_type'),
            'daily'       => $this->dailyTrend($from, $to),
        ];
    }

    /** @return array<string, int> */
    protected function groupCount($base, string $column): array
    {
        return (clone $base)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw($column.' as label, count(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(15)
            ->pluck('total', 'label')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function dailyTrend(Carbon $from, Carbon $to): array
    {
        $rows = AffiliateEvent::query()
            ->selectRaw('DATE(created_at) as day, event_type, count(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('day', 'event_type')
            ->orderBy('day')
            ->get();

        $days = [];
        foreach ($rows as $row) {
            $day = (string) $row->day;
            $days[$day] ??= ['day' => $day, 'clicks' => 0, 'registrations' => 0, 'applications' => 0];
            $key = match ($row->event_type) {
                'registration' => 'registrations',
                'application'  => 'applications',
                'click'        => 'clicks',
                default        => null,
            };
            if ($key) {
                $days[$day][$key] = (int) $row->total;
            }
        }

        return array_values($days);
    }

    /** @return Collection<int, object> */
    public function topAffiliatesByUtmSource(?Carbon $from = null, ?Carbon $to = null, int $limit = 10): Collection
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();

        return AffiliateEvent::query()
            ->selectRaw('partner_id, utm_source, count(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('utm_source')
            ->groupBy('partner_id', 'utm_source')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('vendor')
            ->get();
    }
}
