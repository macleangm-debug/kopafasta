<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('agreements:expire-offers')->hourly();
Schedule::command('applications:expire-awaiting-guarantor --remind')->dailyAt('08:30');
Schedule::command('applications:fire-capacity-auto-rejects')->everyFifteenMinutes();
Schedule::command('applications:remind-document-requests-due')->dailyAt('08:15');
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('security:expire-blocks')->hourly();
Schedule::command('loans:mark-overdue')->dailyAt('00:30');
Schedule::command('recovery:escalate-expired-slas')->hourly();
Schedule::command('recovery:process-auction-holds')->hourly();
Schedule::command('partners:reassign-expired-service-slas')->hourly();
Schedule::command('loans:propose-write-offs')->dailyAt('02:00');
Schedule::command('loans:accrue-late-fees')->dailyAt('01:00');
Schedule::command('loans:send-reminders --overdue')->dailyAt('08:00');
Schedule::command('membership:send-reminders')->dailyAt('09:00');
Schedule::command('partners:queue-weekly-settlements')->weeklyOn(5, '08:00');
Schedule::command('affiliate:evaluate')->monthlyOn(1, '06:00');
Schedule::command('partners:evaluate-efficiency')->weeklyOn(1, '06:30');
Schedule::command('affiliate:scan-fraud')->weeklyOn(1, '07:00');
Schedule::command('customers:send-birthday-wishes')->dailyAt('07:30');
Schedule::command('integrations:health-check --quiet-ok')->hourly();
