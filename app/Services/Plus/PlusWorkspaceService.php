<?php

namespace App\Services\Plus;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\PlusBusinessEntry;
use App\Models\PlusGoal;
use App\Models\PlusGoalContribution;
use App\Models\PlusLesson;
use App\Models\PlusLessonProgress;
use App\Models\PlusMoneyEntry;
use App\Models\PlusRewardLedger;
use App\Services\ActiveLoanServicingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlusWorkspaceService
{
    public function __construct(
        private readonly PlusService $plus,
        private readonly ActiveLoanServicingService $servicing,
    ) {}

    public function moneyCategories(): array
    {
        return [
            'food' => ['en' => 'Food', 'sw' => 'Chakula'],
            'transport' => ['en' => 'Transport', 'sw' => 'Usafiri'],
            'home' => ['en' => 'Home', 'sw' => 'Nyumbani'],
            'business' => ['en' => 'Business', 'sw' => 'Biashara'],
            'bills' => ['en' => 'Bills', 'sw' => 'Bili'],
            'other' => ['en' => 'Other', 'sw' => 'Nyingine'],
        ];
    }

    public function incomeSources(): array
    {
        return [
            'business' => ['en' => 'Business', 'sw' => 'Biashara'],
            'salary' => ['en' => 'Salary', 'sw' => 'Mshahara'],
            'someone_paid' => ['en' => 'Someone paid me', 'sw' => 'Mtu amenilipa'],
            'other' => ['en' => 'Other', 'sw' => 'Nyingine'],
        ];
    }

    public function goalKinds(): array
    {
        return [
            'business' => ['en' => 'Business', 'sw' => 'Biashara', 'icon' => '🏪'],
            'school' => ['en' => 'School fees', 'sw' => 'Ada', 'icon' => '📚'],
            'home' => ['en' => 'Home', 'sw' => 'Nyumba', 'icon' => '🏠'],
            'vehicle' => ['en' => 'Vehicle / motorcycle', 'sw' => 'Gari / pikipiki', 'icon' => '🛵'],
            'emergency' => ['en' => 'Emergency', 'sw' => 'Dharura', 'icon' => '🛟'],
            'stock' => ['en' => 'Stock', 'sw' => 'Bidhaa', 'icon' => '📦'],
            'savings' => ['en' => 'Savings', 'sw' => 'Akiba', 'icon' => '💰'],
            'other' => ['en' => 'Something else', 'sw' => 'Kitu kingine', 'icon' => '🎯'],
        ];
    }

    public function saleTypes(): array
    {
        return [
            'products' => ['en' => 'Products', 'sw' => 'Bidhaa'],
            'services' => ['en' => 'Services', 'sw' => 'Huduma'],
            'other' => ['en' => 'Other', 'sw' => 'Nyingine'],
        ];
    }

    public function spendTypes(): array
    {
        return [
            'stock' => ['en' => 'Stock', 'sw' => 'Bidhaa'],
            'transport' => ['en' => 'Transport', 'sw' => 'Usafiri'],
            'rent' => ['en' => 'Rent', 'sw' => 'Kodi'],
            'staff' => ['en' => 'Staff', 'sw' => 'Wafanyakazi'],
            'utilities' => ['en' => 'Utilities', 'sw' => 'Bili'],
            'services' => ['en' => 'Business services', 'sw' => 'Huduma za biashara'],
            'other' => ['en' => 'Other', 'sw' => 'Nyingine'],
        ];
    }

    public function moneyDashboard(Customer $customer): array
    {
        $month = now()->copy()->startOfMonth();
        $prev = $month->copy()->subMonth();
        $thisMonth = $this->moneyTotals($customer, $month, $month->copy()->endOfMonth());
        $lastMonth = $this->moneyTotals($customer, $prev, $prev->copy()->endOfMonth());
        $spentDiff = $lastMonth['out'] - $thisMonth['out'];
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';

        $insight = null;
        if ($lastMonth['out'] > 0) {
            $insight = $spentDiff >= 0
                ? __('plus.money.insight_under', ['amount' => format_money($spentDiff)])
                : __('plus.money.insight_over', ['amount' => format_money(abs($spentDiff))]);
        }

        return [
            'month_label' => $month->locale($locale)->translatedFormat('F'),
            'in' => $thisMonth['in'],
            'out' => $thisMonth['out'],
            'left' => $thisMonth['in'] - $thisMonth['out'],
            'insight' => $insight,
            'top_spend' => $this->topSpend($customer, $month, $month->copy()->endOfMonth()),
            'upcoming' => $this->upcoming($customer),
            'history' => PlusMoneyEntry::query()
                ->where('customer_id', $customer->id)
                ->latest('entry_date')
                ->latest('id')
                ->limit(50)
                ->get(),
            'categories' => $this->moneyCategories(),
            'sources' => $this->incomeSources(),
        ];
    }

    public function businessDashboard(Customer $customer, string $period = 'today'): array
    {
        $period = in_array($period, ['today', 'week', 'month'], true) ? $period : 'today';
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        [$from, $to, $previousFrom, $previousTo, $periodLabel] = $this->businessPeriodWindow($period);

        $summary = $this->businessTotals($customer, $from->toDateString(), $to->toDateString());
        $previous = $this->businessTotals($customer, $previousFrom->toDateString(), $previousTo->toDateString());
        $delta = $summary['sold'] - $previous['sold'];
        $insight = null;
        if ($previous['sold'] > 0 || $summary['sold'] > 0) {
            if ($previous['sold'] > 0) {
                $pct = (int) round((($summary['sold'] - $previous['sold']) / $previous['sold']) * 100);
                $insight = $pct >= 0
                    ? __('plus.business.insight_pct_up', ['percent' => abs($pct), 'period' => $periodLabel])
                    : __('plus.business.insight_pct_down', ['percent' => abs($pct), 'period' => $periodLabel]);
            } else {
                $insight = $delta >= 0
                    ? __('plus.business.insight_up')
                    : __('plus.business.insight_down');
            }
        }

        $history = PlusBusinessEntry::query()
            ->where('customer_id', $customer->id)
            ->latest('entry_date')
            ->latest('id')
            ->limit(50)
            ->get();

        $chart = $period === 'month'
            ? $this->businessMonthWeeks($customer, $from)
            : $this->businessWeekDays($customer, $period === 'today' ? now()->copy()->startOfWeek() : $from);

        $chartReady = collect($chart)->contains(fn (array $point) => ($point['sold'] ?? 0) > 0 || ($point['spent'] ?? 0) > 0);

        return [
            'period' => $period,
            'period_label' => $periodLabel,
            'today' => $this->businessTotals($customer, now()->toDateString(), now()->toDateString()),
            'week' => $this->businessTotals($customer, now()->copy()->startOfWeek()->toDateString(), now()->toDateString()),
            'summary' => $summary,
            'previous' => $previous,
            'week_improved' => $previous['sold'] > 0 && $summary['sold'] > $previous['sold'],
            'insight' => $insight,
            'history' => $history,
            'history_rows' => $this->businessHistoryRows($history),
            'chart' => $chart,
            'chart_ready' => $chartReady,
            'sale_types' => $this->saleTypes(),
            'spend_types' => $this->spendTypes(),
        ];
    }

    public function goalsDashboard(Customer $customer): array
    {
        $goals = PlusGoal::query()
            ->where('customer_id', $customer->id)
            ->with(['contributions' => fn ($q) => $q->latest('id')->limit(100)])
            ->latest('id')
            ->get();

        $active = $goals->filter(fn (PlusGoal $g) => ! $g->isComplete() && ! $g->isPaused());
        $lead = $active->sortByDesc(fn (PlusGoal $g) => $g->progressPercent())->first();

        return [
            'goals' => $goals,
            'active' => $active,
            'lead' => $lead,
            'kinds' => $this->goalKinds(),
            'contributed_this_month' => (float) PlusGoalContribution::query()
                ->whereIn('plus_goal_id', $goals->pluck('id'))
                ->whereBetween('created_at', [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()])
                ->sum('amount'),
        ];
    }

    public function reportsDashboard(Customer $customer, string $period = 'month'): array
    {
        [$start, $end, $label] = $this->periodWindow($period);
        $money = $this->moneyTotals($customer, $start, $end);
        $business = $this->businessTotals($customer, $start->toDateString(), $end->toDateString());
        $goals = PlusGoal::query()->where('customer_id', $customer->id)->get();
        $goalAdded = (float) PlusGoal::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('updated_at', [$start, $end])
            ->sum('saved_amount');

        return [
            'period' => $period,
            'label' => $label,
            'money' => $money,
            'business' => $business,
            'goals_added' => $goalAdded,
            'goals_done' => $goals->filter->isComplete()->count(),
            'goals_total' => $goals->count(),
        ];
    }

    public function homeSummary(Customer $customer): array
    {
        $money = $this->moneyDashboard($customer);
        $business = $this->businessDashboard($customer);
        $goals = $this->goalsDashboard($customer);
        $offers = $this->plus->eligibleOffers($customer);
        $lesson = PlusLesson::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->first();
        $lessonWatched = $lesson && PlusLessonProgress::query()
            ->where('customer_id', $customer->id)
            ->where('plus_lesson_id', $lesson->id)
            ->whereNotNull('completed_at')
            ->exists();

        return [
            'money' => $money,
            'business' => $business,
            'goals' => $goals,
            'offers_count' => $offers->count(),
            'best_offer' => $offers->first(),
            'reward_balance' => $this->plus->rewardBalance($customer),
            'latest_lesson' => $lesson,
            'lesson_watched' => $lessonWatched,
            'upcoming' => $money['upcoming'],
        ];
    }

    public function compactAmount(float $amount): string
    {
        return format_money_compact($amount);
    }

    public function moneyCategoryLabel(?string $key, ?string $otherLabel = null): string
    {
        if (filled($otherLabel)) {
            return (string) $otherLabel;
        }
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        if (! $key) {
            return '—';
        }
        $cats = $this->moneyCategories();
        if (isset($cats[$key])) {
            return $cats[$key][$locale];
        }
        $sources = $this->incomeSources();
        if (isset($sources[$key])) {
            return $sources[$key][$locale];
        }

        return $key === 'other' ? ($cats['other'][$locale] ?? 'Other') : $key;
    }

    public function businessCategoryLabel(?string $key, ?string $otherLabel = null, string $kind = 'spend'): string
    {
        if (filled($otherLabel)) {
            return (string) $otherLabel;
        }
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $set = $kind === 'sale' ? $this->saleTypes() : $this->spendTypes();
        if ($key && isset($set[$key])) {
            return $set[$key][$locale];
        }

        return $key ?: '—';
    }

    /** @return array{in: float, out: float} */
    public function moneyTotals(Customer $customer, Carbon $from, Carbon $to): array
    {
        $rows = PlusMoneyEntry::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return [
            'in' => (float) $rows->sum('inflow'),
            'out' => (float) $rows->sum('outflow'),
        ];
    }

    /** @return array{sold: float, spent: float, difference: float} */
    public function businessTotals(Customer $customer, string $from, string $to): array
    {
        $rows = PlusBusinessEntry::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('entry_date', [$from, $to])
            ->get();
        $sold = (float) $rows->sum('sold');
        $spent = (float) $rows->sum('spent');

        return [
            'sold' => $sold,
            'spent' => $spent,
            'difference' => $sold - $spent,
        ];
    }

    public function hasMoneyToday(Customer $customer): bool
    {
        return PlusMoneyEntry::query()
            ->where('customer_id', $customer->id)
            ->whereDate('entry_date', now()->toDateString())
            ->exists();
    }

    public function hasBusinessToday(Customer $customer): bool
    {
        return PlusBusinessEntry::query()
            ->where('customer_id', $customer->id)
            ->whereDate('entry_date', now()->toDateString())
            ->exists();
    }

    public function hasAnyBusiness(Customer $customer): bool
    {
        return PlusBusinessEntry::query()->where('customer_id', $customer->id)->exists();
    }

    public function hasAnyMoney(Customer $customer): bool
    {
        return PlusMoneyEntry::query()->where('customer_id', $customer->id)->exists();
    }

    public function activeLoan(Customer $customer): ?Loan
    {
        return Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring'])
            ->latest('disbursement_date')
            ->first();
    }

    public function nextRepayment(Customer $customer): ?array
    {
        $loan = $this->activeLoan($customer);
        if (! $loan) {
            return null;
        }
        $servicing = $this->servicing->forLoan($loan);
        $amount = $servicing['next_due_amount'] ?? null;
        $date = $servicing['next_due_date'] ?? null;
        if (! $amount || ! $date) {
            return null;
        }
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return [
            'title' => 'Kopafasta',
            'amount' => (float) $amount,
            'date' => $carbon,
            'url' => route('site.borrower.payments.create', ['loan' => $loan->id]),
        ];
    }

    /** @return list<array{title: string, amount: float, date: Carbon, url?: string}> */
    public function upcoming(Customer $customer): array
    {
        $items = [];
        $repay = $this->nextRepayment($customer);
        if ($repay) {
            $items[] = $repay;
        }
        PlusGoal::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('target_date')
            ->whereNull('completed_at')
            ->where('target_date', '>=', now()->toDateString())
            ->orderBy('target_date')
            ->limit(3)
            ->get()
            ->each(function (PlusGoal $goal) use (&$items) {
                $items[] = [
                    'title' => $goal->title,
                    'amount' => $goal->remaining(),
                    'date' => $goal->target_date,
                    'url' => route('site.borrower.plus.goals'),
                ];
            });

        usort($items, fn ($a, $b) => $a['date'] <=> $b['date']);

        return array_slice($items, 0, 4);
    }

    public function recentRewardEarns(Customer $customer, int $limit = 8): Collection
    {
        return PlusRewardLedger::query()
            ->where('customer_id', $customer->id)
            ->where('points', '>', 0)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** @return list<array{key: string, label: string, amount: float}> */
    private function topSpend(Customer $customer, Carbon $from, Carbon $to): array
    {
        $rows = PlusMoneyEntry::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->where('outflow', '>', 0)
            ->get()
            ->groupBy(fn ($row) => filled($row->other_label) ? 'x:'.$row->other_label : ($row->category ?: 'other'))
            ->map(fn ($group, $key) => [
                'key' => $key,
                'label' => $this->moneyCategoryLabel(
                    $group->first()->category,
                    $group->first()->other_label,
                ),
                'amount' => (float) $group->sum('outflow'),
            ])
            ->sortByDesc('amount')
            ->take(3)
            ->values()
            ->all();

        return $rows;
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function periodWindow(string $period): array
    {
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';

        return match ($period) {
            'week' => [
                now()->copy()->startOfWeek(),
                now()->copy()->endOfWeek(),
                __('plus.reports.this_week'),
            ],
            'year' => [
                now()->copy()->startOfYear(),
                now()->copy()->endOfYear(),
                now()->locale($locale)->translatedFormat('Y'),
            ],
            default => [
                now()->copy()->startOfMonth(),
                now()->copy()->endOfMonth(),
                now()->locale($locale)->translatedFormat('F'),
            ],
        };
    }

    /** @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string} */
    private function businessPeriodWindow(string $period): array
    {
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';

        return match ($period) {
            'week' => [
                now()->copy()->startOfWeek(),
                now()->copy()->endOfWeek(),
                now()->copy()->startOfWeek()->subWeek(),
                now()->copy()->startOfWeek()->subDay(),
                __('plus.business.week'),
            ],
            'month' => [
                now()->copy()->startOfMonth(),
                now()->copy()->endOfMonth(),
                now()->copy()->startOfMonth()->subMonth(),
                now()->copy()->startOfMonth()->subDay(),
                __('plus.business.month'),
            ],
            default => [
                now()->copy()->startOfDay(),
                now()->copy()->endOfDay(),
                now()->copy()->subDay()->startOfDay(),
                now()->copy()->subDay()->endOfDay(),
                __('plus.business.today'),
            ],
        };
    }

    /** @return list<array{label: string, sold: float, spent: float}> */
    private function businessWeekDays(Customer $customer, Carbon $weekStart): array
    {
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $totals = $this->businessTotals($customer, $day->toDateString(), $day->toDateString());
            $days[] = [
                'label' => $day->locale($locale)->isoFormat('dd'),
                'sold' => $totals['sold'],
                'spent' => $totals['spent'],
            ];
        }

        return $days;
    }

    /** @return list<array{label: string, sold: float, spent: float}> */
    private function businessMonthWeeks(Customer $customer, Carbon $monthStart): array
    {
        $weeks = [];
        $cursor = $monthStart->copy()->startOfWeek();
        $monthEnd = $monthStart->copy()->endOfMonth();
        for ($n = 1; $n <= 5; $n++) {
            $from = $cursor->copy();
            $to = $cursor->copy()->endOfWeek();
            if ($to->lt($monthStart)) {
                $cursor->addWeek();
                continue;
            }
            if ($from->lt($monthStart)) {
                $from = $monthStart->copy();
            }
            if ($from->gt($monthEnd)) {
                break;
            }
            if ($to->gt($monthEnd)) {
                $to = $monthEnd->copy();
            }
            $totals = $this->businessTotals($customer, $from->toDateString(), $to->toDateString());
            $weeks[] = [
                'label' => __('plus.business.week_n', ['n' => $n]),
                'sold' => $totals['sold'],
                'spent' => $totals['spent'],
            ];
            $cursor->addWeek();
        }

        return $weeks;
    }

    /** @return list<array{id: int, date: Carbon, kind: string, label: string, amount: float, sold: float, spent: float}> */
    public function businessHistoryRows(Collection $history): array
    {
        $rows = [];
        foreach ($history as $entry) {
            $date = $entry->entry_date ?? now();
            if ((float) $entry->sold > 0) {
                $rows[] = [
                    'id' => $entry->id,
                    'date' => $date,
                    'kind' => 'sale',
                    'label' => $this->businessCategoryLabel($entry->category, $entry->other_label, 'sale'),
                    'amount' => (float) $entry->sold,
                ];
            }
            if ((float) $entry->spent > 0) {
                $rows[] = [
                    'id' => $entry->id.'-out',
                    'date' => $date,
                    'kind' => 'spend',
                    'label' => $this->businessCategoryLabel($entry->category, $entry->other_label, 'spend'),
                    'amount' => (float) $entry->spent,
                ];
            }
        }

        return $rows;
    }
}
