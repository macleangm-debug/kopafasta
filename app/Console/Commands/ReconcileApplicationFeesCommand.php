<?php

namespace App\Console\Commands;

use App\Models\CustomerPayment;
use App\Services\ApplicationFeeIntegrityService;
use Illuminate\Console\Command;

class ReconcileApplicationFeesCommand extends Command
{
    protected $signature = 'application-fees:reconcile
        {--reference= : Repair one payment reference (e.g. PAY-BUFVZK)}
        {--dry-run : Classify without writing}
        {--force : Required to write outside local/testing}';

    protected $description = 'Audit verified application fees against their stored obligation and repair class A/B only.';

    public function handle(ApplicationFeeIntegrityService $integrity): int
    {
        $reference = trim((string) $this->option('reference'));
        $dryRun = (bool) $this->option('dry-run');
        $apply = ! $dryRun;

        if ($apply && ! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Refusing to write outside local/testing without --force.');

            return self::FAILURE;
        }

        if ($reference !== '') {
            $payment = CustomerPayment::query()->where('reference', $reference)->first();
            if (! $payment) {
                $this->error('Payment not found: '.$reference);

                return self::FAILURE;
            }
            $row = $integrity->repair($payment, $apply);
            $this->table(['Field', 'Value'], collect($row)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->all());

            return self::SUCCESS;
        }

        $audit = $integrity->audit();
        $this->info('Verified application fees: '.$audit['verified']);
        $this->info('Class A (exact link, still pending): '.count($audit['class_a']));
        $this->info('Class B (deterministic, e.g. withdrawn after paid): '.count($audit['class_b']));
        $this->info('Class C (ambiguous): '.count($audit['class_c']));
        $this->info('Opposite (app says paid, no verified payment): '.count($audit['opposite']));

        foreach (['class_a' => 'A', 'class_b' => 'B', 'class_c' => 'C'] as $key => $label) {
            if ($audit[$key] === []) {
                continue;
            }
            $this->line('');
            $this->warn('Class '.$label);
            $this->table(
                ['Ref', 'Customer', 'Draft', 'App', 'Status', 'Reason'],
                collect($audit[$key])->map(fn (array $row) => [
                    $row['reference'] ?? '',
                    $row['customer_id'] ?? '',
                    $row['draft_reference'] ?? '',
                    $row['application_number'] ?? '',
                    $row['application_status'] ?? '',
                    $row['reason'] ?? '',
                ])->all(),
            );
        }

        if ($apply) {
            $repaired = 0;
            foreach (array_merge($audit['class_a'], $audit['class_b']) as $row) {
                $payment = CustomerPayment::query()->find($row['payment_id'] ?? 0);
                if (! $payment) {
                    continue;
                }
                $result = $integrity->repair($payment, true);
                if ($result['repaired'] ?? false) {
                    $repaired++;
                }
            }
            $this->info('Repaired: '.$repaired);
        } else {
            $this->info('Dry run only — no records changed.');
        }

        return self::SUCCESS;
    }
}
