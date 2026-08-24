<?php

namespace App\Services\Grades;

use App\Models\Customer;
use App\Models\CustomerGradeEvaluation;
use App\Models\CustomerGradeHistory;
use App\Models\GradeWatchAction;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;

class CustomerGradeEngine
{
    public function __construct(
        private readonly GradeSettings $settings,
        private readonly GradeFactCollector $facts,
    ) {}

    public function evaluate(Customer $customer, string $trigger = 'manual'): CustomerGradeEvaluation
    {
        $rules = $this->settings->rules();
        $facts = $this->facts->collect($customer);
        unset($facts['plus_subscriber'], $facts['plus_active'], $facts['kopafasta_plus']);

        $version = $this->settings->currentVersion();
        $components = $this->components($facts, $rules, $customer);
        $score = (int) min(100, max(0, array_sum($components)));
        $scoreGrade = $this->gradeFromScore($score, $rules);
        [$calculated, $passed, $failed] = $this->applyGates($scoreGrade, $facts, $rules);
        [$integrity, $signals] = $this->integrity($facts, $rules);

        $previous = (string) ($customer->grade ?: 'bronze');
        $status = 'ok';
        $effective = $calculated;
        $reason = 'Evaluated from repayment and relationship facts.';
        $nextReview = now()->addDays((int) ($rules['review_days'] ?? 30));

        if ($this->overrideActive($customer)) {
            $effective = (string) $customer->grade_override;
            $status = 'override';
            $reason = 'Staff override in force.';
        } elseif ($this->rank($calculated) > $this->rank($previous) && in_array($integrity, ['review', 'restricted'], true)) {
            $effective = $previous;
            $status = 'upgrade_held';
            $reason = 'Upgrade held while activity is under review.';
            $nextReview = now()->addDays((int) ($rules['integrity']['upgrade_freeze_days_on_review'] ?? 60));
        } elseif ($this->rank($calculated) > $this->rank($previous) && $integrity === 'watch') {
            $effective = $previous;
            $status = 'watch';
            $reason = 'Another review period is needed before an upgrade.';
            $nextReview = now()->addDays((int) ($rules['integrity']['upgrade_freeze_days_on_watch'] ?? 30));
        } elseif ($this->rank($calculated) < $this->rank($previous)) {
            $graceDays = (int) ($rules['grace_days'][$previous] ?? 14);
            $until = $customer->grade_review_until ? Carbon::parse($customer->grade_review_until) : null;
            if ($this->severeNow($facts)) {
                $effective = $calculated;
                $reason = 'A serious account event changed this status.';
                $customer->grade_review_until = null;
            } elseif ($until && $until->isFuture()) {
                $effective = $previous;
                $status = 'under_review';
                $reason = 'Status under review during the grace period.';
                $nextReview = $until;
            } elseif (! $until) {
                $effective = $previous;
                $status = 'under_review';
                $reason = 'Your status is being reviewed. Keeping your commitments on time helps you maintain a strong Kopafasta status.';
                $nextReview = now()->addDays($graceDays);
                $customer->grade_review_until = $nextReview;
            } else {
                $reason = 'Review period ended and the previous status could not be kept.';
                $customer->grade_review_until = null;
            }
        } else {
            $customer->grade_review_until = null;
            if ($this->rank($calculated) > $this->rank($previous)) {
                $reason = 'Strong repayment history moved your Kopafasta status up.';
            }
        }

        $customer->forceFill([
            'grade' => $effective,
            'calculated_grade' => $calculated,
            'grade_score' => $score,
            'grade_status' => $status,
            'grade_integrity' => $integrity,
            'grade_next_review_at' => $nextReview,
            'grade_rule_version' => $version?->version,
        ])->save();

        $evaluation = CustomerGradeEvaluation::query()->create([
            'customer_id' => $customer->id,
            'rule_version' => $version?->version,
            'trigger' => $trigger,
            'score' => $score,
            'component_scores' => $components,
            'calculated_grade' => $calculated,
            'effective_grade' => $effective,
            'previous_grade' => $previous,
            'grade_status' => $status,
            'integrity_status' => $integrity,
            'facts' => $facts,
            'gates_passed' => $passed,
            'gates_failed' => $failed,
            'integrity_signals' => $signals,
            'reason' => $reason,
            'next_review_at' => $nextReview,
        ]);

        if ($previous !== $effective) {
            CustomerGradeHistory::query()->create([
                'customer_id' => $customer->id,
                'from_grade' => $previous,
                'to_grade' => $effective,
                'event' => $this->rank($effective) > $this->rank($previous) ? 'upgrade' : 'change',
                'rule_version' => $version?->version,
                'reason' => $reason,
                'facts' => $facts,
            ]);
            $this->notify($customer, $previous, $effective, $status);
        }

        return $evaluation;
    }

