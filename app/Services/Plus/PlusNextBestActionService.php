<?php

namespace App\Services\Plus;

use App\Models\Customer;
use App\Models\PlusLesson;
use App\Models\PlusLessonProgress;
use App\Models\Setting;

class PlusNextBestActionService
{
    public function __construct(
        private readonly PlusService $plus,
        private readonly PlusWorkspaceService $workspace,
    ) {}

    /** @return array{key: string, eyebrow: string, title: string, body: string, cta_label: string, cta_url: ?string, cta_action: ?string} */
    public function forCustomer(Customer $customer): array
    {
        $priority = $this->priority();
        foreach ($priority as $key) {
            $card = $this->card($customer, $key);
            if ($card) {
                return $card;
            }
        }

        return $this->fallback($customer);
    }

    /** @return list<string> */
    public function priority(): array
    {
        $stored = Setting::get('kopafasta_plus.config');
        $fromSettings = is_array($stored) ? ($stored['nba_priority'] ?? null) : null;

        return is_array($fromSettings) && $fromSettings !== []
            ? array_values($fromSettings)
            : (array) config('kopafasta_plus.nba_priority', []);
    }

    /** @return array<string, mixed>|null */
    private function card(Customer $customer, string $key): ?array
    {
        return match ($key) {
            'repayment_due_soon' => $this->repaymentDueSoon($customer),
            'lesson_published_not_watched' => $this->lessonWaiting($customer),
            'goal_near_completion' => $this->goalNear($customer),
            'no_business_entry_today' => $this->noBusinessToday($customer),
            'no_money_entry_today' => $this->noMoneyToday($customer),
            'reward_available' => $this->rewardAvailable($customer),
            'business_week_improved' => $this->businessImproved($customer),
            'monthly_report_ready' => $this->monthlyReport($customer),
            default => null,
        };
    }

    private function repaymentDueSoon(Customer $customer): ?array
    {
        $next = $this->workspace->nextRepayment($customer);
        if (! $next) {
            return null;
        }
        $days = now()->startOfDay()->diffInDays($next['date']->copy()->startOfDay(), false);
        if ($days < 0 || $days > 3) {
            return null;
        }

        return [
            'key' => 'repayment_due_soon',
            'eyebrow' => __('plus.today.reminder'),
            'title' => __('plus.today.repayment_title'),
            'body' => __('plus.today.repayment_body', [
                'amount' => format_money($next['amount']),
                'day' => $next['date']->locale(app()->getLocale())->isoFormat('dddd'),
            ]),
            'cta_label' => __('plus.today.look'),
            'cta_url' => $next['url'],
            'cta_action' => null,
        ];
    }

    private function lessonWaiting(Customer $customer): ?array
    {
        $lesson = PlusLesson::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->first();
        if (! $lesson) {
            return null;
        }
        $done = PlusLessonProgress::query()
            ->where('customer_id', $customer->id)
            ->where('plus_lesson_id', $lesson->id)
            ->whereNotNull('completed_at')
            ->exists();
        if ($done) {
            return null;
        }
        $title = app()->getLocale() === 'sw' ? ($lesson->title_sw ?: $lesson->title_en) : $lesson->title_en;

        return [
            'key' => 'lesson_published_not_watched',
            'eyebrow' => __('plus.learn.club'),
            'title' => $title,
            'body' => __('plus.today.lesson_body', ['minutes' => $lesson->duration_minutes ?? 7]),
            'cta_label' => __('plus.today.watch'),
            'cta_url' => route('site.borrower.plus.lesson', $lesson),
            'cta_action' => null,
        ];
    }

    private function goalNear(Customer $customer): ?array
    {
        $lead = $this->workspace->goalsDashboard($customer)['lead'] ?? null;
        if (! $lead || $lead->progressPercent() < 70) {
            return null;
        }

        return [
            'key' => 'goal_near_completion',
            'eyebrow' => __('plus.home.goals'),
            'title' => $lead->title,
            'body' => __('plus.today.goal_body', [
                'percent' => $lead->progressPercent(),
                'left' => format_money($lead->remaining()),
            ]),
            'cta_label' => __('plus.today.look'),
            'cta_url' => route('site.borrower.plus.goals'),
            'cta_action' => null,
        ];
    }

