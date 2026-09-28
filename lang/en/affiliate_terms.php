<?php

return [
    'yes' => 'Yes',
    'no' => 'No',
    'quarterly' => 'every 3 months',
    'days' => 'days',
    'title' => 'Affiliate Terms & Conditions',
    'accept' => 'I have read and accept these Affiliate Terms',
    'accept_button' => 'Accept Affiliate Terms',
    'required' => 'Accept the Affiliate Terms to continue.',
    'required_before_membership' => 'Accept the Affiliate Terms before paying membership.',
    'already_accepted' => 'You have already accepted the Affiliate Terms that applied at the time.',
    'annual_membership_term' => ':days-day annual membership',
    'general_provisions' => 'General provisions',
    'contract_years' => '{1} :count year|[2,*] :count years',
    'contract_months' => '{1} :count-month agreement|[2,*] :count-month agreement',
    'membership_not_required_premium' => 'Premium Affiliates do not pay an annual membership fee unless Settings explicitly require it.',
    'membership_not_required_territory' => 'Annual Affiliate membership does not apply under the current territory Settings.',
    'membership_required_clause' => 'Affiliates may require payment of the annual membership fee. The individual fee is :membership_fee_individual and the company fee is :membership_fee_company, for a duration of :membership_duration days, with a payment grace of :membership_grace_hours hours as configured in Settings.',
    'body' => <<<'TEXT'
# {{brand}} Affiliate Terms & Conditions

This is an application to become an independent {{brand}} Affiliate and is not an application for employment.

## 1. Independent relationship
You operate as an independent commercial Affiliate. You must not present yourself as an employee, officer, or agent with authority to bind {{brand}}, and you must not charge customers any unauthorised fee.

## 2. Membership or Premium agreement
{{membership_clause}}

Premium Affiliates operate under a fixed Premium Affiliate Agreement for {{premium_contract_label}} ({{premium_contract_months}} months) from {{agreement_start}} to {{agreement_end}}, unless suspended or terminated earlier under these Terms.

Approval of your application means {{brand}} is willing to accept you. Where membership is required, membership payment activates commercial sharing. Where membership does not apply, acceptance of these Terms and account eligibility activate commercial sharing.

## 3. Commercial terms (effective rates)
Territory: {{territory}}.
Rate source: {{rate_source}}.
Effective from: {{commercial_effective_from}}.

Commission
- Affiliate commission on qualifying referrals: {{commission_percent}}.

Customer benefit / discount
- Registration fee discount: {{registration_discount_percent}}.
- Application fee discount: {{application_discount_percent}}.
- Kopafasta Plus customer discount: {{plus_discount_percent}}.

Commission is earned only after a configured qualifying event is confirmed — not merely when a person clicks, visits, or registers. Withdrawals follow the Affiliate wallet and minimum payout rules in Settings. These are the actual effective commercial terms for this Affiliate. A later Settings Hub change does not rewrite a signed acceptance.

## 4. Promo codes and commissions
Your promo code may be reserved when your partner record is created. It becomes operational for new qualifying referrals only while your application is approved, your account is eligible, KYC is satisfied where required, membership is active where required, performance is permitted, and compliance is clear.

Historical referrals, commissions, and ledger records are preserved if eligibility later changes.

## 5. Performance assessment
Affiliates are subject to periodic performance assessment based on qualified business generated, conversion, activity, quality and other applicable performance measures.

Assessment period: {{assessment_period_label}} ({{assessment_period}} days).
Ramp-up before volume enforcement: {{ramp_up_days}} days.
Minimum qualified referrals: {{minimum_qualified_referrals}} per assessment period (when that KPI is enabled).

Where performance falls below the applicable minimum requirements, {{brand}} may issue automated performance warnings. Continued failure for {{suspension_periods}} consecutive assessment periods may result in automatic suspension in accordance with the Affiliate Performance Policy (policy version {{policy_version}}). Warnings begin according to the configured warning periods ({{warning_periods}}).

Automatic recovery after a performance suspension: {{recovery_enabled}}.

## 6. Conduct
You must not misrepresent {{brand}}, collect unauthorised customer fees, or use deceptive marketing. Compliance or fraud concerns may result in restriction, suspension, or termination separate from performance status.

## 6A. Referral benefit is not loan approval
An Affiliate introduces or refers a member and may provide only the referral benefits configured in Settings. An Affiliate does not approve the loan, cannot guarantee approval, and must not represent an application fee as payment for obtaining a loan. Paying an application fee does not mean that a loan has been approved or will be granted. The lending decision remains under {{brand}}'s assessment, eligibility requirements and approval process.

## 7. Changes
Configurable values in these Terms come from Settings Hub. Material changes may require re-acceptance or apply on renewal, according to Settings. The version you accept is snapshotted and is not rewritten when Settings later change.
TEXT,
    'premium_body' => <<<'TEXT'
# {{brand}} Premium Affiliate Agreement

This is an application to become an independent {{brand}} Premium Affiliate and is not an application for employment.

## 1. Independent relationship
You operate as an independent commercial Affiliate. You must not present yourself as an employee, officer, or agent with authority to bind {{brand}}, and you must not charge customers any unauthorised fee.

## 2. Premium relationship
You are classified as a {{affiliate_type}}. Premium Affiliates are a special partnership focused on brand reach and growing kopafasta. Referrals, commissions, wallet, and withdrawals remain available subject to eligibility and compliance.

{{membership_clause}}

## 3. Agreement term
The Premium Affiliate Agreement runs for {{premium_contract_label}} ({{premium_contract_months}} months) from {{agreement_start}} to {{agreement_end}}, unless suspended or terminated earlier under these Terms. Applicable territory: {{territory}}.

## 4. Commercial terms (effective rates)
Rate source: {{rate_source}}.
Effective from: {{commercial_effective_from}}.

Commission
- Affiliate commission on qualifying referrals: {{commission_percent}}.

Customer benefit / discount
- Registration fee discount: {{registration_discount_percent}}.
- Application fee discount: {{application_discount_percent}}.
- Kopafasta Plus customer discount: {{plus_discount_percent}}.

Example principle: if a referred customer pays a qualifying fee of TZS 10,000 and your commission rate is {{commission_percent}}, commission is calculated on the configured base (original or discounted amount) in Settings. Commission becomes earned only after the qualifying event is confirmed.

Withdrawals follow the Affiliate wallet, minimum payout, and payout-account rules. Commercial changes after acceptance require a new negotiated arrangement and do not silently rewrite this signed agreement.

## 5. Benefits and obligations
Benefits include Share & Earn, promo code use, commission wallet, and withdrawals while eligible. You must complete required identity / KYC, keep a valid payout account, accept this agreement, and market {{brand}} lawfully without charging customers unauthorised fees.

## 6. Promo codes and commissions
Your promo code may be reserved when your partner record is created. It becomes operational for new qualifying referrals only while your application is approved, your account is eligible, KYC is satisfied where required, and compliance is clear.

Historical referrals, commissions, and ledger records are preserved if eligibility later changes.

## 7. Conduct, suspension and termination
You must not misrepresent {{brand}}, collect unauthorised customer fees, or use deceptive marketing. Compliance or fraud concerns may result in restriction, suspension, or termination under the existing Affiliate rules. Premium status does not remove identity, payment, or compliance obligations.

## 7A. Referral benefit is not loan approval
An Affiliate introduces or refers a member and may provide only the referral benefits configured in Settings. An Affiliate does not approve the loan, cannot guarantee approval, and must not represent an application fee as payment for obtaining a loan.

## 8. Changes
The version you accept is snapshotted and is not rewritten when Settings later change. Material commercial amendments for a negotiated Premium arrangement are recorded on the Affiliate commercial card and may require fresh acceptance where Settings require it.
TEXT,
];