    public function preview(array $facts, string $country = 'TZ'): array
    {
        unset($facts['plus_subscriber'], $facts['plus_active'], $facts['kopafasta_plus']);
        $customer = new Customer(['country_code' => $country]);
        $rules = $this->settings->rules();
        $components = $this->components($facts, $rules, $customer);
        $score = (int) min(100, max(0, array_sum($components)));
        $scoreGrade = $this->gradeFromScore($score, $rules);
        [$gateGrade, $passed, $failed] = $this->applyGates($scoreGrade, $facts, $rules);
        [$integrity, $signals] = $this->integrity($facts, $rules);

        return compact('score', 'components', 'scoreGrade', 'gateGrade', 'passed', 'failed', 'integrity', 'signals');
    }

    public function backtest(): array
    {
        $counts = ['bronze' => 0, 'silver' => 0, 'gold' => 0, 'platinum' => 0];
        Customer::query()->orderBy('id')->chunk(100, function ($chunk) use (&$counts) {
            foreach ($chunk as $customer) {
                $grade = $this->preview($this->facts->collect($customer), (string) ($customer->country_code ?? 'TZ'))['gateGrade'];
                $counts[$grade] = ($counts[$grade] ?? 0) + 1;
            }
        });

        return $counts;
    }

    private function components(array $facts, array $rules, Customer $customer): array
    {
        $w = $rules['weights'];
        $bands = $this->settings->countryBands((string) ($customer->country_code ?? 'TZ'));

        return [
            'repayment' => $this->ratioPoints((float) ($facts['effective_on_time_ratio'] ?? 100), (int) $w['repayment']),
            'handled_credit' => $this->bandPoints((float) ($facts['lifetime_principal_borrowed'] ?? 0), $bands['lifetime_principal'], (int) $w['handled_credit']),
            'relationship' => $this->daysPoints((int) ($facts['relationship_days'] ?? 0), (int) ($facts['active_relationship_months'] ?? 0), (int) $w['relationship']),
            'current_position' => $this->positionPoints($facts, (int) $w['current_position']),
            'stability' => $this->stabilityPoints($facts, (int) $w['stability']),
            'verification' => (int) round(((int) ($facts['verified_profile_score'] ?? 0) / 100) * (int) $w['verification']),
        ];
    }

    private function gradeFromScore(int $score, array $rules): string
    {
        foreach (['platinum', 'gold', 'silver', 'bronze'] as $grade) {
            if ($score >= (int) $rules['score_bands'][$grade]['min']) {
                return $grade;
            }
        }

        return 'bronze';
    }

    private function applyGates(string $scoreGrade, array $facts, array $rules): array
    {
        $best = 'bronze';
        $passed = $failed = [];
        foreach (['bronze', 'silver', 'gold', 'platinum'] as $grade) {
            [$ok, $okRules, $bad] = $this->gradePasses($grade, $facts, $rules);
            if ($ok && $this->rank($grade) <= $this->rank($scoreGrade)) {
                $best = $grade;
                $passed = array_merge($passed, $okRules);
            } else {
                $failed = array_merge($failed, $bad);
            }
        }

        return [$best, $passed, $failed];
    }

    private function gradePasses(string $grade, array $facts, array $rules): array
    {
        $gate = $rules['gates'][$grade] ?? ['all' => [], 'any_of' => ['count' => 0, 'rules' => []]];
        $passed = $failed = [];
        foreach ($gate['all'] ?? [] as $rule) {
            if ($this->rulePasses($facts, $rule)) {
                $passed[] = $grade.':'.$rule['fact'];
            } else {
                $failed[] = $grade.':'.$rule['fact'];
            }
        }
        $any = $gate['any_of'] ?? ['count' => 0, 'rules' => []];
        $need = (int) ($any['count'] ?? 0);
        $got = 0;
        foreach ($any['rules'] ?? [] as $rule) {
            if ($this->rulePasses($facts, $rule)) {
                $got++;
                $passed[] = $grade.':any:'.$rule['fact'];
            }
        }
        if ($need > 0 && $got < $need) {
            $failed[] = $grade.':any_of';
        }

        return [$failed === [], $passed, $failed];
    }

    private function rulePasses(array $facts, array $rule): bool
    {
        $left = $facts[$rule['fact']] ?? 0;

        return match ($rule['op']) {
            '>=' => $left >= $rule['value'],
            '>' => $left > $rule['value'],
            '<=' => $left <= $rule['value'],
            '<' => $left < $rule['value'],
            '=' => $left == $rule['value'],
            default => false,
        };
    }

