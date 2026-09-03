<?php

return [
    'yes' => 'Ndiyo',
    'no' => 'Hapana',
    'quarterly' => 'kila miezi 3',
    'days' => 'siku',
    'title' => 'Masharti ya Msambazaji',
    'accept' => 'Nimesoma na ninakubali Masharti haya ya Msambazaji',
    'accept_button' => 'Kubali Masharti ya Msambazaji',
    'required' => 'Kubali Masharti ya Msambazaji ili kuendelea.',
    'required_before_membership' => 'Kubali Masharti ya Msambazaji kabla ya kulipa uanachama.',
    'already_accepted' => 'Tayari umekubali Masharti ya Msambazaji yaliyotumika wakati huo.',
    'annual_membership_term' => 'Uanachama wa mwaka wa siku :days',
    'general_provisions' => 'Masharti ya jumla',
    'contract_years' => '{1} mwaka :count|[2,*] miaka :count',
    'contract_months' => '{1} mkataba wa miezi :count|[2,*] mkataba wa miezi :count',
    'body' => <<<'TEXT'
# Masharti ya Msambazaji wa {{brand}}

Hili ni ombi la kuwa Msambazaji huru wa {{brand}}; si ombi la ajira.

## 1. Uhusiano huru
Unafanya kazi kama Msambazaji huru wa kibiashara. Usiwajulishe wateja kuwa wewe ni mfanyakazi, afisa, au wakala mwenye mamlaka ya kufunga {{brand}}, wala usitoze ada yoyote isiyoruhusiwa.

## 2. Uanachama au Mkataba wa Premium
Wasambazaji wa Kawaida wanaweza kuhitaji malipo ya ada ya uanachama wa mwaka. Ada ya mtu binafsi ni {{membership_fee_individual}} na ada ya kampuni ni {{membership_fee_company}}, kwa muda wa siku {{membership_duration}}, na neema ya malipo ya saa {{membership_grace_hours}} kama ilivyowekwa kwenye Mipangilio.

Wasambazaji wa Premium wanafanya kazi chini ya Mkataba maalum wa Msambazaji wa Premium wa {{premium_contract_label}} (miezi {{premium_contract_months}}) kuanzia {{agreement_start}} hadi {{agreement_end}}, isipokuwa kusimamishwa au kusitishwa mapema chini ya Masharti haya. Wasambazaji wa Premium hawalipi ada ya uanachama wa mwaka isipokuwa Mipangilio ikaagiza wazi.

Idhini ya ombi inamaanisha {{brand}} iko tayari kukukubali. Kwa Wasambazaji wa Kawaida, malipo ya uanachama yanaanzisha kushiriki kibiashara yanapohitajika. Kwa Wasambazaji wa Premium, kukubali Masharti haya na kuanzisha Mkataba wa Premium kunaanzisha kushiriki kibiashara.

## 3. Misimbo na kamisheni
Msimbo wako unaweza kuwekwa wakati rekodi ya mshirika inapoundwa. Unakuwa unafanya kazi kwa rufaa mpya zinazostahili tu wakati ombi limeidhinishwa, akaunti inastahili, KYC imetoshelezwa inapohitajika, uanachama uko hai, utendaji unaruhusiwa, na uzingatiaji uko wazi.

Rufaa, kamisheni, na kumbukumbu za daftari za kihistoria zinahifadhiwa ikiwa ustahiki utabadilika baadaye.

## 4. Tathmini ya utendaji
Wasambazaji wanatathminiwa kwa mzunguko kulingana na biashara stahiki, ubadilishaji, shughuli, ubora, na vipimo vingine vinavyotumika.

Kipindi cha tathmini: {{assessment_period_label}} (siku {{assessment_period}}).
Kipindi cha kuanza kabla ya kutekeleza kiasi: siku {{ramp_up_days}}.
Rufaa stahiki za chini: {{minimum_qualified_referrals}} kwa kila kipindi (KPI hiyo ikiwa imewezeshwa).

Utendaji ukishuka chini ya kiwango, {{brand}} inaweza kutoa onyo otomatiki. Kushindwa kuendelea kwa vipindi {{suspension_periods}} mfululizo kunaweza kuleta kusimamishwa kiotomatiki kulingana na Sera ya Utendaji (toleo {{policy_version}}). Onyo huanza kulingana na vipindi vilivyowekwa ({{warning_periods}}).

Kurudisha kiotomatiki baada ya kusimamishwa kwa utendaji: {{recovery_enabled}}.

## 5. Mwenendo
Usipotoshe {{brand}}, usitoze ada zisizoidhinishwa, wala usitumie utangazaji wa udanganyifu. Masuala ya uzingatiaji au udanganyifu yanaweza kuzuia, kusimamisha, au kusitisha akaunti tofauti na hali ya utendaji.

## 6. Mabadiliko
Thamani zinazobadilika katika Masharti haya zinatokana na Kitovu cha Mipangilio. Mabadiliko muhimu yanaweza kuhitaji kukubali upya au kutumika wakati wa kuhuisha, kulingana na Mipangilio. Toleo unalokubali linahifadhiwa na halibadilishwi Mipangilio inapobadilika baadaye.
TEXT,
];
