<?php

return [
    'yes' => 'Yes',
    'no' => 'No',
    'quarterly' => 'every 3 months',
    'days' => 'days',
    'title' => 'Affiliate Agreement',
    'premium_title' => 'Premium Affiliate Partnership Agreement',
    'accept' => 'I have read and accept this Affiliate Agreement',
    'accept_button' => 'Accept Affiliate Agreement',
    'required' => 'Accept the Affiliate Agreement to continue.',
    'required_before_membership' => 'Accept the Affiliate Agreement before paying membership.',
    'already_accepted' => 'You have already accepted the Affiliate Agreement that applied at the time.',
    'updated' => 'You have accepted the updated Affiliate Agreement.',
    'annual_membership_term' => ':days-day annual membership',
    'general_provisions' => 'General provisions',
    'contract_years' => '{1} :count year|[2,*] :count years',
    'contract_months' => '{1} :count-month agreement|[2,*] :count-month agreement',
    'membership_not_required_premium' => 'Premium Affiliates do not pay an annual membership fee unless Settings explicitly require it.',
    'membership_not_required_territory' => 'Annual Affiliate membership does not apply under the current territory Settings.',
    'membership_required_clause' => 'Affiliates may require payment of the annual membership fee. The individual fee is :membership_fee_individual and the company fee is :membership_fee_company, for a duration of :membership_duration days, with a payment grace of :membership_grace_hours hours as configured in Settings.',
    'rate_commission' => 'Affiliate commission on qualifying referrals',
    'rate_registration' => 'Registration fee discount',
    'rate_application' => 'Application fee discount',
    'rate_plus' => 'Kopafasta Plus customer discount',
    'benefit_none' => 'No additional customer benefits apply under the current commercial terms.',
    'exclusivity_none' => 'Non-exclusive',
    'negotiated_none' => 'None',
    'withdrawal_terms_text' => 'Withdrawals are subject to the applicable minimum withdrawal amount, account verification, available wallet balance and Kopafasta\'s applicable payment controls.',
    'application_commission_description' => 'Commission linked to qualifying application-related events according to the effective commercial rates.',
    'plus_commission_description' => 'Commission linked to qualifying Kopafasta Plus events according to the effective commercial rates.',
    'other_commission_description' => 'Any other qualifying commission event configured for this Affiliate under the effective commercial rates.',
    'body' => <<<'TEXT'
# {{brand}} Affiliate Agreement

This Affiliate Agreement (“Agreement”) is entered into between **{{brand}}** (“Kopafasta”) and the person or entity identified in the Affiliate Profile (“Affiliate”).

**Effective date:** {{effective_date}}
**Affiliate name:** {{affiliate_name}}
**Affiliate number:** {{affiliate_number}}
**Territory:** {{territory}}

## 1. Appointment and relationship
Kopafasta appoints the Affiliate on a non-exclusive basis to introduce and refer prospective customers to Kopafasta using approved referral links, promo codes, campaigns and promotional materials.

The Affiliate operates as an independent commercial partner. Nothing in this Agreement creates employment, partnership, joint venture, agency or authority for the Affiliate to bind Kopafasta.

The Affiliate must not represent themselves as an employee, credit officer or authorized representative capable of approving loans or making commitments on behalf of Kopafasta.

## 2. What the Affiliate may do
The Affiliate may promote Kopafasta products and services, distribute their approved referral link or promo code, explain publicly available product information and participate in campaigns authorized by Kopafasta.

The Affiliate must use accurate information and must not promise loan approval, guaranteed limits, guaranteed interest/pricing, special treatment or any benefit that has not been authorized by Kopafasta.

All lending, eligibility, screening, approval and disbursement decisions remain solely with Kopafasta.

{{membership_clause}}

## 3. Referrals and attribution
A referral is attributed according to Kopafasta's referral rules in force when the qualifying event occurs.

The Affiliate's promo code and referral link are personal to the Affiliate account and must not be transferred, sold or misrepresented.

Changing a promo code does not transfer historical referrals or commissions to another Affiliate and does not require a new Agreement.

## 4. Commercial terms
The Affiliate earns commissions and referral benefits according to the commercial terms applicable to their account.

**Rate source:** {{rate_source}}
**Effective from:** {{commercial_effective_from}}

{{effective_rates_table}}

These rates are obtained from Kopafasta's Affiliate configuration and the applicable agreement snapshot.

Commission becomes payable only when the event required by the applicable commission rule has been successfully completed and verified.

A registration, application, referral or other activity does not by itself create an entitlement unless it meets the applicable qualifying rule.

## 5. Performance
The Affiliate relationship may include performance measures established by Kopafasta, including paying-member performance and other applicable operational measures.

Performance information may be displayed in the Affiliate Portal.

Failure to achieve a target does not authorize the Affiliate to manipulate registrations, payments, customer identities or transactions.

Any consequence attached to performance must follow the applicable Kopafasta Affiliate rules and the terms communicated to the Affiliate.

## 6. Wallet and withdrawals
Verified commissions are credited through the Kopafasta Affiliate commission and wallet system.

**Minimum withdrawal amount:** {{minimum_withdrawal_amount}}

{{withdrawal_terms}}

Kopafasta may withhold a disputed commission while the underlying transaction is being reviewed.

## 7. Customer benefits
Where an Affiliate promotion provides a benefit to a referred customer, the applicable benefit will be shown in the Affiliate's current commercial terms.

{{customer_benefits_table}}

The Affiliate must not increase, alter or promise benefits beyond those authorized by Kopafasta.

## 8. Marketing and brand use
The Affiliate may use Kopafasta names, logos and approved promotional materials solely for authorized Affiliate activities.

The Affiliate must not create misleading advertisements, impersonate Kopafasta, alter official documents or publish information that could reasonably mislead customers regarding Kopafasta products.

Kopafasta may require correction or removal of inaccurate or unauthorized promotional material.

## 9. Customer information and privacy
The Affiliate must collect or share only information reasonably necessary for an authorized referral activity.

Customer credentials, PINs, passwords or other security credentials must never be requested or retained by the Affiliate.

Personal information obtained through Affiliate activities must be handled only for its authorized purpose and in accordance with applicable data-protection requirements.

## 10. Prohibited conduct
The Affiliate must not create fictitious customers or transactions; submit false information; manipulate referrals or payments to generate commission; collect unauthorized charges from customers; offer bribes or improper inducements; misuse customer information; impersonate Kopafasta staff; or engage in fraud, harassment or unlawful marketing.

Kopafasta may investigate suspicious activity and suspend affected commissions while an investigation is underway.

## 11. Records and reconciliation
Kopafasta's verified transaction and commission records are the primary operational record for calculating commissions.

The Affiliate may raise a query regarding an apparent discrepancy through the available support or complaints channel.

## 12. Term and termination
This Agreement begins on the Effective Date and continues until terminated in accordance with these terms.

Either party may terminate the commercial relationship subject to applicable notice requirements.

Kopafasta may suspend or terminate immediately for fraud, serious misuse of customer information, material misrepresentation, unlawful conduct or another serious breach.

Termination does not remove legitimate commission already earned before termination, subject to reconciliation, reversals and any unresolved investigation.

## 13. Changes
Kopafasta may update its products, operational procedures and future Affiliate commercial terms.

Changes to rates apply prospectively according to their effective date and do not rewrite commissions already earned under an earlier applicable rate.

Material changes will be communicated through the appropriate Kopafasta channel.

## 14. Electronic acceptance
The Affiliate may review and accept this Agreement electronically through the Kopafasta platform.

The system may record the Agreement version, effective commercial terms, acceptance date/time and relevant audit information as evidence of acceptance.

## 15. Governing law and disputes
This Agreement is governed by the laws of the United Republic of Tanzania applicable to the relationship.

The parties should first attempt to resolve disputes through Kopafasta's established complaints or dispute-resolution process before pursuing other remedies available under applicable law.

## 16. Entire agreement
This Agreement, together with the commercial terms captured for the Affiliate and any incorporated schedules, constitutes the agreement governing the Affiliate relationship.

**Affiliate:** {{affiliate_name}}
**{{brand}}**

**Accepted electronically:** {{accepted_at}}
**Agreement version:** {{agreement_version}}
TEXT,
    'premium_body' => <<<'TEXT'
# {{brand}} Premium Affiliate Partnership Agreement

This Premium Affiliate Partnership Agreement (“Agreement”) is entered into between **{{brand}}** (“Kopafasta”) and **{{affiliate_name}}** (“Premium Affiliate”).

**Effective date:** {{effective_date}}
**Affiliate number:** {{affiliate_number}}
**Territory:** {{territory}}
**Commercial terms source:** {{rate_source}}

## 1. Purpose of the partnership
Kopafasta and the Premium Affiliate enter into this relationship to support awareness, trusted introductions, customer acquisition and growth of the Kopafasta brand and its products.

The Premium Affiliate relationship is structured around brand reach, trusted introductions and commercial collaboration.

It is a commercial partnership and does not create employment, agency, joint venture or authority for the Premium Affiliate to approve loans or bind Kopafasta.

## 2. Appointment
Kopafasta appoints the Premium Affiliate on a non-exclusive basis unless the parties expressly agree otherwise in writing.

The Premium Affiliate may promote Kopafasta through approved channels, including their digital platforms, social-media presence, appearances, campaigns, referral links, promo codes and other mutually agreed activities.

{{membership_clause}}

## 3. Brand collaboration
The parties may agree on particular campaigns, content, appearances or promotional activities from time to time.

Unless separately agreed, this Agreement does not require the Premium Affiliate to publish a fixed number of posts, appearances or campaigns.

Both parties should protect each other's legitimate brand reputation.

Kopafasta may request correction or removal of materially inaccurate or unauthorized information concerning its products.

## 4. Promo code and attribution
The Premium Affiliate will have an approved Kopafasta promo code and referral link.

Qualifying customers and transactions are attributed according to the applicable Kopafasta referral rules.

The Premium Affiliate may request a permitted promo-code change through the Affiliate Portal subject to the configured change interval.

Changing a promo code does not require a new Agreement and does not rewrite historical attribution or earned commissions.

## 5. Commercial terms
**Rate source:** {{rate_source}}
**Effective from:** {{commercial_terms_effective_date}}

{{effective_rates_table}}

Where the rate source is Kopafasta Affiliate rates, the rates shown above are the applicable Settings-backed rates captured for this Agreement.

Where the rate source is Individually negotiated, the rates shown above constitute the negotiated commercial arrangement between Kopafasta and this Premium Affiliate.

The values recorded in this Agreement are the controlling commercial snapshot for the applicable agreement period.

A later change to Kopafasta's general Affiliate rates does not retrospectively rewrite this Agreement or commissions already earned under it.

## 6. Customer benefits
{{customer_benefits_table}}

Benefits apply only where the applicable eligibility conditions are satisfied.

The Premium Affiliate may not independently alter or promise additional Kopafasta discounts or financial benefits.

## 7. Commission calculation and payment
Commission is calculated using the qualifying events and rates stated in the applicable commercial terms.

**Application-related commission**
{{application_commission_description}}

**Kopafasta Plus-related commission**
{{plus_commission_description}}

**Other eligible commission**
{{other_commission_description}}

The Affiliate Portal will show verified earnings, wallet balance, withdrawals and relevant transaction history.

No commission becomes payable solely because a person clicked a link, entered a promo code or started an application unless that action is itself an expressly configured qualifying event.

## 8. Wallet and withdrawals
**Minimum withdrawal amount:** {{minimum_withdrawal_amount}}

{{withdrawal_terms}}

## 9. Partnership analytics
Kopafasta may provide analytics showing referrals, registrations, paying members, conversions, commissions and campaign performance so both parties can understand and improve the partnership.

## 10. Content and public communications
The Premium Affiliate should take reasonable care that statements concerning Kopafasta are accurate.

They must not promise guaranteed loan approval, guaranteed limits, unauthorized pricing, guaranteed returns or preferential credit decisions.

Kopafasta remains solely responsible for credit assessment and lending decisions.

## 11. Name, image and intellectual property
Each party retains ownership of its existing names, logos, trademarks, images and other intellectual property.

Kopafasta may use the Premium Affiliate's name, approved image and approved promotional content only to the extent authorized for the partnership or relevant campaign.

The Premium Affiliate may similarly use Kopafasta branding only for authorized partnership activities.

## 12. Exclusivity
**Exclusivity:** {{exclusivity_terms}}

Any category exclusivity must be expressly negotiated and recorded.

## 13. Confidentiality
Non-public commercial rates, campaign plans, customer information, business information and other confidential information obtained through the relationship must not be improperly disclosed.

An individually negotiated rate is treated as confidential commercial information unless the parties agree otherwise.

## 14. Personal data and customer protection
The Premium Affiliate must not collect customer PINs, passwords or security credentials.

Personal information accessed through an authorized activity may be used only for the permitted purpose and must be appropriately protected.

## 15. Integrity and prohibited practices
The Premium Affiliate must not create artificial referrals or transactions, manipulate commission attribution, submit false customer information, charge unauthorized customer fees, make misleading financial claims, misuse confidential/customer information, or offer or accept improper inducements connected with the relationship.

## 16. Changes to commercial terms
Individually negotiated commercial terms may only be changed through Kopafasta's authorized Change Commercial Terms process.

A change records previous terms, new terms, effective date, reason and authorized actor.

New terms apply prospectively from their effective date. Previously earned commissions remain governed by the commercial terms applicable when they were earned.

## 17. Duration
The Agreement begins on {{effective_date}}.

**Term:** {{agreement_term}}

## 18. Special / negotiated terms
{{negotiated_terms}}

## 19. Suspension and termination
Either party may terminate in accordance with the agreed notice provisions.

Kopafasta may immediately suspend activity where reasonably necessary to investigate suspected fraud, misuse of customer information, serious misleading representations, regulatory concerns or another material breach.

Termination does not automatically cancel legitimate commissions earned before the effective termination date, subject to reconciliation and investigation.

## 20. Electronic execution
The parties may execute and accept this Agreement electronically through Kopafasta.

Kopafasta may retain an audit record containing the agreement version, effective commercial terms, date/time of acceptance and other relevant evidence of execution.

## 21. Governing law and disputes
This Agreement is governed by the laws of the United Republic of Tanzania applicable to the relationship.

The parties will first attempt good-faith resolution through the agreed Kopafasta dispute-resolution channel before exercising other remedies available under applicable law.

## 22. Entire agreement
This Agreement, its captured Commercial Terms and any expressly incorporated schedules constitute the agreement between the parties concerning this Premium Affiliate relationship.

## Commercial Terms Schedule
**Premium Affiliate:** {{affiliate_name}}
**Affiliate number:** {{affiliate_number}}
**Effective from:** {{commercial_terms_effective_date}}
**Rate source:** {{rate_source}}

{{commission_table}}

{{benefits_table}}

**Exclusivity:** {{exclusivity_terms}}
**Agreement term:** {{agreement_term}}
**Other negotiated terms:** {{negotiated_terms}}

**For {{brand}}**
Name: {{authorized_signatory}}
Title: {{authorized_signatory_title}}
Signature: {{company_signature}}

**Premium Affiliate**
Name: {{affiliate_name}}
Acceptance/signature: {{affiliate_signature_or_acceptance}}

**Executed:** {{executed_at}}
**Agreement version:** {{agreement_version}}
TEXT,
];
