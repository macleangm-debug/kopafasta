<?php

namespace App\Console\Commands;

use App\Services\PartnerEfficiencyPolicy;
use App\Services\PartnerPerformanceReviewService;
use Illuminate\Console\Command;

class EvaluatePartnerEfficiency extends Command
{
    protected $signature = 'partners:evaluate-efficiency
                            {--no-apply : Score partners but do not nudge or suspend}';

    protected $description = 'Score field partners, nudge those who need coaching, and suspend after repeated at-risk reviews';

    public function handle(
        PartnerPerformanceReviewService $reviews,
        PartnerEfficiencyPolicy $policy,
    ): int {
        $apply = ! $this->option('no-apply');

        if (! $apply) {
            $this->info('Auto-actions off — scores will be stored without nudge or suspend.');
        } elseif (! $policy->autoNudge() && ! $policy->autoSuspend()) {
            $this->info('Settings have auto-nudge and auto-suspend off.');
        }

        $result = $reviews->reviewAll($apply);

        $this->info('Reviewed: '.$result['reviewed']);
        $this->info('Nudged: '.$result['nudged']);
        $this->info('Suspended: '.$result['suspended']);
        $this->info('No action: '.$result['skipped']);

        return self::SUCCESS;
    }
}
