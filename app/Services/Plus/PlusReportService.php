<?php

namespace App\Services\Plus;

use App\Models\Customer;
use App\Models\PlusGoal;
use App\Models\PlusGoalContribution;
use App\Models\PlusMoneyEntry;
use App\Models\PlusMonthlyReport;
use App\Models\PlusSubscription;
use App\Models\Setting;
use App\Services\Grades\GradeBenefitService;
use App\Services\MemberEngagementService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlusReportService
{
    public function __construct(
        private readonly PlusWorkspaceService $workspace,
        private readonly GradeBenefitService $benefits,
        private readonly MemberEngagementService $engagement,
        private readonly PlusNotificationGate $gate,
    ) {}

    public function config(): array
    {
        $stored = Setting::get('kopafasta_plus.config');
        $config = is_array($stored) ? $stored : [];

        return array_replace([
            'enabled' => true,
            'generation_day' => 1,
            'insights' => true,
        ], $config['reports'] ?? []);
    }

    public function monthDashboard(Customer $customer, ?string $month = null): array
    {
        $selected = $this->resolveMonth($month);
        $closed = $selected->copy()->endOfMonth()->lt(now()->copy()->startOfDay());
        $snapshot = PlusMonthlyReport::query()
            ->where('customer_id', $customer->id)
            ->whereDate('period_month', $selected->toDateString())
            ->first();

        if ($closed && $snapshot) {
            $payload = $snapshot->payload;
            $payload['from_snapshot'] = true;
            $payload['months'] = $this->monthChoices();
            $this->markViewed($snapshot);

            return $payload;
        }

        $payload = $this->buildMonth($customer, $selected);
        $payload['from_snapshot'] = false;
        $payload['months'] = $this->monthChoices();

        if ($closed) {
            PlusMonthlyReport::query()->firstOrCreate(
                [
                    'customer_id' => $customer->id,
                    'period_month' => $selected->toDateString(),
                ],
                [
                    'payload' => $payload,
                    'version' => 1,
                ]
            );
        }

        return $payload;
    }

    public function generateClosedMonth(Customer $customer, Carbon $month): PlusMonthlyReport
    {
        $start = $month->copy()->startOfMonth();
        $payload = $this->buildMonth($customer, $start);

        return PlusMonthlyReport::query()->firstOrCreate(
            [
                'customer_id' => $customer->id,
                'period_month' => $start->toDateString(),
            ],
            ['payload' => $payload, 'version' => 1]
        );
    }

    public function notifyReady(Customer $customer, Carbon $month): bool
    {
        $row = PlusMonthlyReport::query()
            ->where('customer_id', $customer->id)
            ->whereDate('period_month', $month->copy()->startOfMonth()->toDateString())
            ->first();
        if (! $row || $row->notified_at) {
            return false;
        }
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $label = $month->copy()->locale($locale)->translatedFormat('F Y');
        $ok = $this->gate->notify($customer, 'plus_monthly_report_ready', [
            'month' => $label,
            'url' => route('site.borrower.plus.reports', ['month' => $month->format('Y-m')]),
            '_fallback_body' => __('plus.reports.ready_body', ['month' => $label]),
        ]);
        if ($ok) {
            $row->update(['notified_at' => now()]);
        }

        return $ok;
    }

    /** @return Collection<int, PlusMonthlyReport> */
    public function history(Customer $customer, int $limit = 6): Collection
    {
        return PlusMonthlyReport::query()
            ->where('customer_id', $customer->id)
            ->latest('period_month')
            ->limit($limit)
            ->get();
    }

    public function generateDueReports(): int
    {
        $config = $this->config();
        if (! ($config['enabled'] ?? true)) {
            return 0;
        }
        $day = max(1, min(5, (int) ($config['generation_day'] ?? 1)));
        if (now()->day < $day) {
            return 0;
        }
        $month = now()->copy()->subMonth()->startOfMonth();
        $count = 0;
        PlusSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('customer')
            ->chunkById(50, function ($rows) use ($month, &$count) {
                foreach ($rows as $subscription) {
                    $customer = $subscription->customer;
                    if (! $customer) {
                        continue;
                    }
                    $this->generateClosedMonth($customer, $month);
                    $this->notifyReady($customer, $month);
                    $count++;
                }
            });

        return $count;
    }

    private function resolveMonth(?string $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $parsed = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            if ($parsed->lte(now()->copy()->startOfMonth())) {
                return $parsed;
            }
        }

        return now()->copy()->startOfMonth();
    }

    /** @return list<array{value: string, label: string}> */
    private function monthChoices(): array
    {
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $choices = [];
        $cursor = now()->copy()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $choices[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->locale($locale)->translatedFormat('F Y'),
            ];
            $cursor->subMonth();
        }

        return $choices;
    }

    private function markViewed(PlusMonthlyReport $row): void
    {
        if (! $row->viewed_at) {
            $row->update(['viewed_at' => now()]);
        }
    }

    private function buildMonth(Customer $customer, Carbon $month): array
    {
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $prevStart = $start->copy()->subMonth();
        $prevEnd = $start->copy()->subDay();
        $label = $start->locale($locale)->translatedFormat('F Y');
        $prevLabel = $prevStart->locale($locale)->translatedFormat('F');

        $money = $this->workspace->moneyTotals($customer, $start, $end);
        $prevMoney = $this->workspace->moneyTotals($customer, $prevStart, $prevEnd);
        $business = $this->workspace->businessTotals($customer, $start->toDateString(), $end->toDateString());
        $prevBusiness = $this->workspace->businessTotals($customer, $prevStart->toDateString(), $prevEnd->toDateString());
        $left = $money['in'] - $money['out'];
        $prevLeft = $prevMoney['in'] - $prevMoney['out'];

        $goals = PlusGoal::query()->where('customer_id', $customer->id)->get();
        $added = (float) PlusGoalContribution::query()
            ->whereIn('plus_goal_id', $goals->pluck('id'))
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
        $goalCards = $goals->map(function (PlusGoal $goal) use ($start, $end) {
            $monthAdd = (float) $goal->contributions()
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount');

            return [
                'title' => $goal->title,
                'icon' => $goal->kindIcon(),
                'percent' => $goal->progressPercent(),
                'saved' => (float) $goal->saved_amount,
                'target' => (float) $goal->target_amount,
                'added' => $monthAdd,
                'complete' => $goal->isComplete() && $goal->completed_at && $goal->completed_at->between($start, $end),
            ];
        })->all();

        $trustNow = $this->engagement->trustScore($customer);
        $percent = (int) ($trustNow['percent'] ?? 0);
        $trust = $this->benefits->trustLabel($percent, $locale);
        $grade = strtoupper((string) ($customer->grade ?: 'bronze'));
        $daysRecorded = PlusMoneyEntry::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->distinct('entry_date')
            ->count('entry_date');

        $where = $this->moneyBreakdown($customer, $start, $end);
        $observations = $this->observations($money, $prevMoney, $business, $prevBusiness, $added, $goals->count(), $prevLabel);
        $noticed = $this->noticed($money, $prevMoney, $business, $prevBusiness, $goalCards);
        $next = $this->nextMove($goalCards, $business);

        $thin = $daysRecorded < 8 && $start->isCurrentMonth();

        return [
            'month' => $start->format('Y-m'),
            'label' => $label,
            'prev_label' => $prevLabel,
            'member_name' => trim((string) ($customer->full_name ?: $customer->first_name)),
            'member_since' => $customer->created_at?->locale($locale)->isoFormat('MMMM YYYY'),
            'money' => $money + ['left' => $left],
            'prev_money' => $prevMoney + ['left' => $prevLeft],
            'business' => $business,
            'prev_business' => $prevBusiness,
            'where' => $where,
            'goals_added' => $added,
            'goals_moved' => collect($goalCards)->where('added', '>', 0)->count(),
            'goals_done' => $goals->filter->isComplete()->count(),
            'goals_total' => $goals->count(),
            'goal_cards' => $goalCards,
            'trust' => $trust,
            'trust_percent' => $percent,
            'grade' => $grade,
            'benefits' => $this->benefits->customerBenefits($customer, $locale),
            'has_money' => $this->workspace->hasAnyMoney($customer),
            'has_business' => $this->workspace->hasAnyBusiness($customer),
            'has_goals' => $goals->isNotEmpty(),
            'observations' => $observations,
            'noticed' => $noticed,
            'next' => $next,
            'sentence' => $this->oneSentence($left, $prevLeft, $business, $prevBusiness, $percent),
            'days_recorded' => $daysRecorded,
            'thin' => $thin,
            'history' => $this->history($customer)->map(fn (PlusMonthlyReport $row) => [
                'month' => $row->period_month?->format('Y-m'),
                'label' => $row->period_month?->locale($locale)->translatedFormat('F Y'),
                'trust' => data_get($row->payload, 'trust_percent'),
            ])->all(),
        ];
    }

    /** @return list<array{label: string, amount: float, pct: int}> */
    private function moneyBreakdown(Customer $customer, Carbon $from, Carbon $to): array
    {
        $rows = PlusMoneyEntry::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->where('outflow', '>', 0)
            ->get();
        $total = (float) $rows->sum('outflow');
        if ($total <= 0) {
            return [];
        }

        return $rows
            ->groupBy(fn ($row) => filled($row->other_label) ? $row->other_label : ($row->category ?: 'other'))
            ->map(fn ($group) => [
                'label' => $this->workspace->moneyCategoryLabel($group->first()->category, $group->first()->other_label),
                'amount' => (float) $group->sum('outflow'),
                'pct' => (int) round(((float) $group->sum('outflow') / $total) * 100),
            ])
            ->sortByDesc('amount')
            ->take(5)
            ->values()
            ->all();
    }

    private function observations(array $money, array $prevMoney, array $business, array $prevBusiness, float $added, int $goalCount, string $prevLabel): array
    {
        $left = $money['in'] - $money['out'];
        $prevLeft = $prevMoney['in'] - $prevMoney['out'];
        $items = [];
        if ($money['in'] > 0 || $money['out'] > 0) {
            $items[] = [
                'title' => $left >= $prevLeft ? __('plus.reports.obs_kept_title') : __('plus.reports.obs_spent_title'),
                'body' => $left >= $prevLeft
                    ? __('plus.reports.obs_kept_body')
                    : __('plus.reports.obs_spent_body', ['period' => $prevLabel]),
            ];
        }
        if ($business['sold'] > 0 || $business['spent'] > 0) {
            $items[] = [
                'title' => $business['difference'] >= 0 ? __('plus.reports.obs_biz_up_title') : __('plus.reports.obs_biz_down_title'),
                'body' => __('plus.reports.obs_biz_body', [
                    'sold' => format_money($business['sold']),
                    'diff' => format_money($business['difference']),
                ]),
            ];
        }
        if ($goalCount > 0) {
            $items[] = [
                'title' => __('plus.reports.obs_goals_title'),
                'body' => __('plus.reports.obs_goals_body', ['amount' => format_money($added)]),
            ];
        }
        $items[] = [
            'title' => __('plus.reports.obs_trust_title'),
            'body' => __('plus.reports.trust_ok'),
        ];

        return array_slice($items, 0, 4);
    }

    private function noticed(array $money, array $prevMoney, array $business, array $prevBusiness, array $goalCards): array
    {
        $items = [];
        $in = $money['in'];
        $keptPct = $in > 0 ? (int) round((($in - $money['out']) / $in) * 100) : 0;
        $prevIn = $prevMoney['in'];
        $prevKept = $prevIn > 0 ? (int) round((($prevIn - $prevMoney['out']) / $prevIn) * 100) : 0;
        if ($in > 0) {
            $items[] = [
                'title' => __('plus.reports.noticed_keep_title'),
                'body' => __('plus.reports.noticed_keep_body', ['now' => $keptPct, 'then' => $prevKept]),
            ];
        }
        if ($business['sold'] > 0) {
            $items[] = [
                'title' => $business['sold'] >= $prevBusiness['sold']
                    ? __('plus.reports.noticed_biz_title')
                    : __('plus.reports.noticed_biz_quiet_title'),
                'body' => __('plus.reports.noticed_biz_body'),
            ];
        }
        $stale = collect($goalCards)->first(fn ($g) => ($g['added'] ?? 0) <= 0 && ($g['percent'] ?? 0) < 100);
        if ($stale) {
            $items[] = [
                'title' => __('plus.reports.noticed_goal_title'),
                'body' => __('plus.reports.noticed_goal_body', ['goal' => $stale['title']]),
            ];
        }

        return array_slice($items, 0, 3);
    }

    private function nextMove(array $goalCards, array $business): array
    {
        $stale = collect($goalCards)->first(fn ($g) => ($g['added'] ?? 0) <= 0 && ($g['percent'] ?? 0) < 100);
        if ($stale) {
            return [
                'title' => __('plus.reports.next_goal', ['goal' => $stale['title']]),
                'cta' => __('plus.goals.add'),
                'url' => route('site.borrower.plus.goals'),
            ];
        }
        if (($business['sold'] ?? 0) <= 0) {
            return [
                'title' => __('plus.reports.next_business'),
                'cta' => __('plus.business.sold_action'),
                'url' => route('site.borrower.plus.business'),
            ];
        }

        return [
            'title' => __('plus.reports.next_money'),
            'cta' => __('plus.money.in_action'),
            'url' => route('site.borrower.plus.money'),
        ];
    }

    private function oneSentence(float $left, float $prevLeft, array $business, array $prevBusiness, int $trust): string
    {
        return __('plus.reports.sentence', [
            'kept' => $left >= $prevLeft ? __('plus.reports.sentence_kept') : __('plus.reports.sentence_spent'),
            'biz' => ($business['difference'] ?? 0) >= ($prevBusiness['difference'] ?? 0)
                ? __('plus.reports.sentence_biz_up')
                : __('plus.reports.sentence_biz_down'),
            'trust' => $trust,
        ]);
    }
}
