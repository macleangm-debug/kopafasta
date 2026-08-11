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
        'summary' => 'Central index of every platform setting. Open a page, then use Page guide on that page for how-to and term meanings.',
        'where' => 'Admin → Settings. Every settings screen also has a Page guide button next to search.',
        'affects' => [
            'Nothing by itself — it only navigates you to the page that stores the live value.',
        ],
        'how_to' => [
            'Pick the area (Lending, Partners & recovery, Finance, etc.).',
            'Open the page you need.',
            'Click Page guide on that page before changing values.',
            'Save on the page, then verify the related live screen.',
        ],
        'terms' => [
            'Page guide' => 'Slide-out help for the current settings page: where it shows, what it affects, how to set it, and definitions of terms.',
        ],
    ],
    'recovery' => [
        'title' => 'Recovery policy',
        'summary' => 'Controls when recovery starts, which partner types get work, fees/SLAs, repossession charges, service partner pricing, and automatic partner assignment.',
        'where' => 'Used on arrears / collection cases, auction holds, valuation / GPS / insurance handoffs, and partner portals.',
        'affects' => [
            'Borrowers: recovery timeline, fees, and auction hold messaging.',
            'Partners: who receives tasks, SLA due dates, and portal notifications.',
            'Screening / ops: partner availability by region and auto-assign outcomes.',
        ],
        'how_to' => [
            'Timeline — set grace fallback, call-center lead days, and auction hold.',
            'Recovery partners — set SLA, commission, loan/collateral scope, and escalate order.',
            'Repossession — partner cost + markup by asset type.',
            'Service rates — default pricing for insurance, GPS, and valuers.',
            'Auto-assign & KPIs — click Edit on a partner type, change rules, then Save recovery policy.',
        ],
        'terms' => [
            'Max open roster' => 'Maximum open tasks a partner can hold at once. Leave blank for no limit. Stops overloaded partners from receiving new auto-assignments.',
            'Cold-start efficiency %' => 'Assumed efficiency for partners with little or no history (new partners). Used only until real KPI data exists. Example: 50 means “treat as average.”',
            'Strategy' => 'How the system picks among eligible partners: least open roster, efficiency + load balanced, or round robin.',
            'Weight · load / efficiency / fairness' => 'Relative importance when strategy is efficiency-balanced. Higher load weight prefers quieter rosters; efficiency prefers better recovery/completion rates; fairness spreads work over time.',
            'Require region match' => 'Only partners covering the borrower region (or nationwide) are eligible. Soft mode can fall back if nobody matches.',
            'Reassign if SLA missed' => 'If a service task is overdue and another eligible partner exists, move the job automatically.',
            'Task SLA / lead days' => 'Days the partner has to finish after assignment (valuer, GPS, insurance).',
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
        'summary' => 'Loyalty, streaks, milestones, and borrower engagement mechanics.',
        'where' => 'Borrower engagement widgets and rewards.',
    ],
    'signatories' => [
        'title' => 'Signatories',
        'summary' => 'Company signatories used on generated contracts.',
        'where' => 'Agreement signature blocks.',
    ],
];
