<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;

class GroupLoanNotificationService
{
    public function notifyLeaderMemberFeedback(
        Customer $leader,
        LoanApplication $application,
        string $memberName,
        string $feedback,
        string $underwritingStatus,
    ): void {
        $applicationUrl = route('site.borrower.application', $application->id);
        $reference = $application->application_number;

        $isReplacement = $underwritingStatus === 'replacement_requested';
        $templateCode = $isReplacement
            ? 'group_member_replacement_requested'
            : 'group_member_review_feedback';

        $inAppTitle = $isReplacement
            ? __('borrower.apply.group.replacement_requested_title')
            : __('borrower.apply.group.leader_feedback_title');

        $inAppMessage = $isReplacement
            ? __('borrower.apply.group.replacement_requested_notice', [
                'name' => $memberName,
                'reference' => $reference,
                'feedback' => $feedback,
            ])
            : __('borrower.apply.group.underwriter_member_feedback', [
                'name' => $memberName,
                'feedback' => $feedback,
            ]);

        $notifier = app(NotificationService::class);

        $notifier->notifyInApp(
            $leader,
            $inAppMessage,
            'group_loan',
            $templateCode,
            $inAppTitle,
            $applicationUrl,
            __('borrower.apply.group.replacement_view_cta'),
        );

        $notifier->notifyCustomer($leader, $templateCode, [
            'name'                 => $leader->first_name ?: $leader->full_name,
            'member_name'          => $memberName,
            'application_number'   => $reference,
            'feedback'             => $feedback,
            'application_url'      => $applicationUrl,
            '_fallback_subject'    => $inAppTitle,
            '_fallback_body'       => $inAppMessage,
        ]);
    }

    public function notifyLeaderGroupFeedback(
        Customer $leader,
        LoanApplication $application,
        string $feedback,
    ): void {
        $applicationUrl = route('site.borrower.application', $application->id);
        $reference = $application->application_number;
        $inAppTitle = __('borrower.apply.group.leader_feedback_title');
        $inAppMessage = __('borrower.apply.group.underwriter_group_feedback', ['feedback' => $feedback]);

        $notifier = app(NotificationService::class);

        $notifier->notifyInApp(
            $leader,
            $inAppMessage,
            'group_loan',
            'group_application_review_feedback',
            $inAppTitle,
            $applicationUrl,
            __('borrower.apply.group.replacement_view_cta'),
        );

        $notifier->notifyCustomer($leader, 'group_application_review_feedback', [
            'name'               => $leader->first_name ?: $leader->full_name,
            'application_number' => $reference,
            'feedback'           => $feedback,
            'application_url'    => $applicationUrl,
            '_fallback_subject'  => $inAppTitle,
            '_fallback_body'     => $inAppMessage,
        ]);
    }

    public function notifyLeaderContractDeclined(
        Customer $leader,
        LoanApplication $application,
        string $memberName,
    ): void {
        $applicationUrl = route('site.borrower.application', $application->id);
        $reference = $application->application_number;
        $inAppTitle = __('borrower.apply.group.replacement_notify_title');
        $inAppMessage = __('borrower.apply.group.contract_member_declined_notice', [
            'name' => $memberName,
            'reference' => $reference,
        ]);

        $notifier = app(NotificationService::class);

        $notifier->notifyInApp(
            $leader,
            $inAppMessage,
            'group_loan',
            'group_contract_declined',
            $inAppTitle,
            $applicationUrl,
            __('borrower.apply.group.replacement_view_cta'),
        );

        $notifier->notifyCustomer($leader, 'group_contract_member_declined', [
            'name'               => $leader->first_name ?: $leader->full_name,
            'member_name'        => $memberName,
            'application_number' => $reference,
            'application_url'    => $applicationUrl,
            '_fallback_subject'  => $inAppTitle,
            '_fallback_body'     => $inAppMessage,
        ]);
    }

    public function notifyMemberContractSignRequired(
        Customer $member,
        LoanApplication $application,
    ): void {
        $contractUrl = route('site.borrower.group-contract.show', $application->id);
        $leaderName = $application->customer?->full_name ?? brand_name();
        $reference = $application->application_number;
        $title = __('borrower.apply.group.contract_sign_title');
        $message = __('borrower.apply.group.contract_sign_notice', [
            'leader'    => $leaderName,
            'reference' => $reference,
        ]);

        $notifier = app(NotificationService::class);

        $notifier->notifyInApp(
            $member,
            $message,
            'group_loan',
            'group_contract_sign_required',
            $title,
            $contractUrl,
            __('borrower.apply.group.contract_sign_cta'),
        );

        $notifier->notifyCustomer($member, 'group_contract_sign_required', [
            'name'                 => $member->first_name ?: $member->full_name,
            'leader_name'          => $leaderName,
            'application_number'   => $reference,
            'contract_url'         => $contractUrl,
            '_fallback_subject'    => $title,
            '_fallback_body'       => $message,
        ]);
    }

    public function notifyInternalMemberConsent(
        Customer $member,
        Customer $leader,
        ?\App\Models\GroupMemberInvitation $invitation = null,
    ): void {
        $inviteUrl = $invitation
            ? route('site.group-member.invite', ['token' => $invitation->token])
            : route('site.group-member.onboarding');
        $title = __('borrower.apply.group.internal_member_sign_title');
        $message = __('borrower.apply.group.internal_member_sign_notice', ['leader' => $leader->full_name]);

        $notifier = app(NotificationService::class);

        $notifier->notifyInApp(
            $member,
            $message,
            'group_loan',
            'group_member_consent_required',
            $title,
            $inviteUrl,
            __('borrower.apply.group.accept_invite'),
        );

        $notifier->notifyCustomer($member, 'group_member_consent_required', [
            'name'               => $member->first_name ?: $member->full_name,
            'leader_name'        => $leader->full_name,
            'onboarding_url'     => $inviteUrl,
            'invite_url'         => $inviteUrl,
            '_fallback_subject'  => $title,
            '_fallback_body'     => $message,
        ]);
    }

    public function notifyLeaderMemberSigned(
        Customer $leader,
        LoanApplication $application,
        string $memberName,
    ): void {
        $applicationUrl = route('site.borrower.application', $application->id);
        $reference = $application->application_number;
        $title = __('borrower.apply.group.contract_member_signed_title');
        $message = __('borrower.apply.group.contract_member_signed_notice', ['name' => $memberName]);

        $notifier = app(NotificationService::class);

        $notifier->notifyInApp(
            $leader,
            $message,
            'group_loan',
            'group_contract_member_signed',
            $title,
            $applicationUrl,
            __('borrower.apply.group.replacement_view_cta'),
        );

        $notifier->notifyCustomer($leader, 'group_contract_member_signed', [
            'name'               => $leader->first_name ?: $leader->full_name,
            'member_name'        => $memberName,
            'application_number' => $reference,
            'application_url'    => $applicationUrl,
            '_fallback_subject'  => $title,
            '_fallback_body'     => $message,
        ]);
    }
}
