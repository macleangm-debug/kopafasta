<?php

namespace App\Services\Support;

use App\Models\Setting;

/**
 * Settings-backed Support quick replies. Swahili first + English.
 * Bodies are customer-service length; Settings Hub signature is appended at compose time.
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
                'key' => 'introduction',
                'label_sw' => 'Utambulisho',
                'label_en' => 'Introduction',
                'body_sw' => 'Habari {member_first_name}, jina langu ni {agent_first_name} kutoka Huduma kwa Wateja ya Kopafasta. Nitafanya kila niwezalo kukusaidia kutatua suala lako.',
                'body_en' => 'Hello {member_first_name}, my name is {agent_first_name} from Kopafasta Customer Support. I will do everything I can to help resolve your issue.',
            ],
            [
                'key' => 'received',
                'label_sw' => 'Tumepokea',
                'label_en' => 'Received',
                'body_sw' => 'Tumepokea ombi lako na tunalifanyia kazi. Tutakujulisha hapa mara tu tutakapokuwa na taarifa mpya.',
                'body_en' => 'We have received your request and are working on it. We will update you here as soon as we have news.',
            ],
            [
                'key' => 'investigating',
                'label_sw' => 'Tunachunguza',
                'label_en' => 'Investigating',
                'body_sw' => 'Tunachunguza suala lako kwa kina sasa. Tafadhali subiri kidogo — tutarudi kwako hapa mara tu tutakapokuwa na majibu.',
                'body_en' => 'We are carefully checking this for you now. Please hold on — we will come back to you here as soon as we have an answer.',
            ],
            [
                'key' => 'need_info',
                'label_sw' => 'Tunahitaji taarifa',
                'label_en' => 'Need information',
                'body_sw' => 'Ili tuendelee kukusaidia, tafadhali tutumie taarifa zifuatazo: [eleza unachohitajika]. Mara tu tutakapozipokea, tutaendelea mara moja.',
                'body_en' => 'To continue helping you, please send us the following information: [describe what you need]. As soon as we receive it, we will continue right away.',
            ],
            [
                'key' => 'escalated',
                'label_sw' => 'Tumelifikisha',
                'label_en' => 'Escalated',
                'body_sw' => 'Tumelifikisha suala lako kwa timu husika ndani ya Kopafasta. Tutakujulisha hapa tutakapopata mrejesho — wewe utaendelea kuwasiliana nasi kwenye mazungumzo haya.',
                'body_en' => 'We have forwarded your issue to the relevant team inside Kopafasta. We will update you here when we have a response — you will continue speaking with us on this conversation.',
            ],
            [
                'key' => 'resolved',
                'label_sw' => 'Limetatuliwa',
                'label_en' => 'Resolved',
                'body_sw' => 'Suala lako limetatuliwa. Ikiwa bado unahitaji msaada wowote, jibu ujumbe huu na tutakuendelea kusaidia.',
                'body_en' => 'Your issue has been resolved. If you still need any help, reply to this message and we will continue assisting you.',
            ],
            [
                'key' => 'confirm',
                'label_sw' => 'Thibitisha',
                'label_en' => 'Confirmation',
                'body_sw' => 'Je, tumekusaidia? Tafadhali thibitisha kama suala lako limetatuliwa sasa, au niambie iwapo bado kuna kitu kinachohitaji kushughulikiwa.',
                'body_en' => 'Have we helped you? Please confirm whether your issue is now resolved, or tell us if anything still needs attention.',
            ],
            [
                'key' => 'thanks',
                'label_sw' => 'Asante',
                'label_en' => 'Thank you',
                'body_sw' => 'Asante kwa kuwasiliana na Kopafasta. Tuko hapa kukusaidia wakati wowote unapohitaji.',
                'body_en' => 'Thank you for contacting Kopafasta. We are here whenever you need help.',
            ],
        ];
    }

    /**
     * @return list<array{key: string, label_sw: string, label_en: string, body_sw: string, body_en: string}>
     */
    public function all(): array
    {
        $stored = Setting::get(self::SETTING_KEY);
        $byKey = [];
        foreach ($this->defaults() as $row) {
            $byKey[$row['key']] = $row;
        }
        if (is_array($stored)) {
            foreach ($stored as $row) {
                if (! is_array($row) || empty($row['key'])) {
                    continue;
                }
                $byKey[(string) $row['key']] = array_merge($byKey[(string) $row['key']] ?? [], $row);
            }
        }

        // Prefer default order (introduction first).
        $ordered = [];
        foreach ($this->defaults() as $row) {
            $ordered[] = $byKey[$row['key']];
            unset($byKey[$row['key']]);
        }
        foreach ($byKey as $extra) {
            $ordered[] = $extra;
        }

        return $ordered;
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

    /**
     * Compose template + Settings-backed Support signature (phone/website not hard-coded).
     *
     * @param  array<string, string>  $vars
     */
    public function compose(string $key, string $locale = 'sw', array $vars = [], bool $withSignature = true): string
    {
        $body = $this->bodyFor($key, $locale);
        foreach ($vars as $name => $value) {
            $body = str_replace('{'.$name.'}', (string) $value, $body);
        }
        $body = preg_replace('/\{[a-z_]+\}/', '', $body) ?? $body;
        $body = trim($body);

        if ($withSignature) {
            $sig = $this->signature($locale);
            if ($sig !== '') {
                $body = rtrim($body)."\n\n".$sig;
            }
        }

        return $body;
    }

    public function signature(string $locale = 'sw'): string
    {
        $locale = str_starts_with(strtolower($locale), 'en') ? 'en' : 'sw';
        $phone = support_phones()[0] ?? support_contact('phone');
        $website = (string) (Setting::get('company.website') ?: config('app.url') ?: 'https://www.kopafasta.com');
        $website = preg_replace('#^https?://#', '', rtrim($website, '/')) ?: $website;

        if ($locale === 'en') {
            $lines = ['Kopafasta Customer Support'];
            if (filled($phone)) {
                $lines[] = 'Phone: '.$phone;
            }
            if (filled($website)) {
                $lines[] = 'Website: '.$website;
            }

            return implode("\n", $lines);
        }

        $lines = ['Kopafasta Customer Support'];
        if (filled($phone)) {
            $lines[] = 'Simu: '.$phone;
        }
        if (filled($website)) {
            $lines[] = 'Tovuti: '.$website;
        }

        return implode("\n", $lines);
    }
}
