<?php

namespace App\Console\Commands;

use App\Services\ApplicationIntakeReconciliationService;
use Illuminate\Console\Command;

class ReconcileApplicationIntakeCommand extends Command
{
    protected $signature = 'applications:reconcile-intake
        {--numbers=* : Limit to application numbers}
        {--dry-run : Report the plan without writing (default)}
        {--apply : Write proposed transitions}
        {--notify : Dispatch notifications when applying (never in dry-run)}
        {--approve : Required to write in production}';

    protected $description = 'Idempotent intake reconciliation for contradictory drafts, Gate-One FAIL files still awaiting guarantor, and stale closed stages.';

    public function handle(ApplicationIntakeReconciliationService $service): int
    {
        $numbers = array_values(array_filter(array_map('strval', (array) $this->option('numbers'))));
        $apply = (bool) $this->option('apply');
        $notify = (bool) $this->option('notify');

        $plan = $service->plan($numbers);
        $this->table(
            ['Number', 'Borrower', 'Action', 'From', 'To', 'Reason'],
            collect($plan)->map(fn (array $row) => [
                $row['application_number'] ?? '',
                $row['borrower'] ?? '',
                $row['action'] ?? '',
                ($row['from_status'] ?? '').'/'.($row['from_stage'] ?? ''),
                ($row['to_status'] ?? '').'/'.($row['to_stage'] ?? ''),
                $row['reason'] ?? '',
            ])->all(),
        );

        if (! $apply) {
            $this->info('Dry run only — no records changed and no notifications sent.');
            $this->line('Use --apply to write. Production also requires --approve.');

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->option('approve')) {
            $this->error('Refusing production write without --approve after Owner review.');

            return self::FAILURE;
        }

        if (app()->environment('production') && $notify && ! $this->option('approve')) {
            $this->error('Refusing production notifications without --approve.');

            return self::FAILURE;
        }

        $results = $service->apply($numbers, $notify && ! app()->environment('testing') ? $notify : $notify);
        foreach ($results as $result) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        $this->info($notify ? 'Applied with notifications enabled.' : 'Applied without notifications.');

        return self::SUCCESS;
    }
}
