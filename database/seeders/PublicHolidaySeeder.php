<?php

namespace Database\Seeders;

use App\Models\PublicHoliday;
use App\Services\WorkingCalendarService;
use Illuminate\Database\Seeder;

class PublicHolidaySeeder extends Seeder
{
    /**
     * Tanzania public holidays (fixed + known variable dates for upcoming years).
     * Variable religious/Easter dates can be adjusted in Settings → Working hours.
     */
    public function run(): void
    {
        $fixed = [
            ['01-01', 'New Year\'s Day', 'Mwaka Mpya', true],
            ['01-12', 'Zanzibar Revolution Day', 'Mapinduzi ya Zanzibar', true],
            ['04-26', 'Union Day', 'Siku ya Muungano', true],
            ['05-01', 'Labour Day', 'Siku ya Wafanyakazi', true],
            ['07-07', 'Saba Saba (International Trade Fair)', 'Saba Saba', true],
            ['08-08', 'Nane Nane (Farmers\' Day)', 'Nane Nane', true],
            ['10-14', 'Nyerere Day', 'Siku ya Nyerere', true],
            ['12-09', 'Independence Day', 'Siku ya Uhuru', true],
            ['12-25', 'Christmas Day', 'Krismasi', true],
            ['12-26', 'Boxing Day', 'Siku ya Boxi', true],
        ];

        // Variable dates (Good Friday, Easter Monday, Eid al-Fitr, Eid al-Adha, Mawlid)
        $variable = [
            // 2025
            ['2025-03-30', 'Eid al-Fitr', 'Idd el-Fitri'],
            ['2025-03-31', 'Eid al-Fitr (2nd day)', 'Idd el-Fitri (siku ya 2)'],
            ['2025-04-18', 'Good Friday', 'Ijumaa Kuu'],
            ['2025-04-21', 'Easter Monday', 'Jumatatu ya Pasaka'],
            ['2025-06-07', 'Eid al-Adha', 'Idd el-Hajj'],
            ['2025-09-05', 'Maulid Day', 'Maulid'],
            // 2026
            ['2026-03-20', 'Eid al-Fitr', 'Idd el-Fitri'],
            ['2026-03-21', 'Eid al-Fitr (2nd day)', 'Idd el-Fitri (siku ya 2)'],
            ['2026-04-03', 'Good Friday', 'Ijumaa Kuu'],
            ['2026-04-06', 'Easter Monday', 'Jumatatu ya Pasaka'],
            ['2026-05-27', 'Eid al-Adha', 'Idd el-Hajj'],
            ['2026-08-26', 'Maulid Day', 'Maulid'],
            // 2027
            ['2027-03-09', 'Eid al-Fitr', 'Idd el-Fitri'],
            ['2027-03-10', 'Eid al-Fitr (2nd day)', 'Idd el-Fitri (siku ya 2)'],
            ['2027-03-26', 'Good Friday', 'Ijumaa Kuu'],
            ['2027-03-29', 'Easter Monday', 'Jumatatu ya Pasaka'],
            ['2027-05-17', 'Eid al-Adha', 'Idd el-Hajj'],
            ['2027-08-15', 'Maulid Day', 'Maulid'],
            // 2028
            ['2028-02-26', 'Eid al-Fitr', 'Idd el-Fitri'],
            ['2028-02-27', 'Eid al-Fitr (2nd day)', 'Idd el-Fitri (siku ya 2)'],
            ['2028-04-14', 'Good Friday', 'Ijumaa Kuu'],
            ['2028-04-17', 'Easter Monday', 'Jumatatu ya Pasaka'],
            ['2028-05-05', 'Eid al-Adha', 'Idd el-Hajj'],
            ['2028-08-04', 'Maulid Day', 'Maulid'],
        ];

        foreach (range(2025, 2030) as $year) {
            foreach ($fixed as [$md, $name, $nameSw, $recurring]) {
                $date = sprintf('%d-%s', $year, $md);
                PublicHoliday::query()->updateOrCreate(
                    ['date' => $date],
                    [
                        'name' => $name,
                        'name_sw' => $nameSw,
                        'country_code' => 'TZ',
                        'is_recurring' => $recurring,
                    ]
                );
            }
        }

        foreach ($variable as [$date, $name, $nameSw]) {
            PublicHoliday::query()->updateOrCreate(
                ['date' => $date],
                [
                    'name' => $name,
                    'name_sw' => $nameSw,
                    'country_code' => 'TZ',
                    'is_recurring' => false,
                ]
            );
        }

        WorkingCalendarService::forgetHolidayCache();
    }
}
