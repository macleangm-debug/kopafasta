<?php

namespace App\Console\Commands;

use App\Services\AffiliateEvaluationService;
use App\Services\AffiliateSettingsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EvaluateAffiliates extends Command
{
    protected $signature = 'affiliate:evaluate
                            {--month= : Evaluate through end of month (YYYY-MM)}
                            {--dry-run : Preview recommendations without saving}
                            {--no-apply : Save evaluations but do not auto-watchlist/suspend}';

    protected $description = 'Evaluate affiliate KPI, risk, and fraud scores; create admin recommendations and update leaderboard';

    public function handle(
        AffiliateEvaluationService $evaluations,
        AffiliateSettingsService $settings,
    ): int {
        $periodEnd = $this->resolvePeriodEnd();
        $dryRun = (bool) $this->option('dry-run');
        $applyActions = ! $dryRun && ! $this->option('no-apply') && $settings->autoApplyActions();

        if ($dryRun) {
            $this->warn('Dry run — no evaluations will be stored.');
        } elseif (! $applyActions) {
            $this->info('Auto-actions disabled — evaluations will be saved without lifecycle changes.');
        }

        $result = $evaluations->evaluateAll($periodEnd, $dryRun, $applyActions);

        $this->info("Period ending: {$periodEnd->toDateString()}");
        $this->info("Evaluated: {$result['evaluated']}");
        $this->info("Watchlisted: {$result['watchlisted']}");
        $this->info("Suspended: {$result['suspended']}");
        $this->info("No action: {$result['skipped']}");

        if (! $dryRun) {
            $this->info('Leaderboard ranks updated.');
        }

        return self::SUCCESS;
    }

    protected function resolvePeriodEnd(): Carbon
    {
        $month = $this->option('month');

        if (filled($month)) {
            return Carbon::createFromFormat('Y-m', (string) $month)->endOfMonth()->endOfDay();
        }

        return now()->endOfDay();
    }
}
