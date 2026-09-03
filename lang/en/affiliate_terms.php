<?php

return [
    'yes' => 'Yes',
    'no' => 'No',
    'quarterly' => 'every 3 months',
    'days' => 'days',
    'title' => 'Affiliate Terms & Conditions',
    'accept' => 'I have read and accept these Affiliate Terms',
    'accept_button' => 'Accept Affiliate Terms',
    'required' => 'Accept the Affiliate Terms before paying membership.',
    'already_accepted' => 'You have already accepted the Affiliate Terms that applied at the time.',
    'body' => <<<'TEXT'
# {{brand}} Affiliate Terms & Conditions

This is an application to become an independent {{brand}} Affiliate and is not an application for employment.

## 1. Independent relationship
You operate as an independent commercial Affiliate. You must not present yourself as an employee, officer, or agent with authority to bind {{brand}}, and you must not charge customers any unauthorised fee.

## 2. Annual membership
Commercial Affiliate status requires payment of the annual membership fee. The individual fee is {{membership_fee_individual}} and the company fee is {{membership_fee_company}}, for a duration of {{membership_duration}} days, with a payment grace of {{membership_grace_hours}} hours as configured in Settings.

Approval of your application means {{brand}} is willing to accept you. Membership payment, once verified, activates your commercial Affiliate account.

## 3. Promo codes and commissions
Your promo code may be reserved when your partner record is created. It becomes operational for new qualifying referrals only while your application is approved, your account is eligible, KYC is satisfied where required, membership is active, performance is permitted, and compliance is clear.

Historical referrals, commissions, and ledger records are preserved if eligibility later changes.

## 4. Performance assessment
Affiliates are subject to periodic performance assessment based on qualified business generated, conversion, activity, quality and other applicable performance measures.

Assessment period: {{assessment_period_label}} ({{assessment_period}} days).
Ramp-up before volume enforcement: {{ramp_up_days}} days.
Minimum qualified referrals: {{minimum_qualified_referrals}} per assessment period (when that KPI is enabled).

Where performance falls below the applicable minimum requirements, {{brand}} may issue automated performance warnings. Continued failure for {{suspension_periods}} consecutive assessment periods may result in automatic suspension in accordance with the Affiliate Performance Policy (policy version {{policy_version}}). Warnings begin according to the configured warning periods ({{warning_periods}}).

Automatic recovery after a performance suspension: {{recovery_enabled}}.

## 5. Conduct
You must not misrepresent {{brand}}, collect unauthorised customer fees, or use deceptive marketing. Compliance or fraud concerns may result in restriction, suspension, or termination separate from performance status.

## 6. Changes
Configurable values in these Terms come from Settings Hub. Material changes may require re-acceptance or apply on renewal, according to Settings. The version you accept is snapshotted and is not rewritten when Settings later change.
TEXT,
];