    private function noBusinessToday(Customer $customer): ?array
    {
        if (! $this->workspace->hasAnyBusiness($customer) || $this->workspace->hasBusinessToday($customer)) {
            return null;
        }

        return [
            'key' => 'no_business_entry_today',
            'eyebrow' => __('plus.home.business'),
            'title' => __('plus.today.sold_today_title'),
            'body' => __('plus.today.sold_today_body'),
            'cta_label' => __('plus.business.sold_action'),
            'cta_url' => route('site.borrower.plus.business'),
            'cta_action' => 'sale',
        ];
    }

    private function noMoneyToday(Customer $customer): ?array
    {
        if (! $this->workspace->hasAnyMoney($customer) || $this->workspace->hasMoneyToday($customer)) {
            return null;
        }

        return [
            'key' => 'no_money_entry_today',
            'eyebrow' => __('plus.home.money'),
            'title' => __('plus.today.money_streak_title'),
            'body' => __('plus.today.money_streak_body'),
            'cta_label' => __('plus.today.continue'),
            'cta_url' => route('site.borrower.plus.money'),
            'cta_action' => 'out',
        ];
    }

    private function rewardAvailable(Customer $customer): ?array
    {
        $balance = $this->plus->rewardBalance($customer);
        $cheapest = collect($this->plus->rewardCatalog())
            ->pluck('points')
            ->filter()
            ->min();
        if (! $cheapest || $balance < $cheapest) {
            return null;
        }

        return [
            'key' => 'reward_available',
            'eyebrow' => __('plus.home.rewards'),
            'title' => __('plus.today.reward_title', ['points' => $balance]),
            'body' => __('plus.today.reward_body'),
            'cta_label' => __('plus.rewards.use'),
            'cta_url' => route('site.borrower.plus.rewards'),
            'cta_action' => null,
        ];
    }

    private function businessImproved(Customer $customer): ?array
    {
        $dash = $this->workspace->businessDashboard($customer);
        if (! ($dash['week_improved'] ?? false)) {
            return null;
        }

        return [
            'key' => 'business_week_improved',
            'eyebrow' => __('plus.home.business'),
            'title' => __('plus.today.business_up_title'),
            'body' => __('plus.today.business_up_body'),
            'cta_label' => __('plus.today.look'),
            'cta_url' => route('site.borrower.plus.business'),
            'cta_action' => null,
        ];
    }

    private function monthlyReport(Customer $customer): ?array
    {
        if (now()->day < 28) {
            return null;
        }
        if (! $this->workspace->hasAnyMoney($customer) && ! $this->workspace->hasAnyBusiness($customer)) {
            return null;
        }

        return [
            'key' => 'monthly_report_ready',
            'eyebrow' => __('plus.home.reports'),
            'title' => __('plus.today.report_title', ['month' => now()->locale(app()->getLocale())->translatedFormat('F')]),
            'body' => __('plus.today.report_body'),
            'cta_label' => __('plus.today.look'),
            'cta_url' => route('site.borrower.plus.reports'),
            'cta_action' => null,
        ];
    }

    private function fallback(Customer $customer): array
    {
        $name = trim((string) ($customer->first_name ?: $customer->full_name ?: ''));

        return [
            'key' => 'capture_prompt',
            'eyebrow' => __('plus.today.hello', ['name' => $name !== '' ? $name : '']),
            'title' => __('plus.today.capture_title'),
            'body' => __('plus.today.capture_body'),
            'cta_label' => __('plus.money.out_action'),
            'cta_url' => route('site.borrower.plus.money'),
            'cta_action' => 'out',
        ];
    }
}
