<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('agreements:expire-offers')->hourly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('security:expire-blocks')->hourly();
Schedule::command('loans:mark-overdue')->dailyAt('00:30');
Schedule::command('loans:propose-write-offs')->dailyAt('02:00');
Schedule::command('loans:accrue-late-fees')->dailyAt('01:00');
Schedule::command('loans:send-reminders --overdue')->dailyAt('08:00');
Schedule::command('membership:send-reminders')->dailyAt('09:00');
Schedule::command('partners:queue-weekly-settlements')->weeklyOn(5, '08:00');
Schedule::command('customers:send-birthday-wishes')->dailyAt('07:30');
