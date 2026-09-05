<?php

namespace App\Console\Commands;

use App\Services\CommercialPricingProfileService;
use Illuminate\Console\Command;

/**
 * Apply owner-approved Staging test tariffs (or production when confirmed).
 * Prospective only — does not rewrite historical payments.
 */
class ApplyCommercialPricingCommand extends Command
{
    protected $signature = 'pricing:apply-commercial-profile
        {--env= : Override environment (staging|production|local)}
        {--force : Skip confirmation}';

    protected $description = 'Apply Settings Hub + product commercial pricing profile for the environment.';

    public function handle(CommercialPricingProfileService $pricing): int
    {
        $env = $this->option('env') ?: app()->environment();

        if ($env === 'production' && env('CONFIRM_PRODUCTION_PRICING') !== '1') {
            $this->error('Refusing production. Set CONFIRM_PRODUCTION_PRICING=1 after owner freeze.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Apply commercial pricing profile for [{$env}]?")) {
            return self::SUCCESS;
        }

        $result = $pricing->apply($env);
        $this->info('Applied '.$result['environment'].' commercial pricing profile.');
        foreach ($result['changed'] as $line) {
            $this->line(' - '.$line);
        }

        return self::SUCCESS;
    }
}
