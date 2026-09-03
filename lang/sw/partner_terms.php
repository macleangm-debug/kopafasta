<?php

return [
    'yes' => 'Ndiyo',
    'no' => 'Hapana',
    'sla_starts_assignment' => 'SLA ya kukamilisha huanza kazi au kesi inapogawiwa, si inapokubaliwa.',
    'accept' => 'Nimesoma na ninakubali Masharti haya',
    'accept_button' => 'Kubali Masharti',
    'required' => 'Kubali Masharti ya sasa kabla ya kupokea kazi.',
    'already_accepted' => 'Tayari umekubali Masharti yaliyotumika wakati huo.',
    'types' => [
        'valuer' => 'Mthamini',
        'gps_installer' => 'Msakinishaji wa GPS',
        'insurance' => 'Mshirika wa bima',
        'call_center' => 'Mshirika wa kituo cha simu',
        'debt_collector' => 'Mkusanyaji wa deni',
        'auctioneer' => 'Mnada',
        'legal_partner' => 'Mshirika wa kisheria',
    ],
    'valuer' => [
        'title' => 'Masharti ya Mthamini',
        'body' => <<<'TEXT'
# Masharti ya Mthamini wa {{brand}}

Masharti haya yanatawala kazi huru ya uthamini kwa {{brand}}. Si mkataba wa ajira.

## 1. Uteuzi huru
Unafanya kazi kama mthamini huru. Usiwajulishe wateja kuwa wewe ni mfanyakazi wa {{brand}}.

## 2. Ugawaji na SLA
Kazi hugawiwa kulingana na Mipangilio ya ugawaji wa asili. SLA ya kukamilisha ni siku {{sla_days}} (saa {{sla_hours}}). {{sla_starts}} Vikumbusho hutumwa saa {{remind_hours}} zilizobaki. Baada ya mwisho, neema ya saa {{grace_hours}} inaweza kutumika kabla ya kugawa kwa mthamini mwingine (ugawaji otomatiki wa juu {{max_reassignments}}). Kazi iliyoghairiwa haiandikiki tena.

## 3. Ukaguzi, ushahidi na usahihi
Kamilisha ukaguzi, picha zinazohitajika, na takwimu za uthamini kwa uaminifu.

## 4. Mwenendo, usiri na data
Watendee wateja kwa heshima. Weka data ya mkopaji na mali kuwa siri.

## 5. Ada, uanachama na mapato
Uanachama unahitajika: {{membership_required}}. Ada ya mtu {{membership_fee_individual}}, kampuni {{membership_fee_company}}. Kazi zilizokamilika huenda kwenye pochi ya mshirika. Rekodi za historia zinahifadhiwa.

## 6. Utendaji, onyo na kusimamishwa
Lengo la kwa wakati: {{target_on_time}}%. Lengo la kukamilisha: {{target_completion}}%. Alama baada ya kazi {{min_jobs_for_score}}. Tathmini {{warnings_before_suspend}} za hatari zinaweza kusimamisha. Kurejesha kiotomatiki: {{auto_recover}}. Zuio la uzingatiaji/udanganyifu/utawala halirejeshwi kwa KPI.

## 7. Kukomesha na migogoro
{{brand}} inaweza kuacha kugawa kazi. Migogoro chini ya sheria za Tanzania. Sera {{policy_version}}. Makubaliano {{agreement_version}}. Mwenendo {{conduct_version}}.

## 8. Mabadiliko
Namba za SLA na utendaji zinatokana na Mipangilio. Toleo unalokubali linahifadhiwa na halibadilishwi kimya.
TEXT,
    ],
    'gps_installer' => [
        'title' => 'Masharti ya Msakinishaji wa GPS',
        'body' => <<<'TEXT'
# Masharti ya Msakinishaji wa GPS wa {{brand}}

Masharti haya yanahusu usakinishaji wa asili na, inapogawiwa, kazi ya GPS ya urejeshaji. Hizo ni SLA tofauti.

## 1. Mkandarasi huru
Wewe ni msakinishaji huru, si mfanyakazi wa {{brand}}.

## 2. SLA ya usakinishaji
SLA: siku {{sla_days}} (saa {{sla_hours}}). {{sla_starts}} Vikumbusho: saa {{remind_hours}}. Neema: saa {{grace_hours}}. Ugawaji upya: {{max_reassignments}}.

## 3. Kazi ya GPS ya urejeshaji
SLA ya urejeshaji: siku {{recovery_sla_days}}. Vikumbusho: siku {{recovery_remind_days}} kabla ya tarehe. Muda ukiisha, kesi inaweza kupandishwa hatua, si kwa msakinishaji mwingine wa aina ileile.

## 4. Ushahidi na vifaa
Sakinisha au ondoa vifaa kama ilivyoagizwa na hifadhi ushahidi.

## 5. Utendaji
Lengo la kwa wakati {{target_on_time}}%. Kukamilisha {{target_completion}}%. Onyo kabla ya kusimamishwa: {{warnings_before_suspend}}. Kurejesha: {{auto_recover}}. Sera {{policy_version}}. Makubaliano {{agreement_version}}.
TEXT,
    ],
    'insurance' => [
        'title' => 'Masharti ya Mshirika wa Bima',
        'body' => <<<'TEXT'
# Masharti ya Mshirika wa Bima wa {{brand}}

Kazi hugawiwa chini ya ugawaji wa asili. SLA: siku {{sla_days}} (saa {{sla_hours}}). {{sla_starts}} Vikumbusho: saa {{remind_hours}}. Neema: saa {{grace_hours}}. Ugawaji upya: {{max_reassignments}}.

Toa bima na nyaraka kupitia lango. Usiandike bima ya uongo. Lengo la kwa wakati {{target_on_time}}%. Kukamilisha {{target_completion}}%. Kurejesha: {{auto_recover}}. Uanachama: {{membership_required}}. Sera {{policy_version}}. Makubaliano {{agreement_version}}.
TEXT,
    ],
    'call_center' => [
        'title' => 'Masharti ya Kituo cha Simu',
        'body' => <<<'TEXT'
# Masharti ya Kituo cha Simu cha {{brand}}

SLA: siku {{sla_days}}. {{sla_starts}} Vikumbusho: siku {{recovery_remind_days}} kabla. Muda ukiisha, kesi inaweza kupandishwa hatua, si kituo kingine kwa chaguo-msingi.

Tumia tu vitendo vya lango vilivyoidhinishwa. Lengo la kwa wakati {{target_on_time}}%. Onyo kabla ya kusimamishwa: {{warnings_before_suspend}}. Kurejesha: {{auto_recover}}. Sera {{policy_version}}. Mwenendo {{conduct_version}}.
TEXT,
    ],
    'debt_collector' => [
        'title' => 'Masharti ya Mkusanyaji wa Deni',
        'body' => <<<'TEXT'
# Masharti ya Mkusanyaji wa Deni wa {{brand}}

SLA: siku {{sla_days}} tangu kugawiwa. {{sla_starts}} Vikumbusho: siku {{recovery_remind_days}}. Muda ukiisha, kesi hupandishwa hatua, si kwa mkusanyaji mwingine.

Fuata sheria za Tanzania. Hakuna vurugu wala aibu ya umma. Lengo la kwa wakati {{target_on_time}}%. Onyo: {{warnings_before_suspend}}. Kurejesha: {{auto_recover}}. Sera {{policy_version}}. Mwenendo {{conduct_version}}.
TEXT,
    ],
    'auctioneer' => [
        'title' => 'Masharti ya Mnada',
        'body' => <<<'TEXT'
# Masharti ya Mnada wa {{brand}}

SLA: siku {{sla_days}}. {{sla_starts}} Vikumbusho: siku {{recovery_remind_days}}. Endesha mnada kwa haki na andika mapato. Lengo la kwa wakati {{target_on_time}}%. Kurejesha: {{auto_recover}}. Sera {{policy_version}}.
TEXT,
    ],
    'legal_partner' => [
        'title' => 'Masharti ya Mshirika wa Kisheria',
        'body' => <<<'TEXT'
# Masharti ya Mshirika wa Kisheria wa {{brand}}

SLA: siku {{sla_days}}. {{sla_starts}} Vikumbusho: siku {{recovery_remind_days}}. Fuata kanuni za taaluma na usiri. Lengo la kwa wakati {{target_on_time}}%. Kurejesha: {{auto_recover}}. Sera {{policy_version}}.
TEXT,
    ],
];
