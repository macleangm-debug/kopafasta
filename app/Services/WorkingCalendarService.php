<?php

namespace App\Services;

use App\Models\PublicHoliday;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Office working calendar: configured weekdays + hours, excluding public holidays.
 * Defaults: Mon–Fri 08:00–17:00 Africa/Dar_es_Salaam.
 */
class WorkingCalendarService
{
    /** @return list<int> Carbon day-of-week ints (0=Sun … 6=Sat) */
    public function workingWeekdays(): array
    {
        $raw = Setting::get('company.working_weekdays', ['mon', 'tue', 'wed', 'thu', 'fri']);
        if (! is_array($raw) || $raw === []) {
            $raw = ['mon', 'tue', 'wed', 'thu', 'fri'];
        }

        $map = [
            'sun' => CarbonInterface::SUNDAY,
            'mon' => CarbonInterface::MONDAY,
            'tue' => CarbonInterface::TUESDAY,
            'wed' => CarbonInterface::WEDNESDAY,
            'thu' => CarbonInterface::THURSDAY,
            'fri' => CarbonInterface::FRIDAY,
            'sat' => CarbonInterface::SATURDAY,
        ];

        $days = [];
        foreach ($raw as $key) {
            $k = strtolower((string) $key);
            if (isset($map[$k])) {
                $days[] = $map[$k];
            }
        }

        return $days !== [] ? array_values(array_unique($days)) : [
            CarbonInterface::MONDAY,
            CarbonInterface::TUESDAY,
            CarbonInterface::WEDNESDAY,
            CarbonInterface::THURSDAY,
            CarbonInterface::FRIDAY,
        ];
    }

    public function startHour(): int
    {
        return $this->parseHour(Setting::get('company.working_hours_start', '08:00'), 8);
    }

    public function startMinute(): int
    {
        return $this->parseMinute(Setting::get('company.working_hours_start', '08:00'), 0);
    }

    public function endHour(): int
    {
        return $this->parseHour(Setting::get('company.working_hours_end', '17:00'), 17);
    }

    public function endMinute(): int
    {
        return $this->parseMinute(Setting::get('company.working_hours_end', '17:00'), 0);
    }

    public function timezone(): string
    {
        return (string) (Setting::get('company.timezone') ?: config('app.timezone') ?: 'Africa/Dar_es_Salaam');
    }

    public function isHoliday(CarbonInterface $day): bool
    {
        $date = Carbon::parse($day)->timezone($this->timezone())->toDateString();

        return in_array($date, $this->holidayDates(), true);
    }

    public function isWorkingDay(CarbonInterface $day): bool
    {
        $local = Carbon::parse($day)->timezone($this->timezone());

        return in_array((int) $local->dayOfWeek, $this->workingWeekdays(), true)
            && ! $this->isHoliday($local);
    }

    public function addWorkingDays(CarbonInterface $from, int $days): Carbon
    {
        $cursor = Carbon::parse($from)->timezone($this->timezone())->startOfDay();
        $added = 0;
        while ($added < $days) {
            $cursor = $cursor->addDay();
            if ($this->isWorkingDay($cursor)) {
                $added++;
            }
        }

        return $cursor->copy()->endOfDay();
    }

    /**
     * Add N working hours within office hours on working days (skips weekends + holidays).
     */
    public function addWorkingHours(CarbonInterface $from, int $hours): Carbon
    {
        $tz = $this->timezone();
        $cursor = Carbon::parse($from)->timezone($tz);
        $remainingMinutes = max(0, $hours) * 60;
        $startH = $this->startHour();
        $startM = $this->startMinute();
        $endH = $this->endHour();
        $endM = $this->endMinute();
        $guard = 0;

        while ($remainingMinutes > 0 && $guard < 20000) {
            $guard++;

            if (! $this->isWorkingDay($cursor)) {
                $cursor = $this->nextWorkingDayStart($cursor);
                continue;
            }

            $dayStart = $cursor->copy()->setTime($startH, $startM, 0);
            $dayEnd = $cursor->copy()->setTime($endH, $endM, 0);

            if ($cursor->lt($dayStart)) {
                $cursor = $dayStart;
            } elseif ($cursor->gte($dayEnd)) {
                $cursor = $this->nextWorkingDayStart($cursor);
                continue;
            }

            $available = (int) $cursor->diffInMinutes($dayEnd, false);
            if ($available <= 0) {
                $cursor = $this->nextWorkingDayStart($cursor);
                continue;
            }

            $step = min($remainingMinutes, $available);
            $cursor = $cursor->copy()->addMinutes($step);
            $remainingMinutes -= $step;
        }

        return $cursor;
    }

    private function nextWorkingDayStart(CarbonInterface $from): Carbon
    {
        $cursor = Carbon::parse($from)->timezone($this->timezone())->addDay()->startOfDay()
            ->setTime($this->startHour(), $this->startMinute(), 0);

        $guard = 0;
        while (! $this->isWorkingDay($cursor) && $guard < 400) {
            $cursor = $cursor->addDay()->setTime($this->startHour(), $this->startMinute(), 0);
            $guard++;
        }

        return $cursor;
    }

    /** @return list<string> Y-m-d */
    private function holidayDates(): array
    {
        return Cache::remember('public_holidays.dates.v1', 300, function () {
            try {
                return PublicHoliday::query()
                    ->orderBy('date')
                    ->pluck('date')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public static function forgetHolidayCache(): void
    {
        Cache::forget('public_holidays.dates.v1');
    }

    private function parseHour(mixed $value, int $default): int
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return $default;
        }

        return max(0, min(23, (int) $m[1]));
    }

    private function parseMinute(mixed $value, int $default): int
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return $default;
        }

        return max(0, min(59, (int) $m[2]));
    }
}
