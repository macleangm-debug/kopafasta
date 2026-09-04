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
        'partner_jobs' => 'Partner jobs & cover',
        'partner_payouts' => 'Partner payouts',
        'partner_account' => 'Partner account',
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
            'hint' => 'Invites, consent, screening feedback, contract signatures',
        ],
        'marketplace' => [
            'label' => 'Marketplace',
            'hint' => 'Viewing appointments and asset messages',
        ],
        'staff' => [
            'label' => 'Staff alerts',
            'hint' => 'Internal SMS/email for officers',
        ],
        'partners' => [
            'label' => 'Partner & insurance',
            'hint' => 'Cover jobs, partner jobs, payouts, activation',
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
            'membership_issued', 'membership_renewed', 'membership_expiry_30', 'membership_expiry_14',
            'membership_expiry_7', 'membership_expiry_1', 'referral_points_earned',
            'grade_upgraded', 'grade_under_review', 'grade_changed',
            'plus_started', 'plus_expiring', 'plus_expired', 'plus_renewed',
            'plus_monthly_lesson_published', 'plus_lesson_not_started', 'plus_lesson_unwatched', 'plus_lesson_completed',
            'plus_monthly_report_ready',
            'goal_near_target', 'goal_completed', 'goal_created', 'goal_progress', 'goal_no_progress',
            'business_weekly_summary', 'money_weekly_summary',
            'new_eligible_offer', 'reward_available', 'learning_continue' => 'membership',
            'repayment_due_soon', 'repayment_due_today', 'payment_received', 'loan_closed',
            'bank_payment_pending', 'bank_payment_verified' => 'repayment',
            'repayment_overdue', 'penalty_accrued', 'recovery_fee_accrued', 'loan_arrears', 'recovery_case_reminder',
            'collateral_repossessed', 'auction_window_started' => 'late_payment',
            'marketplace_viewing_scheduled' => 'marketplace',
            'staff_restructure_request', 'staff_top_up_request' => 'staff',
            'partner_welcome', 'partner_cover_job_assigned', 'partner_job_assigned', 'partner_cover_job_cancelled',
            'partner_payout_requested', 'partner_payout_paid', 'partner_payout_rejected',
            'partner_efficiency_warning', 'partner_efficiency_suspended', 'affiliate_volume_warning',
            'affiliate_account_active', 'affiliate_premium_active', 'affiliate_referral_new',
            'affiliate_referral_progressed', 'affiliate_impact_progressed', 'affiliate_commission_earned' => 'partners',
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
                'code' => 'application_document_request_reminder',
                'name' => 'Requested documents due tomorrow',
                'group' => 'origination',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Reminder one day before open document requests are due. Lists the requested items.',
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
                'critical' => true,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Funds released — include amount and first due date (BoT e-receipt).',
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
                'critical' => true,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Receipt after repayment — amount paid, remaining balance, and next installment.',
            ],
            [
                'code' => 'bank_payment_pending',
                'name' => 'Bank payment pending verification',
                'group' => 'servicing',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Borrower submitted a bank transfer — awaiting finance verification.',
            ],
            [
                'code' => 'bank_payment_verified',
                'name' => 'Bank payment verified',
                'group' => 'servicing',
                'critical' => true,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Finance verified a bank transfer payment.',
            ],
            [
                'code' => 'loan_closed',
                'name' => 'Loan closed / settled',
                'group' => 'servicing',
                'critical' => true,
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
                'code' => 'recovery_fee_accrued',
                'name' => 'Recovery partner fee charged',
                'group' => 'collections',
                'critical' => true,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Notify borrower (and escalate messaging) when a recovery partner fee is added to the loan.',
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
            [
                'code' => 'recovery_case_reminder',
                'name' => 'Recovery case payment reminder',
                'group' => 'collections',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'One-tap reminder from a recovery partner case. In-app only until SMS provider is integrated.',
            ],
            [
                'code' => 'collateral_repossessed',
                'name' => 'Collateral repossessed',
                'group' => 'collections',
                'critical' => true,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Borrower notice when collateral is repossessed and the auction hold countdown starts.',
            ],
            [
                'code' => 'auction_window_started',
                'name' => 'Auction window started',
                'group' => 'collections',
                'critical' => true,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Borrower notice when the post-repossession hold ends and auctioneer work begins.',
            ],

            // Membership
            [
                'code' => 'referral_points_earned',
                'name' => 'Referral points earned',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Referrer earns fixed points when an invited member pays membership.',
            ],
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
            [
                'code' => 'plus_started',
                'name' => 'Kopafasta Plus started',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Plus membership is active. Transactional — not optional.',
            ],
            [
                'code' => 'plus_expiring',
                'name' => 'Kopafasta Plus expiring',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Plus is about to expire.',
            ],
            [
                'code' => 'plus_expired',
                'name' => 'Kopafasta Plus expired',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Plus has expired.',
            ],
            [
                'code' => 'plus_renewed',
                'name' => 'Kopafasta Plus renewed',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Plus was renewed through payment.show.',
            ],
            [
                'code' => 'plus_monthly_report_ready',
                'name' => 'Plus monthly report ready',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Previous month’s progress report is ready. Deep link opens Plus → Reports for that month. Stops when viewed.',
            ],
            [
                'code' => 'plus_monthly_lesson_published',
                'name' => 'Plus monthly lesson published',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Monthly Club lesson is ready. Stops when the member finishes it.',
            ],
            [
                'code' => 'plus_lesson_not_started',
                'name' => 'Plus lesson not started',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Reminder if the monthly lesson is not started. Stops when started or completed.',
            ],
            [
                'code' => 'plus_lesson_unwatched',
                'name' => 'Plus lesson reminder',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'State-aware reminder. Never sends after the lesson is completed.',
            ],
            [
                'code' => 'plus_lesson_completed',
                'name' => 'Plus lesson completed',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Member finished the monthly Club lesson.',
            ],
            [
                'code' => 'money_daily_reminder',
                'name' => 'Plus money daily reminder',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => false,
                'description' => 'Optional nudge to record money. Stops once an entry exists today.',
            ],
            [
                'code' => 'business_no_activity',
                'name' => 'Plus business no activity',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => false,
                'description' => 'Optional. Stops when a sale or spend is recorded today.',
            ],
            [
                'code' => 'goal_near_target',
                'name' => 'Plus goal near target',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Goal is close. Stops when the goal is completed.',
            ],
            [
                'code' => 'goal_completed',
                'name' => 'Plus goal completed',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'A Plus goal was finished.',
            ],
            [
                'code' => 'goal_created',
                'name' => 'Plus goal created',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Sent once when a Plus goal is created.',
            ],
            [
                'code' => 'goal_progress',
                'name' => 'Plus goal progress',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Milestone at 25/50/75 percent. Once per milestone.',
            ],
            [
                'code' => 'goal_no_progress',
                'name' => 'Plus goal waiting',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Meaningful inactivity. Cooldown 14 days. Stops when progress resumes or the goal completes.',
            ],
            [
                'code' => 'business_weekly_summary',
                'name' => 'Plus weekly business summary',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Weekly sales activity. Once per week. Never invents praise.',
            ],
            [
                'code' => 'money_weekly_summary',
                'name' => 'Plus weekly money summary',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Weekly money check when actual spend is under the member plan.',
            ],
            [
                'code' => 'new_eligible_offer',
                'name' => 'New eligible Plus offer',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'A matching offer appeared. Do not fire for every library article.',
            ],
            [
                'code' => 'reward_available',
                'name' => 'Plus reward available',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => false,
                'description' => 'Member has enough points. Stops after redeem.',
            ],
            [
                'code' => 'learning_continue',
                'name' => 'Plus learning continue',
                'group' => 'membership',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => false,
                'description' => 'Continue a started subject. Stops when completed. Never fires for every new draft article.',
            ],

            // Group
            [
                'code' => 'group_member_consent_required',
                'name' => 'Member consent / invite required',
                'group' => 'group',
                'audience' => 'member',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Invite a member to join and complete membership consent for a group loan.',
            ],
            [
                'code' => 'group_contract_sign_required',
                'name' => 'Member contract signature required',
                'group' => 'group',
                'audience' => 'member',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Ask a member to sign the group loan contract after approval.',
            ],
            [
                'code' => 'group_member_review_feedback',
                'name' => 'Leader — member screening feedback',
                'group' => 'group',
                'audience' => 'leader',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Tell the leader when underwriting leaves feedback on a specific member.',
            ],
            [
                'code' => 'group_member_replacement_requested',
                'name' => 'Leader — member replacement requested',
                'group' => 'group',
                'audience' => 'leader',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Tell the leader a member must be replaced before the group can proceed.',
            ],
            [
                'code' => 'group_application_review_feedback',
                'name' => 'Leader — group application feedback',
                'group' => 'group',
                'audience' => 'leader',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Tell the leader when underwriting leaves group-level feedback.',
            ],
            [
                'code' => 'group_contract_member_signed',
                'name' => 'Leader — member signed contract',
                'group' => 'group',
                'audience' => 'leader',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Confirm to the leader that a member signed the group contract.',
            ],
            [
                'code' => 'group_contract_member_declined',
                'name' => 'Leader — member declined contract',
                'group' => 'group',
                'audience' => 'leader',
                'critical' => false,
                'default_channels' => ['sms', 'in_app'],
                'default_enabled' => true,
                'description' => 'Alert the leader that a member declined to sign the group contract.',
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

            // Partner jobs & cover
            [
                'code' => 'partner_cover_job_assigned',
                'name' => 'Partner cover job assigned',
                'group' => 'partner_jobs',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Notify an insurance partner when a new collateral cover job is ready to accept.',
            ],
            [
                'code' => 'partner_job_assigned',
                'name' => 'Partner job assigned',
                'group' => 'partner_jobs',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Generic notice when a partner is assigned a new task in their portal.',
            ],
            [
                'code' => 'partner_welcome',
                'name' => 'Partner welcome (first login)',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'In-app welcome on a partner’s first portal login. Shown in the notification bell, not as a dashboard card.',
            ],
            [
                'code' => 'partner_cover_job_cancelled',
                'name' => 'Partner cover job cancelled',
                'group' => 'partner_jobs',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => false,
                'description' => 'Notify a partner when a cover job assigned to them is cancelled before completion.',
            ],

            // Partner payouts
            [
                'code' => 'partner_payout_requested',
                'name' => 'Partner payout requested',
                'group' => 'partner_payouts',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => false,
                'description' => 'Acknowledge a partner payout / withdrawal request while it awaits review.',
            ],
            [
                'code' => 'partner_payout_paid',
                'name' => 'Partner payout paid',
                'group' => 'partner_payouts',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Notify a partner once their payout request has been marked paid.',
            ],
            [
                'code' => 'partner_payout_rejected',
                'name' => 'Partner payout rejected',
                'group' => 'partner_payouts',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Notify a partner when their payout request is rejected, with reason.',
            ],
            [
                'code' => 'partner_efficiency_warning',
                'name' => 'Partner efficiency warning',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Nudge a field partner whose jobs are failing, late, or escalating.',
            ],
            [
                'code' => 'partner_efficiency_suspended',
                'name' => 'Partner efficiency suspended',
                'group' => 'partners',
                'critical' => true,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Tell a field partner their account was suspended after repeated low performance.',
            ],
            [
                'code' => 'affiliate_volume_warning',
                'name' => 'Affiliate volume warning',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app', 'sms'],
                'default_enabled' => true,
                'description' => 'Nudge an affiliate who is below the monthly new-user target.',
            ],
            [
                'code' => 'affiliate_account_active',
                'name' => 'Affiliate account active',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Once when an Affiliate portal first becomes active.',
            ],
            [
                'code' => 'affiliate_premium_active',
                'name' => 'Premium Affiliate agreement active',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Once when a Premium Affiliate agreement is in force.',
            ],
            [
                'code' => 'affiliate_referral_new',
                'name' => 'Affiliate new referral',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'When a referred customer registers.',
            ],
            [
                'code' => 'affiliate_referral_progressed',
                'name' => 'Affiliate referral progressed',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'When a referred customer starts an application.',
            ],
            [
                'code' => 'affiliate_impact_progressed',
                'name' => 'Premium Affiliate impact update',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'Impact-only notice when a Premium Affiliate referral progresses.',
            ],
            [
                'code' => 'affiliate_commission_earned',
                'name' => 'Affiliate commission earned',
                'group' => 'partners',
                'critical' => false,
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'description' => 'When an eligible commission is recorded.',
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
