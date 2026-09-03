<?php

return [
    'yes' => 'Yes',
    'no' => 'No',
    'sla_starts_assignment' => 'The completion SLA starts when the job or case is assigned, not when it is accepted.',
    'accept' => 'I have read and accept these Terms',
    'accept_button' => 'Accept Terms',
    'required' => 'Accept the current Terms before receiving work.',
    'already_accepted' => 'You have already accepted the Terms that applied at the time.',
    'types' => [
        'valuer' => 'Valuer',
        'gps_installer' => 'GPS installer',
        'insurance' => 'Insurance partner',
        'call_center' => 'Call centre partner',
        'debt_collector' => 'Debt collector',
        'auctioneer' => 'Auctioneer',
        'legal_partner' => 'Legal partner',
    ],
    'valuer' => [
        'title' => 'Valuer Terms',
        'body' => <<<'TEXT'
# {{brand}} Valuer Terms

These Terms govern independent valuation work for {{brand}}. They are not a contract of employment.

## 1. Independent appointment
You act as an independent valuer. You must not present yourself as an employee of {{brand}} or bind {{brand}} without written authority.

## 2. Assignment and SLA
Jobs are assigned according to Origination auto-assignment Settings. The completion SLA is {{sla_days}} days ({{sla_hours}} hours). {{sla_starts}} Reminders are sent at {{remind_hours}} hours remaining. After the deadline, a grace of {{grace_hours}} hours may apply before peer reassignment (maximum {{max_reassignments}} automatic reassignments). A cancelled task is no longer writable.

## 3. Inspection, evidence and accuracy
Complete the inspection, required photographs, and valuation figures honestly and in full. Do not invent values or omit material defects.

## 4. Conduct, confidentiality and data
Treat customers professionally. Keep borrower and asset data confidential and use it only for the assigned job.

## 5. Fees, membership and earnings
Where Settings require membership for valuers, annual membership applies (individual {{membership_fee_individual}}, company {{membership_fee_company}}). Membership required: {{membership_required}}. Completed jobs settle to your partner wallet under the existing ledger. Historical payments are preserved if eligibility later changes.

## 6. Performance, warnings and suspension
Performance is calculated from operational records (assigned, accepted, completed, on-time vs SLA, breaches, reassignments). On-time target: {{target_on_time}}%. Completion target: {{target_completion}}%. A score is assigned after {{min_jobs_for_score}} closed jobs. Repeated at-risk reviews ({{warnings_before_suspend}}) may result in automatic performance suspension. Automatic recovery after performance improves: {{auto_recover}}. Compliance, fraud, safety, or administrative restrictions are separate and are not undone by KPI recovery.

## 7. Termination and disputes
{{brand}} may stop assigning work or terminate the relationship for material breach. Disputes are handled under the laws of Tanzania. Policy version {{policy_version}}. Agreement version {{agreement_version}}. Conduct version {{conduct_version}}.

## 8. Changes
SLA, reminder, grace, membership and performance numbers come from Settings. Material changes may require re-acceptance according to Settings. The version you accept is snapshotted and is not rewritten when Settings later change.
TEXT,
    ],
    'gps_installer' => [
        'title' => 'GPS Installer Terms',
        'body' => <<<'TEXT'
# {{brand}} GPS Installer Terms

These Terms cover origination installation work and, where assigned, recovery GPS locate/remove work. Those are different SLAs.

## 1. Independent contractor
You are an independent installer, not an employee of {{brand}}.

## 2. Origination installation SLA
Installation jobs use Origination auto-assignment. Completion SLA: {{sla_days}} days ({{sla_hours}} hours). {{sla_starts}} Reminders: {{remind_hours}} hours remaining. Grace: {{grace_hours}} hours. Maximum peer reassignments: {{max_reassignments}}.

## 3. Recovery GPS work
Recovery GPS assignments use Recovery Policy, not origination hours. Recovery SLA: {{recovery_sla_days}} days. Automated reminders: {{recovery_remind_days}} day(s) before the SLA date. On expiry, the case may escalate to the next recovery stage rather than another installer of the same type.

## 4. Installation, evidence and equipment
Install or remove devices as instructed, capture required evidence, and handle customer property and equipment with care. Do not reuse or withhold {{brand}} or customer hardware.

## 5. Conduct, confidentiality, completion and earnings
Keep location and customer data confidential. Completed origination and recovery work settle to the existing partner wallet. Membership required: {{membership_required}}.

## 6. Performance and suspension
On-time target {{target_on_time}}%. Completion target {{target_completion}}%. Warnings before performance suspension: {{warnings_before_suspend}}. Automatic recovery: {{auto_recover}}. Policy version {{policy_version}}. Agreement version {{agreement_version}}.
TEXT,
    ],
    'insurance' => [
        'title' => 'Insurance Partner Terms',
        'body' => <<<'TEXT'
# {{brand}} Insurance Partner Terms

These Terms govern assigned insurance cover work for {{brand}} borrowers.

## 1. Assigned work
Cover jobs are assigned under Origination auto-assignment. Completion SLA: {{sla_days}} days ({{sla_hours}} hours). {{sla_starts}} Reminders: {{remind_hours}} hours. Grace: {{grace_hours}} hours. Maximum peer reassignments: {{max_reassignments}}.

## 2. Documentation and service standards
Issue cover, record policy details, and supply documents through the portal. Do not backdate or misstate cover.

## 3. Payments and governance
Premiums and partner amounts settle through the existing wallet. Membership required: {{membership_required}}. Performance uses the same Partner Performance Settings (on-time target {{target_on_time}}%, completion {{target_completion}}%). Automatic recovery: {{auto_recover}}. Policy version {{policy_version}}. Agreement version {{agreement_version}}.
TEXT,
    ],
    'call_center' => [
        'title' => 'Call Centre Partner Terms',
        'body' => <<<'TEXT'
# {{brand}} Call Centre Partner Terms

These Terms govern recovery call-centre cases. They are not a contract of employment.

## 1. Case assignment and SLA
Cases are assigned under Recovery Policy. Completion SLA: {{sla_days}} days. {{sla_starts}} Automated reminders: {{recovery_remind_days}} day(s) before the SLA date. On expiry, the case may escalate to the next recovery stage (not another call centre by default).

## 2. Conduct
Use only authorised portal actions. Do not threaten, shame, or contact unrelated third parties to coerce payment.

## 3. Reporting, performance and earnings
Log actions in the portal. Performance uses assigned, actioned, completed, SLA and escalation records. On-time target {{target_on_time}}%. Completion target {{target_completion}}%. Warnings before performance suspension: {{warnings_before_suspend}}. Automatic recovery: {{auto_recover}}. Commissions settle to the existing wallet. Membership required: {{membership_required}}. Policy version {{policy_version}}. Agreement version {{agreement_version}}. Conduct version {{conduct_version}}.
TEXT,
    ],
    'debt_collector' => [
        'title' => 'Debt Collector Terms',
        'body' => <<<'TEXT'
# {{brand}} Debt Collector Terms

These Terms govern field collection and repossession cases assigned by {{brand}}.

## 1. Case assignment and SLA
SLA: {{sla_days}} days from assignment. {{sla_starts}} Reminders: {{recovery_remind_days}} day(s) before expiry. Default outcome of SLA expiry is escalation to the next recovery stage, not peer reassignment.

## 2. Lawful collection and repossession
Follow authorised portal actions and applicable Tanzanian law. No violence, public shaming, unlawful entry, or harvesting contacts from a borrower device.

## 3. Performance, suspension, earnings
KPIs come from recovery operational records. On-time target {{target_on_time}}%. Completion {{target_completion}}%. Warnings before performance suspension: {{warnings_before_suspend}}. Automatic recovery: {{auto_recover}}. Membership required: {{membership_required}}. Policy version {{policy_version}}. Agreement version {{agreement_version}}. Conduct version {{conduct_version}}.
TEXT,
    ],
    'auctioneer' => [
        'title' => 'Auctioneer Terms',
        'body' => <<<'TEXT'
# {{brand}} Auctioneer Terms

These Terms govern auction of repossessed assets after the configured hold period.

## 1. Assignment and SLA
SLA: {{sla_days}} days from assignment. {{sla_starts}} Reminders: {{recovery_remind_days}} day(s) before expiry. SLA expiry may escalate to the next recovery stage.

## 2. Sale standards
Run the auction fairly, document bids and proceeds, and do not deal in the asset for personal account without disclosure.

## 3. Performance and earnings
On-time target {{target_on_time}}%. Completion {{target_completion}}%. Warnings before performance suspension: {{warnings_before_suspend}}. Automatic recovery: {{auto_recover}}. Membership required: {{membership_required}}. Policy version {{policy_version}}. Agreement version {{agreement_version}}.
TEXT,
    ],
    'legal_partner' => [
        'title' => 'Legal Partner Terms',
        'body' => <<<'TEXT'
# {{brand}} Legal Partner Terms

These Terms govern legal recovery instructions assigned by {{brand}}.

## 1. Instructions and SLA
SLA: {{sla_days}} days from assignment. {{sla_starts}} Reminders: {{recovery_remind_days}} day(s) before expiry. You remain an independent legal practitioner.

## 2. Professional duties
Act within your professional rules, keep client matters confidential, and report progress through the portal.

## 3. Performance, fees and governance
On-time target {{target_on_time}}%. Completion {{target_completion}}%. Warnings before performance suspension: {{warnings_before_suspend}}. Automatic recovery: {{auto_recover}}. Membership required: {{membership_required}}. Fees follow Recovery Policy. Policy version {{policy_version}}. Agreement version {{agreement_version}}.
TEXT,
    ],
];