    private function integrity(array $facts, array $rules): array
    {
        $cfg = $rules['integrity'];
        $signals = [];
        if ((int) ($facts['tiny_completed_facilities'] ?? 0) >= 3) {
            $signals[] = 'many_tiny_facilities';
        }
        if ((int) ($facts['rapid_cycle_count'] ?? 0) >= (int) $cfg['rapid_cycle_watch_count']) {
            $signals[] = 'rapid_facility_cycling';
        }
        if ((int) ($facts['facilities_opened_recently'] ?? 0) > (int) $cfg['max_qualifying_facilities_per_30_days']) {
            $signals[] = 'too_many_new_facilities';
        }
        if ((int) ($facts['reversed_payments_count'] ?? 0) >= (int) $cfg['reversal_review_count']) {
            $signals[] = 'payment_reversals';
        }
        $status = 'normal';
        if (array_intersect($signals, ['payment_reversals', 'rapid_facility_cycling'])) {
            $status = 'review';
        } elseif ($signals) {
            $status = 'watch';
        }

        return [$status, $signals];
    }

    private function severeNow(array $facts): bool
    {
        return (int) ($facts['defaulted_facilities_count'] ?? 0) > 0
            || (int) ($facts['current_days_past_due'] ?? 0) >= 30;
    }

    public function staffOverride(Customer $customer, string $grade, string $reason, $expiresAt, $actorId = null): CustomerGradeEvaluation
    {
        $grade = strtolower($grade);
        abort_unless(in_array($grade, ['bronze', 'silver', 'gold', 'platinum'], true), 422);
        abort_unless(filled($reason), 422, 'A reason is required.');
        abort_unless($expiresAt, 422, 'An expiry date is required.');

        $customer->forceFill([
            'grade_override' => $grade,
            'grade_override_reason' => $reason,
            'grade_override_by' => $actorId,
            'grade_override_expires_at' => $expiresAt,
        ])->save();

        return $this->evaluate($customer->fresh(), 'staff_override');
    }

    public function applyWatchAction(Customer $customer, string $action, string $reason, $actorId = null): CustomerGradeEvaluation
    {
        abort_unless(filled($reason), 422, 'A reason is required.');
        $from = (string) ($customer->grade_integrity ?: 'normal');
        $to = match ($action) {
            'clear' => 'normal',
            'keep_review' => 'review',
            'restrict' => 'restricted',
            'escalate' => 'review',
            default => $from,
        };

        $customer->forceFill(['grade_integrity' => $to])->save();
        GradeWatchAction::query()->create([
            'customer_id' => $customer->id,
            'actor_user_id' => $actorId,
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'reason' => $reason,
        ]);

        return $this->evaluate($customer->fresh(), 'watch_'.$action);
    }

    private function overrideActive(Customer $customer): bool
    {
        return filled($customer->grade_override)
            && $customer->grade_override_expires_at
            && $customer->grade_override_expires_at->isFuture();
    }

    private function rank(string $grade): int
    {
        return ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4][$grade] ?? 1;
    }

    private function ratioPoints(float $ratio, int $max): int
    {
        return (int) round(($ratio / 100) * $max);
    }

    private function bandPoints(float $amount, array $thresholds, int $max): int
    {
        $steps = max(1, count($thresholds) - 1);
        $hit = 0;
        foreach ($thresholds as $i => $threshold) {
            if ($amount >= (float) $threshold) {
                $hit = $i;
            }
        }

        return (int) round(($hit / $steps) * $max);
    }

    private function daysPoints(int $days, int $activeMonths, int $max): int
    {
        return (int) round((min(1, $days / 730) * 0.7 + min(1, $activeMonths / 18) * 0.3) * $max);
    }

    private function positionPoints(array $facts, int $max): int
    {
        if ((int) ($facts['open_overdue_count'] ?? 0) > 0 || (int) ($facts['current_days_past_due'] ?? 0) > 0) {
            return 0;
        }
        $penalty = min(1, ((int) ($facts['concurrent_facility_count'] ?? 0) + (int) ($facts['facilities_opened_recently'] ?? 0)) / 6);

        return (int) round($max * (1 - ($penalty * 0.4)));
    }

    private function stabilityPoints(array $facts, int $max): int
    {
        $trend = min(1, ((float) ($facts['recent_on_time_ratio'] ?? 100)) / 100);
        if ((int) ($facts['restructured_facilities_count'] ?? 0) > 1 || (int) ($facts['reversed_payments_count'] ?? 0) > 0) {
            $trend *= 0.5;
        }

        return (int) round($trend * $max);
    }

    private function notify(Customer $customer, string $from, string $to, string $status): void
    {
        $code = $this->rank($to) > $this->rank($from) ? 'grade_upgraded' : ($status === 'under_review' ? 'grade_under_review' : 'grade_changed');
        $body = $status === 'under_review'
            ? 'Your Kopafasta status is being reviewed. Keeping your commitments on time helps you maintain a strong status.'
            : ($this->rank($to) > $this->rank($from)
                ? 'You reached '.strtoupper($to).'. Your strong Kopafasta history moved you up.'
                : 'Your current Kopafasta status is '.strtoupper($to).'. You can build back by keeping your commitments.');
        try {
            app(NotificationService::class)->notifyCustomer($customer, $code, [
                'grade' => strtoupper($to),
                '_fallback_body' => $body,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
