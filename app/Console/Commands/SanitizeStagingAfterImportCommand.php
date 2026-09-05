<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * After cloning triptz/test data into staging: keep operational records,
 * strip live integration secrets, and drop queued outbound work.
 * Never changes payment statuses or commercial Settings amounts.
 */
class SanitizeStagingAfterImportCommand extends Command
{
    protected $signature = 'staging:sanitize-after-import {--force : Skip confirmation}';

    protected $description = 'Strip live PSP/SMS secrets and queued jobs after a staging data import. Staging only.';

    public function handle(): int
    {
        if (app()->isProduction() || ! app()->environment('staging')) {
            $this->error('Refusing: this command only runs when APP_ENV=staging.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Sanitize staging integration secrets and drain the queue? Payment records will not be changed.')) {
            return self::SUCCESS;
        }

        Setting::setMany([
            'payin.enabled' => false,
            'payin.environment' => 'sandbox',
            'payin.api_key' => '',
            'payin.api_secret' => '',
            'payin.webhook_secret' => '',
            'payin.default_callback_url' => 'https://staging.kopafasta.com/webhooks/payin',
            'gateway.sms_api_key' => '',
            'gateway.sms_api_secret' => '',
            'security.turnstile_site_key' => '',
            'security.turnstile_secret_key' => '',
        ]);

        foreach (['jobs', 'failed_jobs', 'job_batches'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        Cache::forget('settings.all');
        Cache::forget('sms.settings.v1');

        $this->info('Staging secrets stripped. Queue drained. Payment statuses were not modified.');

        return self::SUCCESS;
    }
}
