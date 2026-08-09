<?php

namespace Tests\Feature;

use App\Models\PublicHoliday;
use App\Models\Setting;
use App\Services\WorkingCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkingCalendarFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('company.timezone', 'Africa/Dar_es_Salaam');
        Setting::set('company.working_weekdays', ['mon', 'tue', 'wed', 'thu', 'fri']);
        Setting::set('company.working_hours_start', '08:00');
        Setting::set('company.working_hours_end', '17:00');
        WorkingCalendarService::forgetHolidayCache();
    }

    public function test_skips_weekend_when_adding_working_hours(): void
    {
        $calendar = app(WorkingCalendarService::class);
        // Friday 16:00 → 2 working hours → Monday 09:00 (1h Fri + 1h Mon)
        $from = Carbon::parse('2026-03-13 16:00:00', 'Africa/Dar_es_Salaam');
        $due = $calendar->addWorkingHours($from, 2);

        $this->assertSame('2026-03-16', $due->toDateString());
        $this->assertSame(9, (int) $due->format('G'));
    }

    public function test_skips_public_holiday(): void
    {
        PublicHoliday::create([
            'date' => '2026-03-16',
            'name' => 'Test Holiday',
            'country_code' => 'TZ',
            'is_recurring' => false,
        ]);
        WorkingCalendarService::forgetHolidayCache();

        $calendar = app(WorkingCalendarService::class);
        $from = Carbon::parse('2026-03-13 16:00:00', 'Africa/Dar_es_Salaam');
        $due = $calendar->addWorkingHours($from, 2);

        // Fri 1h + skip Sat/Sun/Mon holiday → Tue 09:00
        $this->assertSame('2026-03-17', $due->toDateString());
        $this->assertTrue($calendar->isHoliday(Carbon::parse('2026-03-16')));
        $this->assertFalse($calendar->isWorkingDay(Carbon::parse('2026-03-16')));
    }

    public function test_forty_eight_working_hours_span_multiple_office_days(): void
    {
        $calendar = app(WorkingCalendarService::class);
        // 48 working hours @ 9h/day = a bit over 5 office days
        $from = Carbon::parse('2026-03-09 08:00:00', 'Africa/Dar_es_Salaam'); // Monday open
        $due = $calendar->addWorkingHours($from, 48);

        // 5 full office days (45h) through Fri close, then Mon 08:00 + 3h = 11:00
        $this->assertSame('2026-03-16', $due->toDateString());
        $this->assertSame('11:00', $due->format('H:i'));
    }
}
