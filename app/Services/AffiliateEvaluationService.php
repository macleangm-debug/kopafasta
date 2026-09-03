<?php

namespace App\Services;

use App\Models\AffiliateEvaluation;
use App\Models\AffiliateEvent;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\PartnerPayment;
use App\Models\Vendor;
use App\Support\AffiliatePerformanceStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AffiliateEvaluationService
{
    public function evaluatePartner(
        Vendor $affiliate,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
        bool $applyActions = false,
    ): AffiliateEvaluation {
        abort_unless($affiliate->isAffiliate(), 422);

        $periodEnd ??= now()->endOfDay();
        $periodStart ??= $periodEnd->copy()->subDays($this->settings()->evaluationPeriodDays())->startOfDay();

        $metrics = $this->metricsForPeriod($affiliate, $periodStart, $periodEnd);
        $volume = $this->volumeProgress($affiliate, $metrics, $periodStart, $periodEnd);
        if ($affiliate->isPremiumAffiliate()) {
            $volume['missed'] = false;
            $volume['consecutive_misses'] = 0;
        }
        $metrics['monthly_registration_target'] = $volume['target'];
        $metrics['volume_missed'] = $volume['missed'] ? 1 : 0;
        $metrics['volume_consecutive_misses'] = $volume['consecutive_misses'];
        $metrics['policy_version'] = $this->settings()->policyVersion();
        $metrics['kpi_results'] = $this->kpiResults($metrics);

        $kpiScore = $this->kpiScore($metrics);
        $riskScore = $this->riskScore($metrics);
        $fraudScore = $this->fraudScore($metrics);
        $recommendation = $this->recommendation($kpiScore, $riskScore, $fraudScore, $metrics, $volume);
        $performanceStatus = $this->resolvePerformanceStatus($affiliate, $volume, $kpiScore, $recommendation);
        $metrics['performance_status'] = $performanceStatus;

        $previousPerformance = (string) ($affiliate->affiliate_performance_status ?? '');
        $affiliate->update(['affiliate_performance_status' => $performanceStatus]);

        $actionTaken = $applyActions && $this->settings()->autoApplyActions()
            ? $this->applyRecommendation($affiliate->fresh(), $recommendation, $volume, $fraudScore, $riskScore)
            : 'skipped';

        if ($applyActions && $volume['missed'] && $volume['consecutive_misses'] >= $this->settings()->volumeMissesBeforeNudge()) {
            $this->nudgeVolume($affiliate->fresh(), $volume, $periodStart, $periodEnd, $performanceStatus);
        }

        if ($applyActions && $this->settings()->autoRecover()
            && $previousPerformance === AffiliatePerformanceStatus::SUSPENDED
            && $performanceStatus !== AffiliatePerformanceStatus::SUSPENDED
            && ! $volume['missed']) {
            $this->notifyRecovered($affiliate->fresh(), $volume, $periodStart, $periodEnd);
            if ($actionTaken === 'none' || $actionTaken === 'skipped') {
                $actionTaken = 'recovered';
            }
        }

        $this->storeVolumeMetadata($affiliate->fresh(), $volume);

        $evaluation = AffiliateEvaluation::create([
            'partner_id'     => $affiliate->id,
            'period_start'   => $periodStart->toDateString(),
            'period_end'     => $periodEnd->toDateString(),
            'kpi_score'      => $kpiScore,
            'risk_score'     => $riskScore,
            'fraud_score'    => $fraudScore,
            'recommendation' => $recommendation,
            'action_taken'   => $actionTaken,
            'metrics'        => $metrics,
            'notes'          => $this->notesFor($recommendation, $metrics),
            'evaluated_at'   => now(),
        ]);

        $affiliate->update([
            'affiliate_evaluation_snapshot' => [
                'evaluation_id'  => $evaluation->id,
                'period_start'   => $periodStart->toDateString(),
                'period_end'     => $periodEnd->toDateString(),
                'kpi_score'      => $kpiScore,
                'risk_score'     => $riskScore,
                'fraud_score'    => $fraudScore,
                'recommendation' => $recommendation,
                'volume_target'  => $volume['target'],
                'volume_registrations' => $volume['registrations'],
                'volume_consecutive_misses' => $volume['consecutive_misses'],
                'performance_status' => $performanceStatus,
                'policy_version' => $metrics['policy_version'],
                'kpi_results' => $metrics['kpi_results'],
                'evaluated_at'   => now()->toIso8601String(),
            ],
        ]);

        return $evaluation;
    }

    /**
     * @return array{evaluated: int, watchlisted: int, suspended: int, skipped: int}
     */
    public function evaluateAll(
        ?Carbon $periodEnd = null,
        bool $dryRun = false,
        bool $applyActions = false,
    ): array {
        $periodEnd ??= now()->endOfDay();
        $counts = ['evaluated' => 0, 'watchlisted' => 0, 'suspended' => 0, 'skipped' => 0];

        $affiliates = Vendor::query()
            ->where('category', 'affiliate')
            ->where('status', '!=', 'inactive')
            ->get();

        foreach ($affiliates as $affiliate) {
            if ($dryRun) {
                $metrics = $this->metricsForPeriod(
                    $affiliate,
                    $periodEnd->copy()->subDays($this->settings()->evaluationPeriodDays())->startOfDay(),
                    $periodEnd,
                );
                $periodStart = $periodEnd->copy()->subDays($this->settings()->evaluationPeriodDays())->startOfDay();
                $recommendation = $this->recommendation(
                    $this->kpiScore($metrics),
                    $this->riskScore($metrics),
                    $this->fraudScore($metrics),
                    $metrics,
                    $this->volumeProgress($affiliate, $metrics, $periodStart, $periodEnd),
                );
                $counts['evaluated']++;
                if ($recommendation === 'watchlist') {
                    $counts['watchlisted']++;
                } elseif ($recommendation === 'suspend') {
                    $counts['suspended']++;
                } else {
                    $counts['skipped']++;
                }

                continue;
            }

            $evaluation = $this->evaluatePartner($affiliate, applyActions: $applyActions);
            $counts['evaluated']++;

            match ($evaluation->action_taken) {
                'watchlisted' => $counts['watchlisted']++,
                'suspended'   => $counts['suspended']++,
                default       => $counts['skipped']++,
            };
        }

        if (! $dryRun) {
            $this->updateLeaderboardRanks();
        }

        return $counts;
    }

    /** @return array<string, int|float> */
    public function metricsForPeriod(Vendor $affiliate, Carbon $start, Carbon $end): array
    {
        $events = AffiliateEvent::query()
            ->where('partner_id', $affiliate->id)
            ->whereBetween('created_at', [$start, $end]);

        $clicks = (int) (clone $events)->where('event_type', 'click')->count();
        $registrations = (int) (clone $events)->where('event_type', 'registration')->count();
        $applications = (int) (clone $events)->where('event_type', 'application')->count();

        $commissionsTotal = (float) (clone $events)
            ->where('event_type', 'like', 'commission_%')
            ->sum('commission_amount');

        $paymentsBase = PartnerPayment::query()
            ->where('partner_id', $affiliate->id)
            ->where('source_type', AffiliateCommissionWalletService::SOURCE_TYPE)
            ->whereBetween('created_at', [$start, $end]);

        $commissionPayments = (int) (clone $paymentsBase)->count();
        $disputedPayments = (int) (clone $paymentsBase)->where('status', 'disputed')->count();

        $duplicateIpRegistrations = (int) DB::table('affiliate_events')
            ->selectRaw('ip_address, count(*) as total')
            ->where('partner_id', $affiliate->id)
            ->where('event_type', 'registration')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->havingRaw('count(*) > ?', [$this->settings()->duplicateIpRegistrationThreshold()])
            ->count();

        $clickToRegRate = $clicks > 0 ? round(($registrations / $clicks) * 100, 2) : 0.0;
        $regToAppRate = $registrations > 0 ? round(($applications / $registrations) * 100, 2) : 0.0;
        $disputeRate = $commissionPayments > 0
            ? round(($disputedPayments / $commissionPayments) * 100, 2)
            : 0.0;

        $loanFacts = $this->loanFactsForPeriod($affiliate, $start, $end);

        return [
            'clicks'                     => $clicks,
            'registrations'              => $registrations,
            'applications'               => $applications,
            'approved_loans'             => $loanFacts['approved_loans'],
            'disbursed_loans'            => $loanFacts['disbursed_loans'],
            'disbursed_value'            => $loanFacts['disbursed_value'],
            'commissions_total'        => $commissionsTotal,
            'commission_payments'      => $commissionPayments,
            'disputed_payments'          => $disputedPayments,
            'dispute_rate'               => $disputeRate,
            'duplicate_ip_registrations' => $duplicateIpRegistrations,
            'click_to_reg_rate'          => $clickToRegRate,
            'reg_to_app_rate'            => $regToAppRate,
        ];
    }

    /** @param  array<string, int|float>  $metrics */
    public function kpiScore(array $metrics): float
    {
        $weights = $this->settings()->evaluationWeights();

        $volume = min(100, ($metrics['registrations'] * 8) + ($metrics['applications'] * 15));
        $conversion = min(100, ($metrics['click_to_reg_rate'] * 0.45) + ($metrics['reg_to_app_rate'] * 0.55));
        $commission = min(100, $metrics['commissions_total'] / 1000);

        return round(
            ($volume * $weights['volume'])
            + ($conversion * $weights['conversion'])
            + ($commission * $weights['commission']),
            2,
        );
    }

    /** @param  array<string, int|float>  $metrics */
    public function riskScore(array $metrics): float
    {
        $score = 0.0;

        if ($metrics['clicks'] >= $this->settings()->highClickThreshold()
            && $metrics['click_to_reg_rate'] < $this->settings()->lowConversionThreshold()) {
            $score += 35;
        }

        if ($metrics['registrations'] >= 10 && $metrics['reg_to_app_rate'] < 10) {
            $score += 25;
        }

        $score += min(30, $metrics['dispute_rate']);

        if ($metrics['clicks'] >= 5 && $metrics['registrations'] === 0) {
            $score += 20;
        }

        return round(min(100, $score), 2);
    }

    /** @param  array<string, int|float>  $metrics */
    public function fraudScore(array $metrics): float
    {
        $score = min(60, $metrics['duplicate_ip_registrations'] * 25);
        $score += min(40, $metrics['dispute_rate'] * 1.5);

        return round(min(100, $score), 2);
    }

    /**
     * @param  array<string, int|float>  $metrics
     * @param  array{target: int, registrations: int, missed: bool, consecutive_misses: int, onboarding: bool}  $volume
     */
    public function recommendation(float $kpi, float $risk, float $fraud, array $metrics, array $volume = []): string
    {
        $eventCount = (int) $metrics['clicks'] + (int) $metrics['registrations'] + (int) $metrics['applications'];
        $volumeMisses = (int) ($volume['consecutive_misses'] ?? 0);

        if ($eventCount < $this->settings()->minEventsForScoring()
            && $volumeMisses < $this->settings()->volumeMissesBeforeNudge()) {
            return 'none';
        }

        if ($fraud >= $this->settings()->suspendFraudScore() || $risk >= $this->settings()->suspendRiskScore()) {
            return 'suspend';
        }

        if ($volumeMisses >= $this->settings()->volumeMissesBeforeSuspend()) {
            return 'suspend';
        }

        if ($fraud >= $this->settings()->watchlistFraudScore() || $risk >= $this->settings()->watchlistRiskScore()) {
            return 'watchlist';
        }

        if ($volumeMisses >= $this->settings()->volumeMissesBeforeWatchlist()) {
            return 'watchlist';
        }

        if ($kpi < 30 || (($volume['missed'] ?? false) && $volumeMisses >= $this->settings()->volumeMissesBeforeNudge())) {
            return 'review';
        }

        return 'none';
    }

    /**
     * @param  array<string, int|float>  $metrics
     * @return array{target: int, registrations: int, missed: bool, consecutive_misses: int, onboarding: bool}
     */
    public function volumeProgress(Vendor $affiliate, array $metrics, Carbon $periodStart, ?Carbon $periodEnd = null): array
    {
        $periodEnd ??= $periodStart->copy()->addDays($this->settings()->evaluationPeriodDays());
        $periodKey = $periodEnd->toDateString();
        $target = $this->settings()->monthlyRegistrationTarget();
        $registrations = (int) ($metrics['registrations'] ?? 0);
        $minActiveDays = $this->settings()->volumeMinActiveDays();
        $anchor = $affiliate->membership_started_at ?: $affiliate->created_at;
        $onboarding = $minActiveDays > 0
            && $anchor
            && $anchor->gt(now()->subDays($minActiveDays));
        $missed = $target > 0 && ! $onboarding && $registrations < $target;
        if ($affiliate->isPremiumAffiliate()) {
            $missed = false;
        }

        $meta = is_array($affiliate->metadata) ? $affiliate->metadata : [];
        $previous = (int) (data_get($meta, 'affiliate_volume.consecutive_misses') ?? 0);
        $lastPeriod = (string) (data_get($meta, 'affiliate_volume.period_end') ?? '');
        $consecutive = 0;
        if ($missed) {
            $consecutive = $lastPeriod === $periodKey ? $previous : $previous + 1;
        }

        return [
            'target' => $target,
            'registrations' => $registrations,
            'missed' => $missed,
            'consecutive_misses' => $consecutive,
            'onboarding' => $onboarding,
            'period_end' => $periodKey,
        ];
    }

    /** @return array{target: int, registrations: int, missed: bool, consecutive_misses: int, onboarding: bool} */
    public function currentVolumeProgress(Vendor $affiliate): array
    {
        $end = now()->endOfDay();
        $start = $end->copy()->subDays($this->settings()->evaluationPeriodDays())->startOfDay();
        $metrics = $this->metricsForPeriod($affiliate, $start, $end);
        $volume = $this->volumeProgress($affiliate, $metrics, $start, $end);
        $meta = is_array($affiliate->metadata) ? $affiliate->metadata : [];
        $volume['consecutive_misses'] = (int) (data_get($meta, 'affiliate_volume.consecutive_misses') ?? 0);
        $volume['missed'] = $affiliate->isPremiumAffiliate()
            ? false
            : ($volume['target'] > 0 && ! $volume['onboarding'] && $volume['registrations'] < $volume['target']);

        return $volume;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function volumeBoard(): Collection
    {
        return Vendor::query()
            ->where('category', 'affiliate')
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get()
            ->map(function (Vendor $affiliate): array {
                $volume = $this->currentVolumeProgress($affiliate);
                $label = $affiliate->isPremiumAffiliate()
                    ? AffiliatePerformanceStatus::label(AffiliatePerformanceStatus::PREMIUM)
                    : ($volume['onboarding']
                        ? AffiliatePerformanceStatus::label(AffiliatePerformanceStatus::RAMP_UP)
                        : ($volume['missed']
                            ? AffiliatePerformanceStatus::label(AffiliatePerformanceStatus::NEEDS_ATTENTION)
                            : AffiliatePerformanceStatus::label(AffiliatePerformanceStatus::GOOD_STANDING)));

                return array_merge($volume, [
                    'partner' => $affiliate,
                    'label' => $label,
                ]);
            })
            ->values();
    }

    /** @param  array{target: int, registrations: int, missed: bool, consecutive_misses: int, onboarding: bool}  $volume */
    protected function storeVolumeMetadata(Vendor $affiliate, array $volume): void
    {
        $meta = is_array($affiliate->metadata) ? $affiliate->metadata : [];
        $meta['affiliate_volume'] = [
            'target' => $volume['target'],
            'registrations' => $volume['registrations'],
            'consecutive_misses' => $volume['consecutive_misses'],
            'missed' => $volume['missed'],
            'period_end' => $volume['period_end'] ?? now()->toDateString(),
            'reviewed_at' => now()->toIso8601String(),
        ];
        $affiliate->update(['metadata' => $meta]);
    }

    /** @param  array{target: int, registrations: int, consecutive_misses: int}  $volume */
    protected function nudgeVolume(
        Vendor $affiliate,
        array $volume,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
        string $status = AffiliatePerformanceStatus::NEEDS_ATTENTION,
    ): void {
        $periodStart ??= now()->subDays($this->settings()->evaluationPeriodDays());
        $periodEnd ??= now();
        $left = max(0, $this->settings()->volumeMissesBeforeSuspend() - $volume['consecutive_misses']);
        $required = (int) $volume['target'];
        $achieved = (int) $volume['registrations'];
        $period = $periodStart->format('d M Y').' – '.$periodEnd->format('d M Y');
        $locale = $this->partnerLocale($affiliate);
        $statusLabel = AffiliatePerformanceStatus::label($status, $locale);

        app(NotificationService::class)->notifyPartner($affiliate, 'affiliate_volume_warning', [
            'partner' => $affiliate->name,
            'registrations' => (string) $achieved,
            'target' => (string) $required,
            'remaining' => (string) $left,
            'period' => $period,
            'status' => $statusLabel,
            '_fallback_subject' => trans('site.affiliate_portal.warning_subject', [], $locale),
            '_fallback_body' => trans('site.affiliate_portal.warning_body', [
                'period' => $period,
                'required' => $required,
                'achieved' => $achieved,
                'status' => $statusLabel,
            ], $locale),
        ]);
    }

    public function applyRecommendation(
        Vendor $affiliate,
        string $recommendation,
        array $volume = [],
        float $fraud = 0,
        float $risk = 0,
    ): string {
        $lifecycle = app(AffiliateLifecycleService::class);
        $current = $lifecycle->statusFor($affiliate);

        if (in_array($current, [AffiliateLifecycleService::TERMINATED, AffiliateLifecycleService::SUSPENDED], true)) {
            return 'skipped';
        }

        $fraudSuspend = $fraud >= $this->settings()->suspendFraudScore()
            || $risk >= $this->settings()->suspendRiskScore();
        $volumeSuspend = ! $affiliate->isPremiumAffiliate()
            && (int) ($volume['consecutive_misses'] ?? 0) >= $this->settings()->volumeMissesBeforeSuspend()
            && ! ($volume['onboarding'] ?? false);

        if ($recommendation === 'suspend' && $fraudSuspend) {
            return $this->applyComplianceSuspend($affiliate, $lifecycle, $fraud, $risk);
        }

        if ($recommendation === 'suspend' && $volumeSuspend) {
            return $this->applyPerformanceSuspend($affiliate, $volume);
        }

        if ($recommendation === 'watchlist' && $fraudSuspend === false && ($fraud >= $this->settings()->watchlistFraudScore() || $risk >= $this->settings()->watchlistRiskScore())) {
            return $this->applyWatchlist($affiliate, $lifecycle, $current);
        }

        return 'none';
    }

    protected function applyWatchlist(Vendor $affiliate, AffiliateLifecycleService $lifecycle, string $current): string
    {
        if ($current === AffiliateLifecycleService::WATCHLIST) {
            return 'skipped';
        }

        $lifecycle->transition(
            $affiliate,
            AffiliateLifecycleService::WATCHLIST,
            'Automated watchlist from affiliate evaluation (risk/fraud). Policy v'.$this->settings()->policyVersion().'.',
        );

        return 'watchlisted';
    }

    protected function applyComplianceSuspend(Vendor $affiliate, AffiliateLifecycleService $lifecycle, float $fraud, float $risk): string
    {
        $lifecycle->transition(
            $affiliate,
            AffiliateLifecycleService::SUSPENDED,
            'Suspended — compliance/fraud threshold reached (fraud '.$fraud.', risk '.$risk.'). Policy v'.$this->settings()->policyVersion().'.',
        );

        return 'suspended';
    }

    /** @param  array{target?: int, registrations?: int, consecutive_misses?: int}  $volume */
    protected function applyPerformanceSuspend(Vendor $affiliate, array $volume): string
    {
        $required = (int) ($volume['target'] ?? $this->settings()->monthlyRegistrationTarget());
        $actual = (int) ($volume['registrations'] ?? 0);
        $misses = (int) ($volume['consecutive_misses'] ?? 0);
        $reason = 'Suspended — Quarterly performance requirement not met for '.$misses
            .' consecutive assessment periods. Required qualified referrals: '.$required
            .'. Actual: '.$actual.'. Policy v'.$this->settings()->policyVersion().'.';

        $affiliate->update([
            'affiliate_performance_status' => AffiliatePerformanceStatus::SUSPENDED,
            'affiliate_lifecycle_note' => $reason,
        ]);

        $locale = $this->partnerLocale($affiliate);
        app(NotificationService::class)->notifyPartner($affiliate, 'affiliate_performance_suspended', [
            'partner' => $affiliate->name,
            'required' => (string) $required,
            'actual' => (string) $actual,
            'misses' => (string) $misses,
            '_fallback_subject' => trans('site.affiliate_portal.suspended_subject', [], $locale),
            '_fallback_body' => trans('site.affiliate_portal.suspended_notice', [
                'required' => $required,
                'actual' => $actual,
                'misses' => $misses,
            ], $locale),
        ]);

        return 'suspended';
    }

    protected function notifyRecovered(Vendor $affiliate, array $volume, Carbon $periodStart, Carbon $periodEnd): void
    {
        $period = $periodStart->format('d M Y').' – '.$periodEnd->format('d M Y');
        $locale = $this->partnerLocale($affiliate);
        app(NotificationService::class)->notifyPartner($affiliate, 'affiliate_performance_recovered', [
            'partner' => $affiliate->name,
            'registrations' => (string) ($volume['registrations'] ?? 0),
            'target' => (string) ($volume['target'] ?? 0),
            '_fallback_subject' => trans('site.affiliate_portal.recovered_subject', [], $locale),
            '_fallback_body' => trans('site.affiliate_portal.recovered_body', ['period' => $period], $locale),
        ]);
    }

    /** @param  array<string, int|float>  $metrics */
    protected function notesFor(string $recommendation, array $metrics): string
    {
        $note = sprintf(
            'Recommendation: %s. Policy v%s. Clicks %d, qualified referrals %d, applications %d, disbursed %d, dispute rate %.1f%%.',
            $recommendation,
            $metrics['policy_version'] ?? $this->settings()->policyVersion(),
            $metrics['clicks'],
            $metrics['registrations'],
            $metrics['applications'],
            (int) ($metrics['disbursed_loans'] ?? 0),
            $metrics['dispute_rate'],
        );

        if ((int) ($metrics['volume_missed'] ?? 0) === 1) {
            $note .= sprintf(
                ' Volume: %d referrals vs target %d (%d missed period(s)).',
                $metrics['registrations'],
                (int) ($metrics['monthly_registration_target'] ?? 0),
                (int) ($metrics['volume_consecutive_misses'] ?? 0),
            );
        }

        return $note;
    }

    public function updateLeaderboardRanks(): void
    {
        Vendor::query()
            ->where('category', 'affiliate')
            ->update(['affiliate_leaderboard_rank' => null]);

        $ranked = Vendor::query()
            ->where('category', 'affiliate')
            ->whereNotNull('affiliate_evaluation_snapshot')
            ->get()
            ->sortByDesc(fn (Vendor $row) => (float) ($row->affiliate_evaluation_snapshot['kpi_score'] ?? 0))
            ->values();

        foreach ($ranked as $index => $affiliate) {
            $affiliate->update(['affiliate_leaderboard_rank' => $index + 1]);
        }
    }

    /** @return Collection<int, Vendor> */
    public function leaderboard(int $limit = 10): Collection
    {
        return Vendor::query()
            ->where('category', 'affiliate')
            ->whereNotNull('affiliate_leaderboard_rank')
            ->orderBy('affiliate_leaderboard_rank')
            ->limit($limit)
            ->get();
    }

    /**
     * Live standing for dashboards — same engine as the formal assessment.
     *
     * @return array<string, mixed>
     */
    public function currentStanding(Vendor $affiliate): array
    {
        $end = now()->endOfDay();
        $start = $end->copy()->subDays($this->settings()->evaluationPeriodDays())->startOfDay();
        $metrics = $this->metricsForPeriod($affiliate, $start, $end);
        $volume = $this->volumeProgress($affiliate, $metrics, $start, $end);
        $metrics['monthly_registration_target'] = $volume['target'];
        $kpiScore = $this->kpiScore($metrics);
        $status = $affiliate->isPremiumAffiliate()
            ? AffiliatePerformanceStatus::PREMIUM
            : (string) ($affiliate->affiliate_performance_status
                ?: $this->resolvePerformanceStatus($affiliate, $volume, $kpiScore, 'none'));
        $results = $this->kpiResults($metrics);
        $needed = $affiliate->isPremiumAffiliate()
            ? 0
            : max(0, (int) $volume['target'] - (int) $volume['registrations']);

        return [
            'status' => $status,
            'status_label' => AffiliatePerformanceStatus::label($status),
            'score' => $kpiScore,
            'period_start' => $start,
            'period_end' => $end,
            'volume' => $volume,
            'kpi_results' => $results,
            'needed_referrals' => $needed,
            'policy_version' => $this->settings()->policyVersion(),
            'premium' => $affiliate->isPremiumAffiliate(),
            'next_action' => $this->nextActionCopy($status, $volume, $needed, $end),
        ];
    }

    /**
     * @param  array<string, int|float>  $metrics
     * @return list<array{key: string, label: string, actual: float, target: float, met: bool, enabled: bool}>
     */
    public function kpiResults(array $metrics): array
    {
        $actuals = [
            'qualified_referrals' => (float) ($metrics['registrations'] ?? 0),
            'applications' => (float) ($metrics['applications'] ?? 0),
            'disbursed_loans' => (float) ($metrics['disbursed_loans'] ?? 0),
            'conversion' => (float) ($metrics['reg_to_app_rate'] ?? 0),
        ];
        $labels = [
            'qualified_referrals' => __('site.affiliate_portal.kpi_qualified_referrals'),
            'applications' => __('site.affiliate_portal.kpi_applications'),
            'disbursed_loans' => __('site.affiliate_portal.kpi_disbursed'),
            'conversion' => __('site.affiliate_portal.kpi_conversion'),
        ];

        $rows = [];
        foreach ($this->settings()->kpiCatalog() as $key => $kpi) {
            $actual = $actuals[$key] ?? 0;
            $target = (float) ($kpi['target'] ?? 0);
            $rows[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'actual' => $actual,
                'target' => $target,
                'met' => ! ($kpi['enabled'] ?? false) || $target <= 0 || $actual >= $target,
                'enabled' => (bool) ($kpi['enabled'] ?? false),
            ];
        }

        return $rows;
    }

    /**
     * @param  array{target: int, registrations: int, missed: bool, consecutive_misses: int, onboarding: bool}  $volume
     */
    public function resolvePerformanceStatus(Vendor $affiliate, array $volume, float $kpiScore, string $recommendation): string
    {
        if ($affiliate->isPremiumAffiliate()) {
            return AffiliatePerformanceStatus::PREMIUM;
        }

        if ($volume['onboarding']) {
            return AffiliatePerformanceStatus::RAMP_UP;
        }

        $misses = (int) $volume['consecutive_misses'];
        if ($recommendation === 'suspend' || $misses >= $this->settings()->volumeMissesBeforeSuspend()) {
            return AffiliatePerformanceStatus::SUSPENDED;
        }
        if ($misses >= $this->settings()->volumeMissesBeforeWatchlist()) {
            return AffiliatePerformanceStatus::AT_RISK;
        }
        if ($volume['missed'] || $misses >= $this->settings()->volumeMissesBeforeNudge()) {
            return AffiliatePerformanceStatus::NEEDS_ATTENTION;
        }
        if ($kpiScore >= 85) {
            return AffiliatePerformanceStatus::EXCELLENT;
        }

        return AffiliatePerformanceStatus::GOOD_STANDING;
    }

    /** @return array{approved_loans: int, disbursed_loans: int, disbursed_value: float} */
    protected function loanFactsForPeriod(Vendor $affiliate, Carbon $start, Carbon $end): array
    {
        $customerIds = Customer::query()
            ->where('affiliate_partner_id', $affiliate->id)
            ->pluck('id');

        if ($customerIds->isEmpty()) {
            return ['approved_loans' => 0, 'disbursed_loans' => 0, 'disbursed_value' => 0.0];
        }

        $approved = LoanApplication::query()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', ['approved', 'pre_approved', 'awaiting_offer', 'disbursed'])
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        $disbursedQuery = Loan::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNotNull('disbursement_date')
            ->whereBetween('disbursement_date', [$start->toDateString(), $end->toDateString()]);

        return [
            'approved_loans' => $approved,
            'disbursed_loans' => (clone $disbursedQuery)->count(),
            'disbursed_value' => (float) (clone $disbursedQuery)->sum('principal_amount'),
        ];
    }

    /**
     * @param  array{consecutive_misses?: int, target?: int}  $volume
     */
    protected function nextActionCopy(string $status, array $volume, int $needed, Carbon $periodEnd): string
    {
        if ($status === AffiliatePerformanceStatus::PREMIUM) {
            return __('site.affiliate_portal.next_action_premium');
        }
        if ($status === AffiliatePerformanceStatus::SUSPENDED) {
            return __('site.affiliate_portal.next_action_suspended');
        }
        if ($needed > 0 && in_array($status, [AffiliatePerformanceStatus::NEEDS_ATTENTION, AffiliatePerformanceStatus::AT_RISK, AffiliatePerformanceStatus::RAMP_UP, AffiliatePerformanceStatus::GOOD_STANDING], true)) {
            return __('site.affiliate_portal.needed_by', [
                'count' => $needed,
                'date' => $periodEnd->format('d M Y'),
            ]);
        }
        if ($status === AffiliatePerformanceStatus::AT_RISK) {
            return __('site.affiliate_portal.next_action_at_risk');
        }

        return __('site.affiliate_portal.next_action_none');
    }

    protected function partnerLocale(Vendor $affiliate): string
    {
        $locale = $affiliate->user?->locale ?? app()->getLocale();

        return in_array($locale, ['en', 'sw'], true) ? $locale : app()->getLocale();
    }

    protected function settings(): AffiliateSettingsService
    {
        return app(AffiliateSettingsService::class);
    }

    public function syncPremiumStanding(Vendor $affiliate): void
    {
        if (! $affiliate->isPremiumAffiliate()) {
            return;
        }

        if ($affiliate->affiliate_performance_status === AffiliatePerformanceStatus::PREMIUM) {
            return;
        }

        $affiliate->update([
            'affiliate_performance_status' => AffiliatePerformanceStatus::PREMIUM,
        ]);
    }
}
