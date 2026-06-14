<?php

namespace App\Console\Commands;

use App\Services\RecoveryEscalationService;
use App\Services\RecoveryPolicyService;
use Illuminate\Console\Command;

class EscalateExpiredRecoverySlas extends Command
{
    protected $signature = 'recovery:escalate-expired-slas {--dry-run : Count breaches without escalating}';

    protected $description = 'Auto-escalate recovery partner assignments whose SLA has expired and advance to the next recovery stage';

    public function handle(
        RecoveryEscalationService $escalation,
        RecoveryPolicyService $policy,
    ): int {
        if (! $policy->autoEscalate()) {
            $this->info('Recovery auto-escalation is disabled in settings.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $escalation->processExpiredSlas($dryRun);

        if ($dryRun) {
            $this->info("Found {$result['escalated']} assignment(s) past SLA (dry run — no changes made).");

            return self::SUCCESS;
        }

        $this->info("Escalated {$result['escalated']} assignment(s) with expired SLAs.");
        $this->info("Advanced {$result['advanced']} case(s) to the next recovery stage.");

        return self::SUCCESS;
    }
}
