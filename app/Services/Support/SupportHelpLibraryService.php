<?php

namespace App\Services\Support;

/**
 * Grouped FAQ + HOW TO library for Support Home (Member/Partner).
 * Settings-backed override key: support.help_library
 */
class SupportHelpLibraryService
{
    public const SETTING_KEY = 'support.help_library';

    /**
     * @return list<array{key:string, label_en:string, label_sw:string, audience:string, faqs:list<array>, howtos:list<array>}>
     */
    public function groups(string $audience = 'member'): array
    {
        $stored = \App\Models\Setting::get(self::SETTING_KEY);
        $groups = is_array($stored) && $stored !== [] ? $stored : $this->defaults();

        return collect($groups)
            ->filter(function (array $g) use ($audience) {
                $aud = (string) ($g['audience'] ?? 'both');

                return $aud === 'both' || $aud === $audience;
            })
            ->values()
            ->all();
    }

    /**
     * Flat searchable rows for Support Home search.
     *
     * @return list<array{type:string, group:string, title:string, body:string, steps?:list<string>}>
     */
    public function searchable(string $audience = 'member', ?string $locale = null): array
    {
        $isSw = str_starts_with(strtolower((string) ($locale ?: app()->getLocale())), 'sw');
        $rows = [];

        foreach ($this->groups($audience) as $group) {
            $groupLabel = $isSw
                ? (string) ($group['label_sw'] ?? $group['label_en'] ?? '')
                : (string) ($group['label_en'] ?? $group['label_sw'] ?? '');

            foreach ($group['faqs'] ?? [] as $faq) {
                $rows[] = [
                    'type' => 'faq',
                    'group' => $groupLabel,
                    'title' => $isSw ? (string) ($faq['q_sw'] ?? $faq['q_en'] ?? '') : (string) ($faq['q_en'] ?? $faq['q_sw'] ?? ''),
                    'body' => $isSw ? (string) ($faq['a_sw'] ?? $faq['a_en'] ?? '') : (string) ($faq['a_en'] ?? $faq['a_sw'] ?? ''),
                ];
            }

            foreach ($group['howtos'] ?? [] as $how) {
                $steps = $isSw ? ($how['steps_sw'] ?? $how['steps_en'] ?? []) : ($how['steps_en'] ?? $how['steps_sw'] ?? []);
                $rows[] = [
                    'type' => 'howto',
                    'group' => $groupLabel,
                    'title' => $isSw ? (string) ($how['title_sw'] ?? $how['title_en'] ?? '') : (string) ($how['title_en'] ?? $how['title_sw'] ?? ''),
                    'body' => $isSw ? (string) ($how['intro_sw'] ?? $how['intro_en'] ?? '') : (string) ($how['intro_en'] ?? $how['intro_sw'] ?? ''),
                    'steps' => array_values(array_filter(array_map('strval', (array) $steps))),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{type:string, group:string, title:string, body:string, steps?:list<string>}>  $rows
     * @return list<array{type:string, group:string, title:string, body:string, steps?:list<string>}>
     */
    public function search(string $query, string $audience = 'member', ?string $locale = null): array
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return [];
        }

        return collect($this->searchable($audience, $locale))
            ->filter(function (array $row) use ($q) {
                $hay = mb_strtolower(implode(' ', [
                    $row['type'] ?? '',
                    $row['group'] ?? '',
                    $row['title'] ?? '',
                    $row['body'] ?? '',
                    implode(' ', $row['steps'] ?? []),
                ]));

                return str_contains($hay, $q);
            })
            ->take(24)
            ->values()
            ->all();
    }

    /**
     * @return list<array{key:string, label_en:string, label_sw:string, audience:string, faqs:list<array>, howtos:list<array>}>
     */
    public function defaults(): array
    {
        return [
            [
                'key' => 'getting_started',
                'label_en' => 'Getting started',
                'label_sw' => 'Kuanza',
                'audience' => 'both',
                'faqs' => [
                    [
                        'q_en' => 'How do I join Kopafasta?',
                        'q_sw' => 'Ninawezaje kujiunga na Kopafasta?',
                        'a_en' => 'Register with your phone number, set a PIN, then complete your profile. Membership unlocks loan applications.',
                        'a_sw' => 'Jisajili kwa nambari ya simu, weka PIN, kisha kamilisha wasifu. Uanachama hufungua maombi ya mikopo.',
                    ],
                ],
                'howtos' => [
                    [
                        'title_en' => 'Complete your profile',
                        'title_sw' => 'Kamilisha wasifu wako',
                        'intro_en' => 'A complete profile speeds up Screening.',
                        'intro_sw' => 'Wasifu kamili huharakisha Uchunguzi.',
                        'steps_en' => ['Open Profile', 'Fill personal and contact details', 'Upload identity documents', 'Save — look for the green saved tab'],
                        'steps_sw' => ['Fungua Wasifu', 'Jaza taarifa binafsi na mawasiliano', 'Pakia hati za utambulisho', 'Hifadhi — angalia kibao cha kijani Imehifadhiwa'],
                    ],
                ],
            ],
            [
                'key' => 'account_profile',
                'label_en' => 'Account & profile',
                'label_sw' => 'Akaunti na wasifu',
                'audience' => 'both',
                'faqs' => [
                    [
                        'q_en' => 'How do I reset my PIN?',
                        'q_sw' => 'Ninawezaje kuweka upya PIN?',
                        'a_en' => 'Use Forgot PIN on the login screen. We send a code to your registered phone.',
                        'a_sw' => 'Tumia Umesahau PIN kwenye skrini ya kuingia. Tunatuma msimbo kwa simu yako iliyosajiliwa.',
                    ],
                ],
                'howtos' => [
                    [
                        'title_en' => 'Reset PIN',
                        'title_sw' => 'Weka upya PIN',
                        'intro_en' => 'Keep your registered phone nearby.',
                        'intro_sw' => 'Hakikisha simu yako iliyosajiliwa iko karibu.',
                        'steps_en' => ['Open login', 'Tap Forgot PIN', 'Enter the SMS code', 'Choose a new 4-digit PIN'],
                        'steps_sw' => ['Fungua kuingia', 'Gusa Umesahau PIN', 'Weka msimbo wa SMS', 'Chagua PIN mpya ya tarakimu 4'],
                    ],
                ],
            ],
            [
                'key' => 'loan_applications',
                'label_en' => 'Loan applications',
                'label_sw' => 'Maombi ya mikopo',
                'audience' => 'member',
                'faqs' => [
                    [
                        'q_en' => 'Where do I apply for a loan?',
                        'q_sw' => 'Ninaomba wapi mkopo?',
                        'a_en' => 'From your Dashboard choose a product, then Apply. Stay inside your account for the whole journey.',
                        'a_sw' => 'Kutoka Dashibodi chagua bidhaa, kisha Omba. Endelea ndani ya akaunti yako kwa safari yote.',
                    ],
                ],
                'howtos' => [
                    [
                        'title_en' => 'Apply for a loan',
                        'title_sw' => 'Omba mkopo',
                        'intro_en' => 'Have your profile and documents ready.',
                        'intro_sw' => 'Hakikisha wasifu na hati zipo tayari.',
                        'steps_en' => ['Open Dashboard', 'Pick a product', 'Tap Apply', 'Complete required steps', 'Submit and track status'],
                        'steps_sw' => ['Fungua Dashibodi', 'Chagua bidhaa', 'Gusa Omba', 'Kamilisha hatua zinazohitajika', 'Wasilisha na fuatilia hali'],
                    ],
                    [
                        'title_en' => 'Track your application',
                        'title_sw' => 'Fuatilia ombi lako',
                        'intro_en' => 'Status updates appear under Loans / Applications.',
                        'intro_sw' => 'Masasisho ya hali yanaonekana chini ya Mikopo / Maombi.',
                        'steps_en' => ['Open Loans', 'Select the application', 'Read the current stage and any requests'],
                        'steps_sw' => ['Fungua Mikopo', 'Chagua ombi', 'Soma hatua ya sasa na maombi yoyote'],
                    ],
                ],
            ],
            [
                'key' => 'guarantors',
                'label_en' => 'Guarantors',
                'label_sw' => 'Wadhamini',
                'audience' => 'member',
                'faqs' => [
                    [
                        'q_en' => 'When are guarantors required?',
                        'q_sw' => 'Wadhamini wanahitajika lini?',
                        'a_en' => 'When your product and Screening require them. You invite them from the application, not a separate menu.',
                        'a_sw' => 'Bidhaa na Uchunguzi vinapohitaji. Unawaalika kutoka ombi, si menyu tofauti.',
                    ],
                ],
                'howtos' => [
                    [
                        'title_en' => 'Add a guarantor',
                        'title_sw' => 'Ongeza mdhamini',
                        'intro_en' => 'Use the guarantor step on your open application.',
                        'intro_sw' => 'Tumia hatua ya mdhamini kwenye ombi lililo wazi.',
                        'steps_en' => ['Open the application', 'Go to Guarantors', 'Enter phone/name', 'Send invite', 'Wait for acceptance'],
                        'steps_sw' => ['Fungua ombi', 'Nenda Wadhamini', 'Weka simu/jina', 'Tuma mwaliko', 'Subiri kukubaliwa'],
                    ],
                ],
            ],
            [
                'key' => 'payments_fees',
                'label_en' => 'Payments & fees',
                'label_sw' => 'Malipo na ada',
                'audience' => 'both',
                'faqs' => [
                    [
                        'q_en' => 'How do I make a payment?',
                        'q_sw' => 'Ninafanyaje malipo?',
                        'a_en' => 'Open Payments, choose what you are paying for, enter your number, and wait for confirmation from the payment provider.',
                        'a_sw' => 'Fungua Malipo, chagua unacholipia, weka nambari, subiri uthibitisho kutoka mtoa huduma wa malipo.',
                    ],
                ],
                'howtos' => [
                    [
                        'title_en' => 'Make a payment',
                        'title_sw' => 'Fanya malipo',
                        'intro_en' => 'Stay on the payment screen until status updates.',
                        'intro_sw' => 'Kaa kwenye skrini ya malipo hadi hali isasishwe.',
                        'steps_en' => ['Open Payments', 'Select the item', 'Confirm amount', 'Enter mobile money number', 'Approve on your phone'],
                        'steps_sw' => ['Fungua Malipo', 'Chagua kipengele', 'Thibitisha kiasi', 'Weka nambari ya simu', 'Idhinisha kwenye simu yako'],
                    ],
                ],
            ],
            [
                'key' => 'repayments',
                'label_en' => 'Repayments',
                'label_sw' => 'Marejesho',
                'audience' => 'member',
                'faqs' => [
                    [
                        'q_en' => 'Where do I see my repayment schedule?',
                        'q_sw' => 'Ninaona wapi ratiba ya marejesho?',
                        'a_en' => 'Open Loans, select the active loan, then view the schedule and outstanding balance.',
                        'a_sw' => 'Fungua Mikopo, chagua mkopo hai, kisha angalia ratiba na salio.',
                    ],
                ],
                'howtos' => [],
            ],
            [
                'key' => 'marketplace',
                'label_en' => 'Marketplace / assets',
                'label_sw' => 'Soko / mali',
                'audience' => 'member',
                'faqs' => [
                    [
                        'q_en' => 'How does asset financing work?',
                        'q_sw' => 'Ufadhili wa mali unafanyaje kazi?',
                        'a_en' => 'Browse Marketplace assets linked to your product, then continue the application with that asset where required.',
                        'a_sw' => 'Vinjari mali za Soko zinazohusiana na bidhaa yako, kisha endelea na ombi ukitumia mali hiyo inapohitajika.',
                    ],
                ],
                'howtos' => [],
            ],
            [
                'key' => 'plus',
                'label_en' => 'Kopafasta Plus',
                'label_sw' => 'Kopafasta Plus',
                'audience' => 'member',
                'faqs' => [
                    [
                        'q_en' => 'What is Kopafasta Plus?',
                        'q_sw' => 'Kopafasta Plus ni nini?',
                        'a_en' => 'Learning and growth content for members. Open Plus from your account menu.',
                        'a_sw' => 'Maudhui ya kujifunza na kukua kwa wanachama. Fungua Plus kutoka menyu ya akaunti.',
                    ],
                ],
                'howtos' => [],
            ],
            [
                'key' => 'rewards',
                'label_en' => 'Rewards / referrals',
                'label_sw' => 'Zawadi / rufaa',
                'audience' => 'member',
                'faqs' => [
                    [
                        'q_en' => 'How do referrals work?',
                        'q_sw' => 'Rufaa zinafanyaje kazi?',
                        'a_en' => 'Share your referral link from Rewards / Affiliate where enabled. Terms follow your agreement.',
                        'a_sw' => 'Shiriki kiungo chako kutoka Zawadi / Affiliate inapowezeshwa. Masharti yafuate makubaliano yako.',
                    ],
                ],
                'howtos' => [],
            ],
            [
                'key' => 'partner_account',
                'label_en' => 'Partner account',
                'label_sw' => 'Akaunti ya Mshirika',
                'audience' => 'partner',
                'faqs' => [
                    [
                        'q_en' => 'How do I become a Partner or Affiliate?',
                        'q_sw' => 'Ninawezaje kuwa Mshirika au Affiliate?',
                        'a_en' => 'Apply from the public Partner pages, complete KYC, then accept the agreement when approved.',
                        'a_sw' => 'Omba kutoka kurasa za umma za Washirika, kamilisha KYC, kisha kubali makubaliano utakapoidhinishwa.',
                    ],
                ],
                'howtos' => [
                    [
                        'title_en' => 'Become a Partner / Affiliate',
                        'title_sw' => 'Kuwa Mshirika / Affiliate',
                        'intro_en' => 'Use the public apply flow, then track activation.',
                        'intro_sw' => 'Tumia mtiririko wa ombi la umma, kisha fuatilia uanzishaji.',
                        'steps_en' => ['Open Partner apply', 'Complete the form', 'Submit documents', 'Wait for review', 'Accept agreement and activate'],
                        'steps_sw' => ['Fungua ombi la Mshirika', 'Kamilisha fomu', 'Wasilisha hati', 'Subiri ukaguzi', 'Kubali makubaliano na anzisha'],
                    ],
                ],
            ],
            [
                'key' => 'security_login',
                'label_en' => 'Security / login',
                'label_sw' => 'Usalama / kuingia',
                'audience' => 'both',
                'faqs' => [
                    [
                        'q_en' => 'I cannot log in — what should I do?',
                        'q_sw' => 'Siwezi kuingia — nifanye nini?',
                        'a_en' => 'Confirm you use the registered phone and PIN. Try Forgot PIN. If still blocked, Talk to Support.',
                        'a_sw' => 'Hakikisha unatumia simu na PIN zilizosajiliwa. Jaribu Umesahau PIN. Bado ukizuiwa, Ongea na Usaidizi.',
                    ],
                ],
                'howtos' => [],
            ],
            [
                'key' => 'complaints_support',
                'label_en' => 'Complaints & support',
                'label_sw' => 'Malalamiko na usaidizi',
                'audience' => 'both',
                'faqs' => [
                    [
                        'q_en' => 'How do I raise a complaint?',
                        'q_sw' => 'Ninawezaje kuwasilisha malalamiko?',
                        'a_en' => 'Use Send feedback and choose Complaint, or Talk to Support. Serious issues may become a Case for follow-up.',
                        'a_sw' => 'Tumia Tuma maoni na chagua Malalamiko, au Ongea na Usaidizi. Masuala mazito yanaweza kuwa Kesi ya ufuatiliaji.',
                    ],
                ],
                'howtos' => [],
            ],
        ];
    }
}
