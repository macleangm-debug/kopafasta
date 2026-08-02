<?php

namespace App\Services\Messaging;

/**
 * Canonical MFI transactional messaging catalog.
 * Settings UI and send gates resolve against this list.
 */
class MessagingCatalog
{
    public const GROUPS = [
        'auth' => 'Auth & security (OTP)',
        'origination' => 'Loan origination',
        'servicing' => 'Repayments & servicing',
        'collections' => 'Collections & penalties',
        'membership' => 'Membership & retention',
        'group' => 'Group lending',
        'marketplace' => 'Marketplace',
        'staff' => 'Staff alerts',
    ];

    /**
     * Human lifecycle stages for browsing / editing notification templates.
     * Finer than GROUPS so staff pick “when this sends” easily.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const LIFECYCLES = [
        'registration' => [
            'label' => 'Registration & security',
            'hint' => 'PIN reset, partner activation, account security',
        ],
        'membership' => [
            'label' => 'Membership',
            'hint' => 'Issued, renewals, expiry reminders',
        ],
        'application' => [
            'label' => 'Application & underwriting',
            'hint' => 'Document requests, approvals, rejections',
        ],
        'borrowing' => [
            'label' => 'Agreement & disbursement',
            'hint' => 'Signing OTP, signed confirmation, funds released',
        ],
        'repayment' => [
            'label' => 'Repayments',
            'hint' => 'Due soon, due today, payment received, loan closed',
        ],
        'late_payment' => [
            'label' => 'Late payments & collections',
            'hint' => 'Overdue, penalties, arrears escalation',
        ],
        'group' => [
            'label' => 'Group lending',
            'hint' => 'Consent, group contract signatures',
        ],
        'marketplace' => [
            'label' => 'Marketplace',
            'hint' => 'Viewing appointments and asset messages',
        ],
        'staff' => [
            'label' => 'Staff alerts',
            'hint' => 'Internal SMS/email for officers',
        ],
        'other' => [
            'label' => 'Other / custom',
            'hint' => 'Templates not mapped to a standard event',
        ],
    ];

    public const CHANNELS = [
        'sms' => 'SMS',
        'email' => 'Email',
        'in_app' => 'In-app',
        'whatsapp' => 'WhatsApp (API-ready stub)',
        'push' => 'Push (future)',
    ];

    /** Lifecycle stage key for a template / event code. */
    public static function lifecycleForCode(string $code): string
    {
        return match ($code) {
            'pin_reset_otp', 'partner_activation' => 'registration',
            'membership_issued', 'membership_expiry_30', 'membership_expiry_14',
            'membership_expiry_7', 'membership_expiry_1', 'membership_renewed' => 'membership',
            'application_document_request', 'application_approved', 'application_rejected',
            'group_member_replacement_requested', 'group_member_review_feedback',
            'group_application_review_feedback' => 'application',
            'agreement_otp', 'agreement_signed', 'loan_disbursed',
            'group_contract_sign_required', 'group_contract_member_signed',
            'group_contract_member_declined', 'group_member_consent_required' => 'borrowing',
            'repayment_due_soon', 'repayment_due_today', 'payment_received', 'loan_closed' => 'repayment',
            'repayment_overdue', 'penalty_accrued', 'loan_arrears' => 'late_payment',
            'marketplace_viewing_scheduled' => 'marketplace',
            'staff_restructure_request', 'staff_top_up_request' => 'staff',
            default => str_starts_with($code, 'group_') ? 'group' : 'other',
        };
    }

    /** @return array{key: string, label: string, hint: string} */
    public static function lifecycleMeta(string $code): array
    {
        $key = self::lifecycleForCode($code);
        $meta = self::LIFECYCLES[$key] ?? self::LIFECYCLES['other'];

        return [
            'key' => $key,
            'label' => $meta['label'],
            'hint' => $meta['hint'],
        ];
    }

    /**
     * Events for a lifecycle, for optgroup pickers.
     *
     * @return list<array{code: string, name: string, description: string}>
     */
    public static function eventsForLifecycle(string $lifecycle): array
    {
        $out = [];
        foreach (self::events() as $event) {
            if (self::lifecycleForCode($event['code']) === $lifecycle) {
                $out[] = [
                    'code' => $event['code'],
                    'name' => $event['name'],
                    'description' => $event['description'],
                ];
            }
        }

        return $out;
    }

    /**
     * Events grouped by lifecycle for template editors.
     *
     * @return array<string, list<array{code: string, name: string, description: string}>>
     */
    public static function eventsGroupedByLifecycle(): array
    {
        $grouped = [];
        foreach (array_keys(self::LIFECYCLES) as $key) {
            if ($key === 'other') {
                continue;
            }
            $events = self::eventsForLifecycle($key);
            if ($events !== []) {
                $grouped[$key] = $events;
            }
        }

        return $grouped;
    }

