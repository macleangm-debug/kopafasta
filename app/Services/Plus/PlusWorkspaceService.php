<?php

namespace App\Services\Plus;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\PlusBusinessEntry;
use App\Models\PlusGoal;
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
            'other' => ['en' => 'Something else', 'sw' => 'Kitu kingine', 'icon' => '🎯'],
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
                ->limit(20)
                ->get(),
            'categories' => $this->moneyCategories(),
            'sources' => $this->incomeSources(),
        ];
    }

    public function businessDashboard(Customer $customer): array
    {
        $today = $this->businessTotals($customer, now()->toDateString(), now()->toDateString());
        $weekStart = now()->copy()->startOfWeek();
        $thisWeek = $this->businessTotals($customer, $weekStart->toDateString(), now()->toDateString());
        $lastWeek = $this->businessTotals(
            $customer,
            $weekStart->copy()->subWeek()->toDateString(),
            $weekStart->copy()->subDay()->toDateString()
        );
        $delta = $thisWeek['sold'] - $lastWeek['sold'];
        $insight = null;
        if ($lastWeek['sold'] > 0 || $thisWeek['sold'] > 0) {
            $insight = $delta >= 0
                ? __('plus.business.insight_up')
                : __('plus.business.insight_down');
        }

        $history = PlusBusinessEntry::query()
            ->where('customer_id', $customer->id)
            ->latest('entry_date')
            ->latest('id')
            ->limit(21)
            ->get();

        $points = $history->sortBy('entry_date')->values()->map(fn ($row) => [
            'date' => $row->entry_date?->format('d M'),
            'sold' => (float) $row->sold,
            'spent' => (float) $row->spent,
        ]);

        return [
            'today' => $today,
            'week' => $thisWeek,
            'last_week' => $lastWeek,
            'week_improved' => $lastWeek['sold'] > 0 && $thisWeek['sold'] > $lastWeek['sold'],
            'insight' => $insight,
            'history' => $history,
            'points' => $points,
        ];
    }

    public function goalsDashboard(Customer $customer): array
    {
        $goals = PlusGoal::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->get();

        $active = $goals->filter(fn (PlusGoal $g) => ! $g->isComplete() && ! $g->isPaused());
        $lead = $active->sortByDesc(fn (PlusGoal $g) => $g->progressPercent())->first();

        return [
            'goals' => $goals,
            'active' => $active,
            'lead' => $lead,
            'kinds' => $this->goalKinds(),
            'contributed_this_month' => (float) PlusGoal::query()
                ->where('customer_id', $customer->id)
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->get()
                ->sum(fn (PlusGoal $g) => (float) $g->saved_amount > 0 ? min((float) $g->saved_amount, (float) $g->target_amount) : 0),
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
        $abs = abs($amount);
        if ($abs >= 1_000_000) {
            $value = number_format($abs / 1_000_000, 1);
            $value = rtrim(rtrim($value, '0'), '.');

            return ($amount < 0 ? '−' : '').'TZS '.$value.'m';
        }

        return format_money($amount);
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
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $rows = PlusMoneyEntry::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->where('outflow', '>', 0)
            ->get()
            ->groupBy(fn ($row) => $row->category ?: 'other')
            ->map(fn ($group, $key) => [
                'key' => $key,
                'label' => $this->moneyCategories()[$key][$locale] ?? $this->moneyCategories()['other'][$locale],
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
}
