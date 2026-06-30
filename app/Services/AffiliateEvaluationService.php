<?php

namespace App\Services;

use App\Models\AffiliateEvaluation;
use App\Models\AffiliateEvent;
use App\Models\PartnerPayment;
use App\Models\Vendor;
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
        $kpiScore = $this->kpiScore($metrics);
        $riskScore = $this->riskScore($metrics);
        $fraudScore = $this->fraudScore($metrics);
        $recommendation = $this->recommendation($kpiScore, $riskScore, $fraudScore, $metrics);
        $actionTaken = $applyActions && $this->settings()->autoApplyActions()
            ? $this->applyRecommendation($affiliate, $recommendation)
            : 'skipped';

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
                $recommendation = $this->recommendation(
                    $this->kpiScore($metrics),
                    $this->riskScore($metrics),
                    $this->fraudScore($metrics),
                    $metrics,
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

        return [
            'clicks'                     => $clicks,
            'registrations'              => $registrations,
            'applications'               => $applications,
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
     */
    public function recommendation(float $kpi, float $risk, float $fraud, array $metrics): string
    {
        $eventCount = (int) $metrics['clicks'] + (int) $metrics['registrations'] + (int) $metrics['applications'];

        if ($eventCount < $this->settings()->minEventsForScoring()) {
            return 'none';
        }

        if ($fraud >= $this->settings()->suspendFraudScore() || $risk >= $this->settings()->suspendRiskScore()) {
            return 'suspend';
        }

        if ($fraud >= $this->settings()->watchlistFraudScore() || $risk >= $this->settings()->watchlistRiskScore()) {
            return 'watchlist';
        }

        if ($kpi < 30) {
            return 'review';
        }

        return 'none';
    }

    public function applyRecommendation(Vendor $affiliate, string $recommendation): string
    {
        $lifecycle = app(AffiliateLifecycleService::class);
        $current = $lifecycle->statusFor($affiliate);

        if (in_array($current, [AffiliateLifecycleService::TERMINATED, AffiliateLifecycleService::SUSPENDED], true)) {
            return 'skipped';
        }

        return match ($recommendation) {
            'watchlist' => $this->applyWatchlist($affiliate, $lifecycle, $current),
            'suspend'   => $this->applySuspend($affiliate, $lifecycle),
            default     => 'none',
        };
    }

    protected function applyWatchlist(Vendor $affiliate, AffiliateLifecycleService $lifecycle, string $current): string
    {
        if ($current === AffiliateLifecycleService::WATCHLIST) {
            return 'skipped';
        }

        $lifecycle->transition(
            $affiliate,
            AffiliateLifecycleService::WATCHLIST,
            'Automated watchlist from monthly affiliate evaluation.',
        );

        return 'watchlisted';
    }

    protected function applySuspend(Vendor $affiliate, AffiliateLifecycleService $lifecycle): string
    {
        $lifecycle->transition(
            $affiliate,
            AffiliateLifecycleService::SUSPENDED,
            'Automated suspension from monthly affiliate evaluation.',
        );

        return 'suspended';
    }

    /** @param  array<string, int|float>  $metrics */
    protected function notesFor(string $recommendation, array $metrics): string
    {
        return sprintf(
            'Recommendation: %s. Clicks %d, registrations %d, applications %d, dispute rate %.1f%%, duplicate IP clusters %d.',
            $recommendation,
            $metrics['clicks'],
            $metrics['registrations'],
            $metrics['applications'],
            $metrics['dispute_rate'],
            $metrics['duplicate_ip_registrations'],
        );
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

    protected function settings(): AffiliateSettingsService
    {
        return app(AffiliateSettingsService::class);
    }
}
