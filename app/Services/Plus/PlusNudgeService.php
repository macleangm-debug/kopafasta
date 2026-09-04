<?php

namespace App\Services\Plus;

use App\Models\Customer;
use App\Models\PlusBusinessEntry;
use App\Models\PlusGoal;
use App\Models\PlusMoneyEntry;
use App\Models\PlusSubscription;

class PlusNudgeService
{
    public function __construct(
        private readonly PlusNotificationGate $gate,
        private readonly PlusService $plus,
    ) {}

    public function onGoalCreated(Customer $customer, PlusGoal $goal): void
    {
        if (! $this->plus->isActive($customer)) {
            return;
        }

        $this->gate->notifyOnce($customer, 'goal_created', [
            'name' => $customer->first_name ?: $customer->full_name,
            'title' => $goal->title,
            '_action_url' => route('site.borrower.plus.goals'),
        ], 'goal:'.$goal->id.':created', 8760);
    }

    public function onGoalProgress(Customer $customer, PlusGoal $goal): void
    {
        if (! $this->plus->isActive($customer) || $goal->isPaused()) {
            return;
        }

        $percent = $goal->progressPercent();
        $vars = [
            'name' => $customer->first_name ?: $customer->full_name,
            'title' => $goal->title,
            'percent' => $percent,
            '_action_url' => route('site.borrower.plus.goals'),
        ];

        if ($goal->isComplete() || $percent >= 100) {
            $this->gate->notifyOnce($customer, 'goal_completed', $vars, 'goal:'.$goal->id.':done', 8760);

            return;
        }

        $bucket = match (true) {
            $percent >= 75 => 75,
            $percent >= 50 => 50,
            $percent >= 25 => 25,
            default => null,
        };
        if ($bucket === null) {
            return;
        }

        $this->gate->notifyOnce(
            $customer,
            'goal_progress',
            $vars + ['percent' => $bucket],
            'goal:'.$goal->id.':p'.$bucket,
            8760,
        );
    }

    public function dispatchScheduled(): int
    {
        $sent = 0;
        PlusGoal::query()
            ->where('status', 'active')
            ->whereNull('completed_at')
            ->with('customer.user')
            ->each(function (PlusGoal $goal) use (&$sent) {
                $customer = $goal->customer;
                if (! $customer || ! $this->plus->isActive($customer)) {
                    return;
                }
                $last = $goal->contributions()->latest('id')->first()?->created_at ?? $goal->updated_at ?? $goal->created_at;
                if (! $last || $last->gt(now()->subDays(14))) {
                    return;
                }
                if ($this->gate->notifyOnce($customer, 'goal_no_progress', [
                    'name' => $customer->first_name ?: $customer->full_name,
                    'title' => $goal->title,
                    '_action_url' => route('site.borrower.plus.goals'),
                ], 'goal:'.$goal->id.':wait', 336)) {
                    $sent++;
                }
            });

        $weekKey = now()->format('o-W');
        PlusSubscription::query()
            ->where('status', 'active')
            ->where('expires_at', '>=', now())
            ->with('customer.user')
            ->each(function (PlusSubscription $subscription) use (&$sent, $weekKey) {
                $customer = $subscription->customer;
                if (! $customer || ! $this->plus->isActive($customer)) {
                    return;
                }
                $sent += $this->weeklyBusiness($customer, $weekKey) ? 1 : 0;
                $sent += $this->weeklyMoney($customer, $weekKey) ? 1 : 0;
            });

        return $sent;
    }

    private function weeklyBusiness(Customer $customer, string $weekKey): bool
    {
        $start = now()->startOfWeek();
        $days = PlusBusinessEntry::query()
            ->where('customer_id', $customer->id)
            ->where('entry_date', '>=', $start->toDateString())
            ->pluck('entry_date')
            ->unique()
            ->count();
        if ($days < 1) {
            return false;
        }

        return $this->gate->notifyOnce($customer, 'business_weekly_summary', [
            'name' => $customer->first_name ?: $customer->full_name,
            'days' => $days,
            '_action_url' => route('site.borrower.plus.business'),
        ], 'biz:week:'.$weekKey, 168);
    }

    private function weeklyMoney(Customer $customer, string $weekKey): bool
    {
        $start = now()->startOfWeek();
        $spent = (float) PlusMoneyEntry::query()
            ->where('customer_id', $customer->id)
            ->where('entry_date', '>=', $start->toDateString())
            ->sum('outflow');
        $inflow = (float) PlusMoneyEntry::query()
            ->where('customer_id', $customer->id)
            ->where('entry_date', '>=', $start->toDateString())
            ->sum('inflow');
        if ($spent <= 0 && $inflow <= 0) {
            return false;
        }
        if ($inflow <= 0 || $spent >= $inflow) {
            return false;
        }

        return $this->gate->notifyOnce($customer, 'money_weekly_summary', [
            'name' => $customer->first_name ?: $customer->full_name,
            '_action_url' => route('site.borrower.plus.money'),
        ], 'money:week:'.$weekKey, 168);
    }
}
