<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $brand = brand_name();

        $templates = [
            [
                'code' => 'pin_reset_otp',
                'name' => 'PIN Reset OTP',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'PIN reset code',
                        'body' => "Your {$brand} PIN reset code is {{ code }}. It expires in {{ minutes }} minutes. Do not share this code.",
                    ],
                    'sw' => [
                        'subject' => 'Nambari ya kuweka PIN upya',
                        'body' => "Nambari yako ya kuweka PIN upya ya {$brand} ni {{ code }}. Inaisha baada ya dakika {{ minutes }}. Usishiriki nambari hii.",
                    ],
                ],
            ],
            [
                'code' => 'agreement_otp',
                'name' => 'Loan Agreement Signing OTP',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Agreement signing code',
                        'body' => "Your {$brand} agreement signing code is {{ code }}. Expires in {{ minutes }} minutes. Do not share.",
                    ],
                    'sw' => [
                        'subject' => 'Nambari ya kusaini mkataba',
                        'body' => "Nambari yako ya kusaini mkataba wa {$brand} ni {{ code }}. Inaisha baada ya dakika {{ minutes }}. Usishiriki nambari hii.",
                    ],
                ],
            ],
            [
                'code' => 'repayment_due_soon',
                'name' => 'Repayment Due Soon',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Loan repayment reminder',
                        'body' => "Hi {{ name }}, your loan {{ loan_number }} installment of {{ amount }} is due on {{ due_date }}. Pay on time to avoid penalties. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Ukumbusho wa lipa mkopo',
                        'body' => "Habari {{ name }}, awamu ya mkopo {{ loan_number }} ya {{ amount }} inadaiwa tarehe {{ due_date }}. Lipa kwa wakati ili kuepuka faini. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'repayment_due_today',
                'name' => 'Repayment Due Today',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Repayment due today',
                        'body' => "Hi {{ name }}, your loan {{ loan_number }} installment of {{ amount }} is due TODAY. Thank you. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Malipo yanadaiwa leo',
                        'body' => "Habari {{ name }}, awamu ya mkopo {{ loan_number }} ya {{ amount }} inadaiwa LEO. Asante. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'repayment_overdue',
                'name' => 'Repayment Overdue',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Overdue repayment',
                        'body' => "Hi {{ name }}, your loan {{ loan_number }} installment of {{ amount }} (due {{ due_date }}) is OVERDUE. Please pay today to avoid further charges. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Malipo yamechelewa',
                        'body' => "Habari {{ name }}, awamu ya mkopo {{ loan_number }} ya {{ amount }} (iliyodaiwa {{ due_date }}) IMECHELEWA. Tafadhali lipa leo ili kuepuka gharama zaidi. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'payment_received',
                'name' => 'Payment Received',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Payment received',
                        'body' => "Hi {{ name }}, we received {{ amount }} for loan {{ loan_number }}. Remaining balance: {{ balance }}. Thank you. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Malipo yamepokelewa',
                        'body' => "Habari {{ name }}, tumepokea {{ amount }} kwa mkopo {{ loan_number }}. Salio lililobaki: {{ balance }}. Asante. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'penalty_accrued',
                'name' => 'Penalty Accrued',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Late fee applied',
                        'body' => "Hi {{ name }}, a late fee of {{ penalty_amount }} was applied to loan {{ loan_number }}. Amount now due: {{ amount }}. Pay promptly to limit further charges. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Faini ya kuchelewa imetumika',
                        'body' => "Habari {{ name }}, faini ya kuchelewa ya {{ penalty_amount }} imetumika kwenye mkopo {{ loan_number }}. Kiasi kinachodaiwa sasa: {{ amount }}. Lipa haraka ili kuepuka gharama zaidi. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'loan_closed',
                'name' => 'Loan Closed',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Loan settled',
                        'body' => "Congratulations {{ name }}! Loan {{ loan_number }} is fully repaid and closed. Thank you for banking with {$brand}.",
                    ],
                    'sw' => [
                        'subject' => 'Mkopo umefungwa',
                        'body' => "Hongera {{ name }}! Mkopo {{ loan_number }} umelipwa kikamilifu na kufungwa. Asante kwa kushirikiana na {$brand}.",
                    ],
                ],
            ],
            [
                'code' => 'loan_arrears',
                'name' => 'Loan in Arrears',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Loan arrears notice',
                        'body' => "Hi {{ name }}, loan {{ loan_number }} is in arrears. Amount overdue: {{ amount }}. Please pay today or contact us. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Taarifa ya deni lililochelewa',
                        'body' => "Habari {{ name }}, mkopo {{ loan_number }} uko katika deni lililochelewa. Kiasi kilichochelewa: {{ amount }}. Tafadhali lipa leo au wasiliana nasi. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'application_approved',
                'name' => 'Loan Application Approved',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Loan approved',
                        'body' => "Good news {{ name }}! Your loan application {{ application_number }} for {{ amount }} has been APPROVED. Please review and sign your offer letter. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Mkopo umeidhinishwa',
                        'body' => "Habari njema {{ name }}! Ombi lako la mkopo {{ application_number }} la {{ amount }} limeIDHINISHWA. Tafadhali soma na usaini barua ya ofa. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'application_rejected',
                'name' => 'Loan Application Rejected',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Application update',
                        'body' => "Hi {{ name }}, your loan application {{ application_number }} was not approved. Reason: {{ reason }}.{{ advice }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Taarifa ya ombi',
                        'body' => "Habari {{ name }}, ombi lako la mkopo {{ application_number }} halikuidhinishwa. Sababu: {{ reason }}.{{ advice }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'application_document_request',
                'name' => 'Application Document Request',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Document needed for {{ application_number }}',
                        'body' => "Hi {{ name }}, additional documents are required for application {{ application_number }}. {{ instructions }} Open your application to upload by {{ due_date }}: {{ upload_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Nyaraka zinahitajika kwa {{ application_number }}',
                        'body' => "Habari {{ name }}, nyaraka za ziada zinahitajika kwa ombi {{ application_number }}. {{ instructions }} Fungua ombi lako kupakia kabla ya {{ due_date }}: {{ upload_url }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'agreement_signed',
                'name' => 'Loan Agreement Signed',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Agreement signed',
                        'body' => "Hi {{ name }}, your loan agreement {{ reference }} has been signed successfully. Disbursement will follow shortly. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Mkataba umesainiwa',
                        'body' => "Habari {{ name }}, mkataba wako wa mkopo {{ reference }} umesainiwa kwa mafanikio. Utoaji wa fedha utafuatia hivi karibuni. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'loan_disbursed',
                'name' => 'Loan Disbursed',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Loan disbursed',
                        'body' => "Hi {{ name }}, your loan {{ loan_number }} of {{ amount }} has been disbursed. First payment due {{ due_date }}. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Mkopo umetolewa',
                        'body' => "Habari {{ name }}, mkopo wako {{ loan_number }} wa {{ amount }} umetolewa. Malipo ya kwanza yanadaiwa {{ due_date }}. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'membership_issued',
                'name' => 'Membership Issued',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => "Welcome to {$brand}",
                        'body' => "Welcome to {$brand}, {{ name }}! Your membership number is {{ member_no }}. It started on {{ issued_at }} and expires on {{ expires_at }}. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => "Karibu {$brand}",
                        'body' => "Karibu {$brand} {{ name }}! Namba yako ya uanachama ni {{ member_no }}. Imeanza {{ issued_at }} na itaisha {{ expires_at }}. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'membership_expiry_30',
                'name' => 'Membership Expiring in 30 Days',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => "{$brand} membership expires in 30 days",
                        'body' => "Hi {{ name }}, your {$brand} membership ({{ member_no }}) expires on {{ expires_at }} ({{ days_remaining }} days remaining). Renew early to avoid interruption. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => "Uanachama wa {$brand} unaisha baada ya siku 30",
                        'body' => "Habari {{ name }}, uanachama wako wa {$brand} ({{ member_no }}) utaisha tarehe {{ expires_at }} (siku {{ days_remaining }} zilizobaki). Lipa ada ya renewal mapema. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'membership_expiry_14',
                'name' => 'Membership Expiring in 14 Days',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => "{$brand} membership expires in 14 days",
                        'body' => "Reminder: {{ name }}, your {$brand} membership ({{ member_no }}) expires on {{ expires_at }} — {{ days_remaining }} days left. Please renew. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => "Uanachama wa {$brand} unaisha baada ya siku 14",
                        'body' => "Ukumbusho: {{ name }}, uanachama wako wa {$brand} ({{ member_no }}) utaisha tarehe {{ expires_at }} — siku {{ days_remaining }} zilizobaki. Lipa renewal. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'membership_expiry_7',
                'name' => 'Membership Expiring in 7 Days',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => "URGENT: {$brand} membership expires in 7 days",
                        'body' => "URGENT: {{ name }}, your {$brand} membership ({{ member_no }}) expires in {{ days_remaining }} days ({{ expires_at }}). Renew now to keep your services active. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => "DHARURA: Uanachama wa {$brand} unaisha baada ya siku 7",
                        'body' => "DHARURA: {{ name }}, uanachama wako wa {$brand} ({{ member_no }}) utaisha siku {{ days_remaining }} (tarehe {{ expires_at }}). Lipa renewal sasa ili usisitishe huduma. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'membership_expiry_1',
                'name' => 'Membership Expiring Tomorrow',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => "FINAL: {$brand} membership expires tomorrow",
                        'body' => "FINAL REMINDER: {{ name }}, your {$brand} membership ({{ member_no }}) EXPIRES TOMORROW ({{ expires_at }}). Renew immediately. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => "MWISHO: Uanachama wa {$brand} unaisha kesho",
                        'body' => "UKUMBUSHO WA MWISHO: {{ name }}, uanachama wako wa {$brand} ({{ member_no }}) UTAISHA KESHO ({{ expires_at }}). Lipa renewal mara moja. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'membership_renewed',
                'name' => 'Membership Renewed',
                'channel' => 'sms',
                'locales' => [
                    'en' => [
                        'subject' => 'Membership renewed',
                        'body' => "Thank you {{ name }}! Your {$brand} membership ({{ member_no }}) has been renewed until {{ expires_at }}. Welcome back. — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Uanachama umesasishwa',
                        'body' => "Asante {{ name }}! Uanachama wako wa {$brand} ({{ member_no }}) umesasishwa mpaka {{ expires_at }}. Karibu tena. — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'group_member_consent_required',
                'name' => 'Group Membership Consent Required',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Join group loan application',
                        'body' => "Hi {{ name }}, {{ leader_name }} added you to a group loan application. Complete your membership consent: {{ onboarding_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Jiunge na ombi la mkopo wa kikundi',
                        'body' => "Habari {{ name }}, {{ leader_name }} amekujumuisha katika ombi la mkopo wa kikundi. Kamilisha idhini yako ya uanachama: {{ onboarding_url }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'group_contract_sign_required',
                'name' => 'Group Contract Signature Required',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Sign group contract — {{ application_number }}',
                        'body' => "Hi {{ name }}, {{ leader_name }}'s group loan ({{ application_number }}) needs your contract signature. Sign here: {{ contract_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Saini mkataba wa kikundi — {{ application_number }}',
                        'body' => "Habari {{ name }}, mkopo wa kikundi cha {{ leader_name }} ({{ application_number }}) unahitaji saini yako ya mkataba. Saini hapa: {{ contract_url }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'group_member_replacement_requested',
                'name' => 'Group Member Replacement Requested',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Replace group member — {{ application_number }}',
                        'body' => "Hi {{ name }}, underwriting requires a replacement for {{ member_name }} on group application {{ application_number }}. {{ feedback }} Open your application to add a replacement: {{ application_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Badili mwanachama wa kikundi — {{ application_number }}',
                        'body' => "Habari {{ name }}, ukaguzi unahitaji mbadala wa {{ member_name }} kwenye ombi la kikundi {{ application_number }}. {{ feedback }} Fungua ombi lako kuongeza mbadala: {{ application_url }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'group_member_review_feedback',
                'name' => 'Group Member Review Feedback',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Group member update — {{ application_number }}',
                        'body' => "Hi {{ name }}, update for {{ member_name }} on application {{ application_number }}: {{ feedback }} View: {{ application_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Taarifa ya mwanachama wa kikundi — {{ application_number }}',
                        'body' => "Habari {{ name }}, taarifa kuhusu {{ member_name }} kwenye ombi {{ application_number }}: {{ feedback }} Angalia: {{ application_url }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'group_application_review_feedback',
                'name' => 'Group Application Review Feedback',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Group application update — {{ application_number }}',
                        'body' => "Hi {{ name }}, update on your group application {{ application_number }}: {{ feedback }} View: {{ application_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Taarifa ya ombi la kikundi — {{ application_number }}',
                        'body' => "Habari {{ name }}, taarifa kuhusu ombi lako la kikundi {{ application_number }}: {{ feedback }} Angalia: {{ application_url }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'group_contract_member_declined',
                'name' => 'Group Contract Declined by Member',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Member declined contract — {{ application_number }}',
                        'body' => "Hi {{ name }}, {{ member_name }} declined to sign the group loan contract for {{ application_number }}. Add a replacement member to continue: {{ application_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Mwanachama amekataa mkataba — {{ application_number }}',
                        'body' => "Habari {{ name }}, {{ member_name }} amekataa kusaini mkataba wa mkopo wa kikundi kwa {{ application_number }}. Ongeza mwanachama mbadala kuendelea: {{ application_url }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'group_contract_member_signed',
                'name' => 'Group Contract Signed by Member',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Member signed contract — {{ application_number }}',
                        'body' => "Hi {{ name }}, {{ member_name }} signed the group loan contract for {{ application_number }}. View progress: {{ application_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Mwanachama amesaini mkataba — {{ application_number }}',
                        'body' => "Habari {{ name }}, {{ member_name }} amesaini mkataba wa mkopo wa kikundi kwa {{ application_number }}. Angalia maendeleo: {{ application_url }} — {$brand}",
                    ],
                ],
            ],
            [
                'code' => 'marketplace_viewing_scheduled',
                'name' => 'Marketplace Viewing Scheduled',
                'channel' => 'all',
                'locales' => [
                    'en' => [
                        'subject' => 'Viewing scheduled — {{ asset_title }}',
                        'body' => "Hi {{ name }}, your viewing for {{ asset_title }} is set for {{ viewing_when }}. Details: {{ reserve_url }} — {$brand}",
                    ],
                    'sw' => [
                        'subject' => 'Tazama mali imepangwa — {{ asset_title }}',
                        'body' => "Habari {{ name }}, kutazama {{ asset_title }} kumepangwa {{ viewing_when }}. Maelezo: {{ reserve_url }} — {$brand}",
                    ],
                ],
            ],
        ];

        foreach ($templates as $t) {
            foreach ($t['locales'] as $locale => $content) {
                NotificationTemplate::updateOrCreate(
                    ['code' => $t['code'], 'locale' => $locale],
                    [
                        'name' => $t['name'],
                        'channel' => $t['channel'],
                        'subject' => $content['subject'],
                        'body' => $content['body'],
                        'is_active' => true,
                    ]
                );
            }
        }

        app(\App\Services\Messaging\TransactionalMessagingService::class)->ensureDefaults();
    }
}
