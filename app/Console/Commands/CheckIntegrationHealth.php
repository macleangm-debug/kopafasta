<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationHealthService;
use Illuminate\Console\Command;

class CheckIntegrationHealth extends Command
{
    protected $signature = 'integrations:health-check {--quiet-ok : Only print failures}';

    protected $description = 'Probe configured integrations and notify admins on failures';

    public function handle(IntegrationHealthService $health): int
    {
        $results = $health->checkAll(notifyOnFailure: true);
        $failed = 0;

        foreach ($results as $row) {
            if ($row['ok']) {
                if (! $this->option('quiet-ok')) {
                    $this->info("OK  {$row['key']}: {$row['message']}");
                }

                continue;
            }

            $failed++;
            $this->error("FAIL {$row['key']}: {$row['message']}");
        }

        $this->line($failed === 0
            ? 'All available integrations healthy.'
            : "{$failed} integration(s) unhealthy — admins notified.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
