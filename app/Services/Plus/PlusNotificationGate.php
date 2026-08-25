<?php

namespace App\Services\Plus;

use App\Models\Customer;
use App\Models\PlusLesson;
use App\Models\PlusLessonProgress;
use App\Models\Setting;
use App\Services\NotificationService;

class PlusNotificationGate
{
    public const PREF_MAP = [
        'plus_monthly_report_ready' => 'plus_learn',
        'plus_lesson_not_started' => 'plus_learn',
        'plus_lesson_unwatched' => 'plus_learn',
        'plus_lesson_completed' => 'plus_learn',
        'learning_new_subject' => 'plus_learn',
        'learning_featured_subject' => 'plus_learn',
        'learning_continue' => 'plus_learn',
        'learning_saved_reminder' => 'plus_learn',
        'monthly_lesson_published' => 'plus_learn',
        'monthly_lesson_reminder' => 'plus_learn',
        'monthly_action_reminder' => 'plus_learn',
        'monthly_action_completed' => 'plus_learn',
        'goal_progress' => 'plus_goals',
        'goal_no_progress' => 'plus_goals',
        'goal_near_target' => 'plus_goals',
        'goal_completed' => 'plus_goals',
        'goal_target_date_approaching' => 'plus_goals',
        'business_daily_capture' => 'plus_business',
        'business_no_activity' => 'plus_business',
        'business_weekly_summary' => 'plus_business',
        'business_monthly_summary' => 'plus_business',
        'sales_milestone' => 'plus_business',
        'money_daily_reminder' => 'payments',
        'money_weekly_summary' => 'payments',
        'unusual_spending_change' => 'payments',
        'recurring_commitment_due' => 'payments',
        'new_eligible_offer' => 'plus_offers',
        'offer_expiring' => 'plus_offers',
        'offer_claimed' => 'plus_offers',
        'reward_earned' => 'plus_offers',
        'reward_available' => 'plus_offers',
        'reward_expiring' => 'plus_offers',
        'milestone_reached' => 'plus_offers',
        'trust_improved' => 'credit_limit_updates',
        'grade_upgraded' => 'credit_limit_updates',
        'grade_under_review' => 'credit_limit_updates',
        'grade_retained' => 'credit_limit_updates',
    ];

    public const ALWAYS_ON = [
        'plus_started',
        'plus_expiring',
        'plus_expired',
        'plus_renewed',
        'repayment_due_soon',
        'repayment_due_today',
        'payment_received',
    ];

    public function __construct(private readonly NotificationService $notifications) {}

    public function notify(Customer $customer, string $code, array $vars = []): bool
    {
        if (! $this->prefAllows($customer, $code)) {
            return false;
        }
        if (! $this->triggerEnabled($code)) {
            return false;
        }
        $this->notifications->notifyCustomer($customer, $code, $vars);

        return true;
    }

    public function prefAllows(Customer $customer, string $code): bool
    {
        if (in_array($code, self::ALWAYS_ON, true)) {
            return true;
        }
        $key = self::PREF_MAP[$code] ?? null;
        if (! $key) {
            return true;
        }
        $prefs = (array) data_get($customer->user?->preferences, 'notifications', []);
        if ($prefs === []) {
            return true;
        }

        return (bool) ($prefs[$key] ?? true);
    }

    public function triggerEnabled(string $code): bool
    {
        $config = Setting::get('kopafasta_plus.notifications');
        if (! is_array($config) || $config === []) {
            return true;
        }
        $row = $config[$code] ?? null;
        if (! is_array($row)) {
            return true;
        }

        return (bool) ($row['active'] ?? true);
    }

    public function lessonStillUnwatched(Customer $customer, PlusLesson $lesson): bool
    {
        return ! PlusLessonProgress::query()
            ->where('customer_id', $customer->id)
            ->where('plus_lesson_id', $lesson->id)
            ->whereNotNull('completed_at')
            ->exists();
    }
}
