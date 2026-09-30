<?php

namespace App\Services\Support;

use App\Models\Setting;

/**
 * Settings-backed Support quick replies. Swahili first + English.
 */
class SupportQuickReplyService
{
    public const SETTING_KEY = 'support.quick_replies';

    /**
     * @return list<array{key: string, label_sw: string, label_en: string, body_sw: string, body_en: string}>
     */
    public function defaults(): array
    {
        return [
            [
                'key' => 'received',
                'label_sw' => 'Tumepokea',
                'label_en' => 'Received',
                'body_sw' => 'Tumepokea ombi lako. Tunalifanyia kazi na tutakujulisha mara tu tutakapokuwa na taarifa mpya.',
                'body_en' => 'We have received your request. We are working on it and will update you as soon as we have news.',
            ],
            [
                'key' => 'investigating',
                'label_sw' => 'Tunachunguza',
                'label_en' => 'Investigating',
                'body_sw' => 'Tunachunguza suala lako sasa. Tafadhali subiri kidogo — tutarudi kwako hivi karibuni.',
                'body_en' => 'We are checking this for you now. Please hold on — we will get back to you shortly.',
            ],
            [
                'key' => 'need_info',
                'label_sw' => 'Tunahitaji taarifa',
                'label_en' => 'Need information',
                'body_sw' => 'Ili tuendelee, tafadhali tutumie taarifa zifuatazo: [eleza unachohitajika].',
                'body_en' => 'To continue, please send us the following information: [describe what you need].',
            ],
            [
                'key' => 'escalated',
                'label_sw' => 'Tumelifikisha',
                'label_en' => 'Escalated',
                'body_sw' => 'Tumelifikisha suala lako kwa timu husika. Tutakujulisha tutakapopata mrejesho.',
                'body_en' => 'We have forwarded your issue to the relevant team. We will update you when we have a response.',
            ],
            [
                'key' => 'resolved',
                'label_sw' => 'Limetatuliwa',
                'label_en' => 'Resolved',
                'body_sw' => 'Suala lako limetatuliwa. Ikiwa bado unahitaji msaada, jibu ujumbe huu.',
                'body_en' => 'Your issue has been resolved. If you still need help, reply to this message.',
            ],
            [
                'key' => 'confirm',
                'label_sw' => 'Thibitisha',
                'label_en' => 'Confirm resolved',
                'body_sw' => 'Je, tumekusaidia? Tafadhali thibitisha kama suala lako limetatuliwa.',
                'body_en' => 'Have we helped you? Please confirm whether your issue is now resolved.',
            ],
            [
                'key' => 'thanks',
                'label_sw' => 'Asante',
                'label_en' => 'Thank you',
                'body_sw' => 'Asante kwa kuwasiliana na Kopafasta. Tuko hapa kukusaidia.',
                'body_en' => 'Thank you for contacting Kopafasta. We are here to help.',
            ],
        ];
    }

    /**
     * @return list<array{key: string, label_sw: string, label_en: string, body_sw: string, body_en: string}>
     */
    public function all(): array
    {
        $stored = Setting::get(self::SETTING_KEY);
        if (! is_array($stored) || $stored === []) {
            return $this->defaults();
        }

        $byKey = [];
        foreach ($this->defaults() as $row) {
            $byKey[$row['key']] = $row;
        }
        foreach ($stored as $row) {
            if (! is_array($row) || empty($row['key'])) {
                continue;
            }
            $byKey[(string) $row['key']] = array_merge($byKey[(string) $row['key']] ?? [], $row);
        }

        return array_values($byKey);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function save(array $rows): void
    {
        Setting::set(self::SETTING_KEY, array_values($rows));
    }

    public function bodyFor(string $key, string $locale = 'sw'): string
    {
        $locale = str_starts_with(strtolower($locale), 'en') ? 'en' : 'sw';
        foreach ($this->all() as $row) {
            if ($row['key'] === $key) {
                return (string) ($row['body_'.$locale] ?? $row['body_sw'] ?? '');
            }
        }

        return '';
    }
}
