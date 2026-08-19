<?php

/**
 * Page-level admin settings help (slide-out drawer).
 * Keys match settings_nav tab keys (3rd item) and any explicit overrides.
 *
 * @return array<string, array{
 *   title: string,
 *   summary: string,
 *   where?: string,
 *   affects?: list<string>,
 *   how_to?: list<string>,
 *   terms?: array<string, string>
 * }>
 */
return [
    'hub' => [
        'title' => 'Settings hub',
        'summary' => 'Central index of every platform setting. Use search on this page only, then open a settings screen and use Page guide (right side) for that page or sub-tab.',
        'where' => 'Admin → Settings hub.',
        'affects' => [
            'Nothing by itself — it only helps you find the page that stores the live value.',
        ],
        'how_to' => [
            'Use Find a setting… to jump to a page.',
            'Or open a group card below.',
            'On the destination page, click Page guide on the right before changing values.',
            'If the page has sub-tabs (e.g. Recovery policy), each tab has its own guide.',
        ],
        'terms' => [
            'Page guide' => 'Slide-out help for the current page or sub-tab: where it shows, what it affects, how to set it, and definitions of terms.',
        ],
    ],
    'recovery' => [
        'title' => 'Recovery policy',
        'summary' => 'Open a numbered sub-tab (Timeline, Recovery partners, Repossession, Service rates, Auto-assign) and use that tab’s Page guide.',
        'where' => 'Admin → Settings → Recovery policy.',
        'how_to' => [
            'Switch to the sub-tab you need.',
            'Click Page guide on the right of the sub-tab row.',
        ],
    ],
    'recovery.timeline' => [
        'title' => 'Timeline & automation',
        'summary' => 'Sets when grace ends, when call-center outreach can start, auction hold after repossession, and GPS map-link visibility.',
        'where' => 'Arrears / recovery timeline, auction scheduling, and GPS “View Asset Location” links in admin and partner consoles.',
        'affects' => [
            'Borrowers: how long they have after repossession before auction can start.',
            'Call-center partners: when leads can be auto-created relative to grace.',
            'Ops: whether GPS map links appear for tracked assets.',
        ],
        'how_to' => [
            'Set fallback grace only for loans without a product-level grace.',
            'Set call-center lead days (0 = at grace end).',
            'Set auction hold days after repossession is marked complete.',
            'Toggle GPS map links if partners/admins should open live location URLs.',
            'Save recovery policy (stores all tabs).',
        ],
        'terms' => [
            'Fallback grace' => 'Used only when the loan product has no grace days of its own.',
            'Call center lead days' => 'How many days before grace ends a call-center task may be created.',
            'Auction hold' => 'Borrower settlement window after repossession before an auctioneer is auto-assigned.',
        ],
    ],
    'recovery.partners' => [
        'title' => 'Recovery partners',
        'summary' => 'Per recovery partner type: SLA, commission, which loans/collateral they handle, and escalate order.',
        'where' => 'Recovery case assignment, partner portal tasks, and recovery fee / commission calculations.',
        'affects' => [
            'Which partner types are in the recovery chain and in what order.',
            'SLA clocks and partner payout math for recovery work.',
        ],
        'how_to' => [
            'Open each partner type card.',
            'Set SLA days, commission, and scope (loan / collateral rules).',
            'Order escalate steps so the next type takes over when needed.',
            'Save recovery policy.',
        ],
        'terms' => [
            'SLA days' => 'Working days the partner has to act before the case is considered late.',
            'Escalate order' => 'Sequence of partner types when a case moves up the recovery chain.',
        ],
    ],
    'recovery.repossession' => [
        'title' => 'Repossession charges',
        'summary' => 'Partner cost and platform markup for repossession by asset type.',
        'where' => 'Recovery billing when repossession is completed; borrower/partner fee lines related to repossession.',
        'affects' => [
            'What the partner is paid and what the platform charges/markups for repossession.',
        ],
        'how_to' => [
            'For each asset type, set partner cost and markup.',
            'Save recovery policy.',
        ],
        'terms' => [
            'Partner cost' => 'Amount owed to the repossession partner.',
            'Markup' => 'Platform add-on on top of partner cost.',
        ],
    ],
    'recovery.service' => [
        'title' => 'Service rates',
        'summary' => 'Default pricing for insurance, GPS, and valuer (and other service partner categories).',
        'where' => 'Origination / screening service tasks and partner fee defaults when those services are ordered.',
        'affects' => [
            'Default amounts charged or paid for valuation, GPS install, insurance, etc.',
        ],
        'how_to' => [
            'Review each service category rate.',
            'Valuation base price is per pledged asset (GPS install is per device). Two assets = 2 × that amount; application fee stays 1×.',
            'Align amounts with your commercial agreements.',
            'Save recovery policy.',
        ],
    ],
    'recovery.auto_assign' => [
        'title' => 'Auto-assign & KPIs',
        'summary' => 'Rules for automatically choosing which active partner gets a recovery or service task.',
        'where' => 'Auto-assignment of call-center / recovery / valuer / GPS / insurance work; screening partner availability; partner portal task inbox.',
        'affects' => [
            'Which partner receives new work when auto-assign is on.',
            'Whether overloaded or out-of-region partners are skipped.',
            'SLA reassignment for overdue service tasks.',
        ],
        'how_to' => [
            'Open the partner-type card you want to change.',
            'Click Edit on Auto-assign rules.',
            'Adjust strategy, max open roster, cold-start %, weights, and region/SLA options.',
            'Click Cancel edit or leave Edit mode, then Save recovery policy.',
            'Manage who is eligible under Partners (not on this list).',
        ],
        'terms' => [
            'Max open roster' => 'Maximum open tasks a partner can hold at once. Leave blank for no limit.',
            'Cold-start efficiency %' => 'Assumed efficiency for new partners with little history until real KPIs exist.',
            'Strategy' => 'How the system picks among eligible partners: least open roster, efficiency + load balanced, or round robin.',
            'Weight · load / efficiency / fairness' => 'Relative importance when strategy is efficiency-balanced.',
            'Require region match' => 'Only partners covering the borrower region (or nationwide) are eligible.',
            'Reassign if SLA missed' => 'Move overdue service tasks to another eligible partner when available.',
            'Task SLA / lead days' => 'Days the service partner has to finish after assignment.',
        ],
    ],
    'partners' => [
        'title' => 'Partner membership',
        'summary' => 'Membership fees and renewal rules for partner categories that must pay to stay active on the platform.',
        'where' => 'Partner enrollment, membership payment queue, and partner portal membership status.',
        'affects' => [
            'Whether a partner must pay membership and how long membership lasts.',
            'Reminder timing before membership expires.',
        ],
        'how_to' => [
            'Enable membership tracking if partners must pay.',
            'Set duration, grace, and notify days.',
            'Choose which partner categories pay and their fee amounts.',
        ],
        'terms' => [
            'Grace days' => 'Extra days after expiry before the partner is treated as lapsed.',
            'Notify days' => 'How many days before expiry to remind the partner.',
        ],
    ],
    'company' => [
        'title' => 'Company profile',
        'summary' => 'Brand and legal identity used across contracts, receipts, emails, and the public site.',
        'where' => 'Contracts, PDFs, emails, borrower/partner portals, and console branding.',
        'affects' => [
            'Company name, address, logo, and contact details shown to customers and partners.',
        ],
        'how_to' => [
            'Update legal name and trading name carefully — they appear on agreements.',
            'Upload logo assets used in headers and documents.',
            'Save, then spot-check a contract preview and the public site.',
        ],
    ],
    'underwriting' => [
        'title' => 'Underwriting',
        'summary' => 'Credit decision rules, capacity checks, and underwriting behaviour for loan applications.',
        'where' => 'Screening desk, recommendations, capacity auto-reject, and offer generation.',
        'affects' => [
            'How applications are scored, blocked, or advanced.',
            'What analysts see on the review workspace.',
        ],
        'how_to' => [
            'Adjust only one rule family at a time and test with a sample application.',
            'Document why a threshold changed for auditability.',
        ],
    ],
    'loan-rules' => [
        'title' => 'Loan rules',
        'summary' => 'Global lending constraints such as tenure, amounts, and product-level defaults that applications must respect.',
        'where' => 'Apply wizard, product offers, and admin loan creation.',
        'affects' => [
            'What borrowers can request and what officers can approve.',
        ],
    ],
    'offer' => [
        'title' => 'Offer settings',
        'summary' => 'How loan offers are presented, expire, and can be reissued.',
        'where' => 'Borrower offer page and admin offer actions.',
        'affects' => [
            'Offer validity windows and borrower CTAs.',
        ],
    ],
    'credit-policy' => [
        'title' => 'Credit policy',
        'summary' => 'Policy statements and rejection reason catalogues used in credit decisions.',
        'where' => 'Recommendation / reject flows and borrower feedback.',
    ],
    'asset-lending' => [
        'title' => 'Asset lending',
        'summary' => 'Rules for marketplace / asset-backed lending flows.',
        'where' => 'Asset lending applications, reservations, and collateral steps.',
    ],
    'kyc' => [
        'title' => 'KYC rules',
        'summary' => 'Know-your-customer document and verification requirements.',
        'where' => 'Borrower profile, screening documents, and onboarding gates.',
    ],
    'identity' => [
        'title' => 'Identity verification',
        'summary' => 'NIDA / face / identity provider behaviour.',
        'where' => 'Borrower identity steps and admin face verification queues.',
    ],
    'aml' => [
        'title' => 'AML thresholds',
        'summary' => 'Anti-money-laundering thresholds that flag suspicious activity.',
        'where' => 'Compliance queues and payment monitoring.',
    ],
    'finance' => [
        'title' => 'Finance defaults',
        'summary' => 'Accounting and capital allocation defaults for funding and journals.',
        'where' => 'Disbursements, journals, and capital partner allocation.',
    ],
    'payment-accounts' => [
        'title' => 'Payment accounts',
        'summary' => 'Where borrower payments and payouts are expected to land.',
        'where' => 'Payment instructions and reconciliation.',
    ],
    'membership' => [
        'title' => 'Membership',
        'summary' => 'Borrower membership fees, renewals, and access gates.',
        'where' => 'Borrower membership payment and renewals admin queue.',
    ],
    'referrals' => [
        'title' => 'Referrals',
        'summary' => 'Referral rewards and eligibility rules.',
        'where' => 'Borrower referral UI and reward processing.',
    ],
    'affiliates' => [
        'title' => 'Affiliates',
        'summary' => 'Affiliate partner commissions and lifecycle rules.',
        'where' => 'Affiliate portal and admin affiliate reviews.',
    ],
    'messaging' => [
        'title' => 'Transactional messaging',
        'summary' => 'Which SMS/email/WhatsApp notices are enabled for system events.',
        'where' => 'Borrower and partner notifications across the product.',
    ],
    'group-notifications' => [
        'title' => 'Group notifications',
        'summary' => 'Enable or disable group-loan SMS and in-app notices for leaders and members (invites, screening feedback, contract signatures).',
        'where' => 'Group loan apply flow, member onboarding, underwriting feedback, and contract signing.',
    ],
    'gateways' => [
        'title' => 'SMS / Email gateways',
        'summary' => 'Provider credentials and connection health for outbound messaging.',
        'where' => 'All SMS and email sends from the platform.',
    ],
    'chatbot' => [
        'title' => 'Chatbot',
        'summary' => 'FAQ content and chatbot replies on the public/borrower experience.',
        'where' => 'Site chatbot widget.',
    ],
    'legal' => [
        'title' => 'Contracts & clauses',
        'summary' => 'Legal clause libraries used when generating agreements.',
        'where' => 'Loan agreements and contract PDFs.',
    ],
    'working-hours' => [
        'title' => 'Working hours',
        'summary' => 'Office hours and public holidays used for SLA day counting.',
        'where' => 'SLA due dates and operational calendars.',
    ],
    'auth-portal' => [
        'title' => 'Authentication',
        'summary' => 'Login, PIN, recovery, and session behaviour for portals.',
        'where' => 'Borrower, partner, and staff sign-in flows.',
    ],
    'account-security' => [
        'title' => 'Account security',
        'summary' => 'Two-factor authentication for your own staff account.',
        'where' => 'Your next admin sign-in challenge.',
    ],
    'locations' => [
        'title' => 'Locations',
        'summary' => 'Region / district / ward master data used in profiles and partner coverage.',
        'where' => 'Borrower residence, partner regions, and region-matched auto-assign.',
    ],
    'countries' => [
        'title' => 'Countries',
        'summary' => 'Supported countries and related locale defaults.',
    ],
    'integrations' => [
        'title' => 'Integrations hub',
        'summary' => 'Third-party integration health and primary partners (payments, KYC, etc.).',
        'where' => 'PayIn, identity, and other external API calls.',
    ],
    'integrations.partner.configuration' => [
        'title' => 'Integration configuration',
        'summary' => 'Credentials, endpoints, and behaviour for this integration partner.',
        'where' => 'Live API calls for this partner (payments, KYC, messaging, CRB, etc.).',
        'affects' => [
            'Whether the integration can authenticate and complete real requests.',
        ],
        'how_to' => [
            'Fill required credentials carefully.',
            'Use Check health / Live test when available.',
            'Save, then run a small real-world action to confirm.',
        ],
    ],
    'integrations.partner.usage' => [
        'title' => 'Integration usage & billing',
        'summary' => 'Usage metering and cost rates you pay this provider.',
        'where' => 'Internal cost tracking / billing views for this integration.',
        'how_to' => [
            'Set per-message or per-call rates if the provider charges you.',
            'Save and reconcile against provider invoices.',
        ],
    ],
    'payin' => [
        'title' => 'PayIn',
        'summary' => 'Payment collection provider settings.',
        'where' => 'Borrower fee and repayment PayIn flows.',
    ],
    'crb' => [
        'title' => 'CRB',
        'summary' => 'Credit reference bureau integration and reporting behaviour.',
        'where' => 'Screening CRB checks and bureau submissions.',
    ],
    'engagement' => [
        'title' => 'Engagement',
        'summary' => 'Loyalty, streaks, milestones, and borrower engagement mechanics. Open a sub-page and use its Page guide.',
        'where' => 'Borrower engagement widgets and rewards.',
    ],
    'engagement.hub' => [
        'title' => 'Engagement hub',
        'summary' => 'Overview of engagement modules. Open a sub-page (referral levels, trust score, streaks, etc.) for the controls.',
        'where' => 'Admin → Settings → Engagement.',
        'how_to' => [
            'Pick a sub-page from the row under the settings tabs.',
            'Use Page guide on that sub-page before editing.',
        ],
    ],
    'engagement.referral-levels' => [
        'title' => 'Referral levels',
        'summary' => 'Tiered referral rewards and thresholds.',
        'where' => 'Borrower referral progress and reward payouts.',
    ],
    'engagement.trust-score' => [
        'title' => 'Trust score',
        'summary' => 'How borrower trust score is calculated and displayed.',
        'where' => 'Borrower profile / engagement trust widgets.',
    ],
    'engagement.milestones' => [
        'title' => 'Community milestones',
        'summary' => 'Community milestone definitions and rewards.',
        'where' => 'Borrower community milestone UI.',
    ],
    'engagement.repayment-streak' => [
        'title' => 'Repayment streak',
        'summary' => 'Rules for repayment streaks and related rewards.',
        'where' => 'Borrower streak widgets after successful repayments.',
    ],
    'engagement.profile-strength' => [
        'title' => 'Profile strength',
        'summary' => 'What completes a strong borrower profile and any related boosts.',
        'where' => 'Borrower profile completeness UI.',
    ],
    'engagement.loyalty-points' => [
        'title' => 'Loyalty points',
        'summary' => 'Earning and redeeming loyalty points.',
        'where' => 'Borrower loyalty balance and redemptions.',
    ],
    'engagement.underwriting' => [
        'title' => 'Underwriting boosts',
        'summary' => 'Engagement-based boosts that can influence underwriting.',
        'where' => 'Screening / underwriting when engagement boosts apply.',
    ],
    'engagement.notifications' => [
        'title' => 'Engagement notifications',
        'summary' => 'Which engagement events notify the borrower.',
        'where' => 'Borrower SMS/email/in-app engagement notices.',
    ],
    'engagement.profile-sections' => [
        'title' => 'Profile builder',
        'summary' => 'Sections shown in the borrower profile builder.',
        'where' => 'Borrower profile completion flow.',
    ],
    'signatories' => [
        'title' => 'Signatories',
        'summary' => 'Company signatories used on generated contracts.',
        'where' => 'Agreement signature blocks.',
    ],
];
