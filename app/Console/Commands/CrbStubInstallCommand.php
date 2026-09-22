<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\CrbCreditCheckService;
use App\Services\Crb\StubCrbCreditFixture;
use Illuminate\Console\Command;

/**
 * Install participant-specific stub CRB fixtures for Gate 3 testing.
 * Never calls live D&B.
 */
class CrbStubInstallCommand extends Command
{
    protected $signature = 'crb:stub-install
        {customer : Customer id}
        {scenario=clean : clean|refer|hard_fail|wrong_subject|stale|no_record}
        {--days= : Override age in days (stale defaults to freshness_days+1)}';

    protected $description = 'Install a crb_stub CreditHistory scenario for a customer (no live bureau call)';

    public function handle(CrbCreditCheckService $crb, StubCrbCreditFixture $fixture): int
    {
        if (! app(\App\Services\CrbService::class)->usesStub()) {
            $this->error('Refusing: CRB driver is not stub/sandbox. Set CRB_DRIVER=stub or enable KYC CRB sandbox.');

            return self::FAILURE;
        }

        $customer = Customer::find($this->argument('customer'));
        if (! $customer) {
            $this->error('Customer not found.');

            return self::FAILURE;
        }

        $scenario = (string) $this->argument('scenario');
        if ($fixture->normalizeScenario($scenario) === null && $scenario !== 'pass') {
            $this->error('Unknown scenario. Use: '.implode(', ', StubCrbCreditFixture::SCENARIOS));

            return self::FAILURE;
        }

        $checkedAt = null;
        if ($this->option('days') !== null) {
            $checkedAt = now()->subDays(max(0, (int) $this->option('days')));
        }

        $history = $crb->installStubReport($customer, $scenario, $checkedAt, [
            'via' => 'artisan crb:stub-install',
        ]);

        $this->info('Installed crb_stub #'.$history->id
            .' for '.$customer->full_name
            .' scenario='.($history->payload['scenario'] ?? $scenario)
            .' checked_at='.$history->checked_at
            .' source='.$history->source);

        return self::SUCCESS;
    }
}