    /**
     * @return list<array{
     *   code: string,
     *   name: string,
     *   group: string,
     *   critical: bool,
     *   default_channels: list<string>,
     *   default_enabled: bool,
     *   description: string
     * }>
     */
    public static function events(): array
    {
        return [
            // Auth / OTP — never blocked by quiet hours
            [
                'code' => 'pin_reset_otp',
                'name' => 'PIN reset OTP',
                'group' => 'auth',
                'critical' => true,
                'default_channels' => ['sms'],
                'default_enabled' => true,
                'description' => 'One-time code when a borrower resets their PIN.',
            ],
            [
                'code' => 'agreement_otp',
                'name' => 'Agreement signing OTP',
                'group' => 'auth',
                'critical' => true,
                'default_channels' => ['sms'],
                'default_enabled' => true,
                'description' => 'OTP to sign a loan agreement / contract.',
            ],
            [
                'code' => 'partner_activation',
                'name' => 'Partner activation invite',
                'group' => 'auth',
                'critical' => true,
                'default_channels' => ['sms', 'email'],
                'default_enabled' => false,
                'description' => 'Optional SMS/email when a partner is invited to activate.',
            ],

            // Origination
            [
                'code' => 'application_document_request',
                'name' => 'Document / photo retake request',
                'group' => 'origination',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Analyst asks borrower to upload or retake documents/photos.',
            ],
            [
                'code' => 'application_approved',
                'name' => 'Application approved',
                'group' => 'origination',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Loan application approved — review and sign offer.',
            ],
            [
                'code' => 'application_rejected',
                'name' => 'Application rejected',
                'group' => 'origination',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Application not approved, with reason.',
            ],
            [
                'code' => 'agreement_signed',
                'name' => 'Agreement signed',
                'group' => 'origination',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Confirmation after contract signing.',
            ],
            [
                'code' => 'loan_disbursed',
                'name' => 'Loan disbursed',
                'group' => 'origination',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Funds released — include amount and first due date.',
            ],

            // Servicing
            [
                'code' => 'repayment_due_soon',
                'name' => 'Repayment due soon',
                'group' => 'servicing',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Reminder N days before installment due (offsets configurable).',
            ],
            [
                'code' => 'repayment_due_today',
                'name' => 'Repayment due today',
                'group' => 'servicing',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Same-day installment reminder.',
            ],
            [
                'code' => 'payment_received',
                'name' => 'Payment received',
                'group' => 'servicing',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Receipt after repayment — amount paid and remaining balance.',
            ],
            [
                'code' => 'loan_closed',
                'name' => 'Loan closed / settled',
                'group' => 'servicing',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Loan fully repaid and closed.',
            ],

            // Collections
            [
                'code' => 'repayment_overdue',
                'name' => 'Repayment overdue',
                'group' => 'collections',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Early arrears reminder (e.g. 1 day overdue).',
            ],
            [
                'code' => 'penalty_accrued',
                'name' => 'Penalty / late fee charged',
                'group' => 'collections',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Notify when a late fee or penalty is applied.',
            ],
            [
                'code' => 'loan_arrears',
                'name' => 'Loan in arrears (escalation)',
                'group' => 'collections',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Stronger collections notice when arrears deepen.',
            ],

            // Membership
            [
                'code' => 'membership_issued',
                'name' => 'Membership issued',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Welcome / membership card issued.',
            ],
            [
                'code' => 'membership_expiry_30',
                'name' => 'Membership expiry — 30 days',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Renewal reminder 30 days out.',
            ],
            [
                'code' => 'membership_expiry_14',
                'name' => 'Membership expiry — 14 days',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Renewal reminder 14 days out.',
            ],
            [
                'code' => 'membership_expiry_7',
                'name' => 'Membership expiry — 7 days',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Urgent renewal reminder 7 days out.',
            ],
            [
                'code' => 'membership_expiry_1',
                'name' => 'Membership expiry — 1 day',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Final renewal reminder.',
            ],
            [
                'code' => 'membership_renewed',
                'name' => 'Membership renewed',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Confirmation after successful renewal payment.',
            ],

            // Group
            [
                'code' => 'group_member_consent_required',
                'name' => 'Group member consent required',
                'group' => 'group',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Invite a member to consent to a group loan.',
            ],
            [
                'code' => 'group_contract_sign_required',
                'name' => 'Group contract signature required',
                'group' => 'group',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Ask a member to sign the group contract.',
            ],

            // Marketplace
            [
                'code' => 'marketplace_viewing_scheduled',
                'name' => 'Marketplace viewing scheduled',
                'group' => 'marketplace',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Asset viewing appointment confirmation.',
            ],

            // Staff
            [
                'code' => 'staff_restructure_request',
                'name' => 'Staff — restructure request',
                'group' => 'staff',
                'critical' => false,
                'default_channels' => ['sms', 'email'],
                'default_enabled' => true,
                'description' => 'Alert officers when a borrower requests restructure (also gated by gateway staff SMS toggle).',
            ],
            [
                'code' => 'staff_top_up_request',
                'name' => 'Staff — top-up request',
                'group' => 'staff',
                'critical' => false,
                'default_channels' => ['sms', 'email'],
                'default_enabled' => true,
                'description' => 'Alert officers when a borrower requests a top-up.',
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function eventsByCode(): array
    {
        $out = [];
        foreach (self::events() as $event) {
            $out[$event['code']] = $event;
        }

        return $out;
    }
}
