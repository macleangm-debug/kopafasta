<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'agreement_otp',
                'name' => 'Loan Agreement Signing OTP',
                'channel' => 'sms',
                'subject' => 'Agreement Signing Code',
                'body'    => 'Your Kopa Fasta agreement signing code is {{ code }}. Expires in 10 minutes. Do not share.',
            ],
            [
                'code' => 'repayment_due_soon',
                'name' => 'Repayment Due Soon',
                'channel' => 'sms',
                'subject' => 'Loan repayment reminder',
                'body'    => 'Hi {{ name }}, your loan {{ loan_number }} installment of {{ amount }} is due on {{ due_date }}. Pay on time to avoid penalties. — Kopa Fasta',
            ],
            [
                'code' => 'repayment_due_today',
                'name' => 'Repayment Due Today',
                'channel' => 'sms',
                'subject' => 'Repayment due today',
                'body'    => 'Hi {{ name }}, your loan {{ loan_number }} installment of {{ amount }} is due TODAY. Thank you. — Kopa Fasta',
            ],
            [
                'code' => 'repayment_overdue',
                'name' => 'Repayment Overdue',
                'channel' => 'sms',
                'subject' => 'Overdue repayment',
                'body'    => 'Hi {{ name }}, your loan {{ loan_number }} installment of {{ amount }} (due {{ due_date }}) is OVERDUE. Please pay today to avoid further charges. — Kopa Fasta',
            ],
            [
                'code' => 'application_approved',
                'name' => 'Loan Application Approved',
                'channel' => 'sms',
                'subject' => 'Loan approved',
                'body'    => 'Good news {{ name }}! Your loan application {{ application_number }} for {{ amount }} has been APPROVED. Please review and sign your offer letter. — Kopa Fasta',
            ],
            [
                'code' => 'application_rejected',
                'name' => 'Loan Application Rejected',
                'channel' => 'sms',
                'subject' => 'Application update',
                'body'    => 'Hi {{ name }}, your loan application {{ application_number }} was not approved. Reason: {{ reason }}. — Kopa Fasta',
            ],
            [
                'code' => 'loan_disbursed',
                'name' => 'Loan Disbursed',
                'channel' => 'sms',
                'subject' => 'Loan disbursed',
                'body'    => 'Hi {{ name }}, your loan {{ loan_number }} of {{ amount }} has been disbursed. First payment due {{ due_date }}. — Kopa Fasta',
            ],
            [
                'code' => 'agreement_signed',
                'name' => 'Loan Agreement Signed',
                'channel' => 'sms',
                'subject' => 'Agreement signed',
                'body'    => 'Hi {{ name }}, your loan agreement {{ reference }} has been signed successfully. Disbursement will follow shortly. — Kopa Fasta',
            ],
            [
                'code' => 'membership_expiry_30',
                'name' => 'Membership Expiring in 30 Days',
                'channel' => 'sms',
                'subject' => 'KopaFasta membership expires in 30 days',
                'body'    => 'Habari {{ name }}, uanachama wako wa KopaFasta ({{ member_no }}) utaisha tarehe {{ expires_at }} (siku {{ days_remaining }} zilizobaki). Lipa ada ya renewal mapema. — KopaFasta',
            ],
            [
                'code' => 'membership_expiry_14',
                'name' => 'Membership Expiring in 14 Days',
                'channel' => 'sms',
                'subject' => 'KopaFasta membership expires in 14 days',
                'body'    => 'Reminder: {{ name }}, uanachama wako wa KopaFasta ({{ member_no }}) utaisha tarehe {{ expires_at }} — siku {{ days_remaining }} zilizobaki. Lipa renewal. — KopaFasta',
            ],
            [
                'code' => 'membership_expiry_7',
                'name' => 'Membership Expiring in 7 Days',
                'channel' => 'sms',
                'subject' => 'URGENT: KopaFasta membership expires in 7 days',
                'body'    => 'URGENT: {{ name }}, uanachama wako wa KopaFasta ({{ member_no }}) utaisha siku {{ days_remaining }} (tarehe {{ expires_at }}). Lipa renewal sasa ili usisitishe huduma. — KopaFasta',
            ],
            [
                'code' => 'membership_expiry_1',
                'name' => 'Membership Expiring Tomorrow',
                'channel' => 'sms',
                'subject' => 'FINAL: KopaFasta membership expires tomorrow',
                'body'    => 'FINAL REMINDER: {{ name }}, uanachama wako wa KopaFasta ({{ member_no }}) UTAISHA KESHO ({{ expires_at }}). Lipa renewal mara moja. — KopaFasta',
            ],
            [
                'code' => 'membership_renewed',
                'name' => 'Membership Renewed',
                'channel' => 'sms',
                'subject' => 'Membership renewed',
                'body'    => 'Asante {{ name }}! Uanachama wako wa KopaFasta ({{ member_no }}) umerenewed mpaka {{ expires_at }}. Karibu tena. — KopaFasta',
            ],
            [
                'code' => 'membership_issued',
                'name' => 'Membership Issued',
                'channel' => 'sms',
                'subject' => 'Welcome to KopaFasta',
                'body'    => 'Karibu KopaFasta {{ name }}! Namba yako ya uanachama ni {{ member_no }}. Imeanza {{ issued_at }} na itaisha {{ expires_at }}. — KopaFasta',
            ],
            [
                'code' => 'application_document_request',
                'name' => 'Application Document Request',
                'channel' => 'all',
                'subject' => 'Document needed for {{ application_number }}',
                'body'    => 'Hi {{ name }}, additional documents are required for application {{ application_number }}. {{ instructions }} Please log in to KopaFasta to upload by {{ due_date }}.',
            ],
            [
                'code' => 'group_member_replacement_requested',
                'name' => 'Group Member Replacement Requested',
                'channel' => 'all',
                'subject' => 'Replace group member — {{ application_number }}',
                'body'    => 'Hi {{ name }}, underwriting requires a replacement for {{ member_name }} on group application {{ application_number }}. {{ feedback }} Log in to add a replacement: {{ application_url }} — KopaFasta',
            ],
            [
                'code' => 'group_member_review_feedback',
                'name' => 'Group Member Review Feedback',
                'channel' => 'all',
                'subject' => 'Group member update — {{ application_number }}',
                'body'    => 'Hi {{ name }}, update for {{ member_name }} on application {{ application_number }}: {{ feedback }} View: {{ application_url }} — KopaFasta',
            ],
            [
                'code' => 'group_application_review_feedback',
                'name' => 'Group Application Review Feedback',
                'channel' => 'all',
                'subject' => 'Group application update — {{ application_number }}',
                'body'    => 'Hi {{ name }}, update on your group application {{ application_number }}: {{ feedback }} View: {{ application_url }} — KopaFasta',
            ],
            [
                'code' => 'group_contract_member_declined',
                'name' => 'Group Contract Declined by Member',
                'channel' => 'all',
                'subject' => 'Member declined contract — {{ application_number }}',
                'body'    => 'Hi {{ name }}, {{ member_name }} declined to sign the group loan contract for {{ application_number }}. Add a replacement member to continue: {{ application_url }} — KopaFasta',
            ],
            [
                'code' => 'group_contract_sign_required',
                'name' => 'Group Contract Signature Required',
                'channel' => 'all',
                'subject' => 'Sign group contract — {{ application_number }}',
                'body'    => 'Hi {{ name }}, {{ leader_name }}\'s group loan ({{ application_number }}) needs your contract signature. Sign here: {{ contract_url }} — KopaFasta',
            ],
            [
                'code' => 'group_member_consent_required',
                'name' => 'Group Membership Consent Required',
                'channel' => 'all',
                'subject' => 'Join group loan application',
                'body'    => 'Hi {{ name }}, {{ leader_name }} added you to a group loan application. Complete your membership consent: {{ onboarding_url }} — KopaFasta',
            ],
            [
                'code' => 'group_contract_member_signed',
                'name' => 'Group Contract Signed by Member',
                'channel' => 'all',
                'subject' => 'Member signed contract — {{ application_number }}',
                'body'    => 'Hi {{ name }}, {{ member_name }} signed the group loan contract for {{ application_number }}. View progress: {{ application_url }} — KopaFasta',
            ],
        ];

        foreach ($templates as $t) {
            NotificationTemplate::updateOrCreate(
                ['code' => $t['code']],
                $t + ['is_active' => true]
            );
        }
    }
}
