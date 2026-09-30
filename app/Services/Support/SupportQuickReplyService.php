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
        $rows = [
            ['introduction', 'greeting', 'Utambulisho', 'Introduction',
             'Habari {member_first_name}, jina langu ni {agent_first_name} kutoka Huduma kwa Wateja ya Kopafasta. Nitafanya kila niwezalo kukusaidia kutatua suala lako.',
             'Hello {member_first_name}, my name is {agent_first_name} from Kopafasta Customer Support. I will do everything I can to help resolve your issue.'],
            ['received', 'acknowledgement', 'Tumepokea', 'Received',
             'Tumepokea ombi lako na tunalifanyia kazi. Tutakujulisha hapa mara tu tutakapokuwa na taarifa mpya.',
             'We have received your request and are working on it. We will update you here as soon as we have news.'],
            ['queue_wait', 'waiting', 'Foleni', 'In queue',
             'Ujumbe wako umepokelewa. Uko kwenye foleni ya Huduma kwa Wateja; mhudumu atakujibu hapa.',
             'Your message has been received. You are in the Customer Support queue; an agent will reply here.'],
            ['investigating', 'waiting', 'Tunachunguza', 'Investigating',
             'Tunachunguza suala lako kwa kina sasa. Tafadhali subiri kidogo — tutarudi kwako hapa mara tu tutakapokuwa na majibu.',
             'We are carefully checking this for you now. Please hold on — we will come back to you here as soon as we have an answer.'],
            ['need_info', 'need_info', 'Tunahitaji taarifa', 'Need information',
             'Ili tuendelee kukusaidia, tafadhali tutumie taarifa zifuatazo: [eleza unachohitajika]. Mara tu tutakapozipokea, tutaendelea mara moja.',
             'To continue helping you, please send us the following information: [describe what you need]. As soon as we receive it, we will continue right away.'],
            ['howto_apply', 'howto', 'Jinsi ya kuomba', 'How to apply',
             'Ili kuomba mkopo: fungua Dashibodi → chagua bidhaa → Omba → kamilisha hatua → wasilisha. Fuatilia chini ya Mikopo.',
             'To apply: open Dashboard → choose a product → Apply → complete steps → submit. Track under Loans.'],
            ['howto_pay', 'payments', 'Jinsi ya kulipa', 'How to pay',
             'Fungua Malipo, chagua unacholipia, thibitisha kiasi, weka nambari, idhinisha kwenye simu. Subiri uthibitisho.',
             'Open Payments, select the item, confirm amount, enter number, approve on your phone. Wait for confirmation.'],
            ['howto_pin', 'login', 'Weka upya PIN', 'Reset PIN',
             'Kwenye kuingia, gusa Umesahau PIN, weka msimbo wa SMS, chagua PIN mpya ya tarakimu 4.',
             'On login, tap Forgot PIN, enter the SMS code, choose a new 4-digit PIN.'],
            ['guarantor_help', 'guarantor', 'Mdhamini', 'Guarantor',
             'Wadhamini wanaalikwa kutoka hatua ya ombi. Hakikisha simu ni sahihi; mdhamini anakubali kutoka kiungo.',
             'Guarantors are invited from the application step. Confirm the phone; they accept from the invite link.'],
            ['application_status', 'application', 'Hali ya ombi', 'Application status',
             'Ombi {application_no} liko kwenye akaunti yako. Fungua Mikopo → chagua ombi kuona maombi yanayosubiri.',
             'Application {application_no} is in your account. Open Loans → select it to see pending requests.'],
            ['screening', 'screening', 'Uchunguzi', 'Screening',
             'Timu ya Uchunguzi inakagua faili yako. Tutakujulisha hapa ikiwa taarifa za ziada zitahitajika.',
             'Screening is reviewing your file. We will update you here if more information is needed.'],
            ['offer', 'approval', 'Ofa', 'Offer',
             'Ofa yako iko tayari katika akaunti yako. Soma masharti kabla ya kukubali.',
             'Your offer is ready in your account. Please read the terms before accepting.'],
            ['repayment', 'repayment', 'Marejesho', 'Repayment',
             'Ratiba na salio ziko chini ya Mikopo. Kulipa: Malipo → marejesho ya mkopo {loan_no}.',
             'Schedule and balance are under Loans. To pay: Payments → repayment for loan {loan_no}.'],
            ['partner_help', 'partner', 'Mshirika', 'Partner',
             'Kamilisha KYC na makubaliano kwenye wasifu. Kwa kazi, tueleze namba ya kazi au ombi.',
             'Complete KYC and agreements in your profile. For a job, share the job or application number.'],
            ['escalated', 'escalation', 'Tumelifikisha', 'Escalated',
             'Tumelifikisha suala lako kwa timu husika (kesi {case_no}). Tutakujulisha hapa tutakapopata mrejesho.',
             'We forwarded your issue to the relevant team (case {case_no}). We will update you here when we hear back.'],
            ['apology_delay', 'apology', 'Samahani', 'Apology delay',
             'Samahani kwa kuchelewa. Tunashughulikia suala lako sasa na tutarudi kwako hapa haraka.',
             'Sorry for the delay. We are handling your issue now and will return here shortly.'],
            ['resolved', 'resolution', 'Limetatuliwa', 'Resolved',
             'Habari {member_first_name}, suala lako limekamilishwa. Tunatumaini tumekusaidia. Tafadhali tathmini huduma kwa nyota 1–5.',
             'Hello {member_first_name}, your issue is complete. We hope we helped. Please rate us 1–5 stars.'],
            ['follow_up', 'follow_up', 'Ufuatiliaji', 'Follow-up',
             'Tunakufuatilia kuhusu suala lako. Je, bado unahitaji msaada, au tunaweza kufunga mazungumzo?',
             'Following up on your issue. Do you still need help, or may we close this conversation?'],
            ['confirm', 'closing', 'Thibitisha', 'Confirmation',
             'Je, tumekusaidia? Thibitisha kama suala limetatuliwa, au niambie kama bado kuna kitu.',
             'Have we helped? Confirm if resolved, or tell us if something still needs attention.'],
            ['thanks', 'closing', 'Asante', 'Thank you',
             'Asante kwa kuwasiliana na Kopafasta. Tuko hapa unapohitaji. Simu: {support_phone}',
             'Thank you for contacting Kopafasta. We are here when you need us. Phone: {support_phone}'],
        ];

        return array_map(static fn (array $r) => [
            'key' => $r[0],
            'group' => $r[1],
            'label_sw' => $r[2],
            'label_en' => $r[3],
            'body_sw' => $r[4],
            'body_en' => $r[5],
        ], $rows);
    }


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
    public function compose(string $key, string $locale = 'sw', array $vars = [], bool $withSignature = false): string
    {
        $body = $this->bodyFor($key, $locale);
        foreach ($vars as $name => $value) {
            $body = str_replace('{'.$name.'}', (string) $value, $body);
        }

        // Never leave broken "Habari ," / "jina langu ni  kutoka" after empty placeholders.
        $body = preg_replace('/\{[a-z_]+\}/', '', $body) ?? $body;
        $body = preg_replace('/\bHabari\s+,/u', 'Habari,', $body) ?? $body;
        $body = preg_replace('/\bHello\s+,/u', 'Hello,', $body) ?? $body;
        $body = preg_replace('/jina langu ni\s+kutoka/u', 'jina langu ni Mtoa huduma kutoka', $body) ?? $body;
        $body = preg_replace('/my name is\s+from/u', 'my name is a support agent from', $body) ?? $body;
        $body = preg_replace('/[ \t]{2,}/', ' ', $body) ?? $body;
        $body = trim($body);

        // Signature/footer only when explicitly requested (first human introduction).
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
