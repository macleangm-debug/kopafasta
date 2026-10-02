<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Support\SupportAutomationService;
use Illuminate\Database\Seeder;

/**
 * Controlled Digital Assistant persona configuration (Settings only).
 *
 * Upserts the Owner-accepted reference personas by key. Does NOT touch
 * Support conversations, tickets, ratings, members, guests, or payments.
 */
class DigitalAssistantPersonasConfigSeeder extends Seeder
{
    /**
     * Accepted production reference set (staging Settings as of Owner UAT 2026-10-02).
     *
     * @var list<array{key: string, name: string, active: bool}>
     */
    public const ACCEPTED_PERSONAS = [
        ['key' => 'amani', 'name' => 'Amani', 'active' => true],
        ['key' => 'neema', 'name' => 'Neema', 'active' => true],
        ['key' => 'baraka', 'name' => 'Baraka', 'active' => true],
        ['key' => 'rehema', 'name' => 'Rehema', 'active' => true],
        ['key' => 'daniel', 'name' => 'Daniel', 'active' => true],
        ['key' => 'geofrey', 'name' => 'Geofrey', 'active' => true],
        ['key' => 'nsajigwa', 'name' => 'Nsajigwa', 'active' => true],
    ];

    public function run(): void
    {
        $max = max(20, (int) Setting::get(SupportAutomationService::PERSONAS_MAX_SETTING_KEY, 20));
        Setting::set(SupportAutomationService::PERSONAS_MAX_SETTING_KEY, min(20, $max));

        $stored = Setting::get(SupportAutomationService::PERSONAS_SETTING_KEY);
        $byKey = [];
        if (is_array($stored)) {
            foreach ($stored as $i => $row) {
                if (is_string($row)) {
                    $name = trim($row);
                    $key = \Illuminate\Support\Str::slug($name) ?: ('persona_'.($i + 1));
                    if ($name !== '') {
                        $byKey[$key] = ['key' => $key, 'name' => $name, 'active' => true];
                    }
                } elseif (is_array($row)) {
                    $name = trim((string) ($row['name'] ?? ''));
                    $key = trim((string) ($row['key'] ?? '')) ?: (\Illuminate\Support\Str::slug($name) ?: ('persona_'.($i + 1)));
                    if ($name !== '') {
                        $byKey[$key] = [
                            'key' => $key,
                            'name' => $name,
                            'active' => array_key_exists('active', $row) ? (bool) $row['active'] : true,
                        ];
                    }
                }
            }
        }

        // Ensure each accepted persona exists with accepted name/active (config only).
        foreach (self::ACCEPTED_PERSONAS as $persona) {
            $byKey[$persona['key']] = $persona;
        }

        Setting::set(
            SupportAutomationService::PERSONAS_SETTING_KEY,
            array_values($byKey)
        );
    }
}
