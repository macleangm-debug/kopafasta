<?php

namespace App\Console\Commands;

use App\Services\PrematureApplicationReconciliationService;
use Illuminate\Console\Command;

class ReconcilePrematureApplicationsCommand extends Command
{
    protected $signature = 'applications:reconcile-premature
        {--numbers=* : Application numbers (defaults to the three production legacy cases)}
        {--dry-run : Report the plan without writing}
        {--force : Required to write outside local/testing}';

    protected $description = 'Return premature submitted applications to Incomplete when the borrower profile is incomplete.';

    public function handle(PrematureApplicationReconciliationService $service): int
    {
        $numbers = $this->option('numbers');
        $numbers = $numbers !== []
            ? array_values(array_filter(array_map('strval', $numbers)))
            : PrematureApplicationReconciliationService::TARGET_NUMBERS;

        $plan = $service->plan($numbers);
        $this->table(
            ['Number', 'Action', 'From', 'To', 'Profile %', 'Draft', 'Missing'],
            collect($plan)->map(fn (array $row) => [
                $row['application_number'] ?? '',
                $row['action'] ?? '',
                ($row['from_status'] ?? '').'/'.($row['from_stage'] ?? ''),
                ($row['to_status'] ?? '').'/'.($row['to_stage'] ?? ''),
                (string) ($row['profile_percent'] ?? ''),
                $row['draft_action'] ?? '',
                implode('; ', $row['missing'] ?? []),
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run only — no records changed.');

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing production write without --force after backup.');

            return self::FAILURE;
        }

        $results = $service->reconcile($numbers);
        foreach ($results as $result) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
