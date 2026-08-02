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

    public const CHANNELS = [
        'sms' => 'SMS',
        'email' => 'Email',
        'in_app' => 'In-app',
        'whatsapp' => 'WhatsApp (API-ready stub)',
        'push' => 'Push (future)',
    ];

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
