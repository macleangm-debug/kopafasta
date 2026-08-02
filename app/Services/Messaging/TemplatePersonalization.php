<?php

namespace App\Services\Messaging;

/**
 * Click-to-insert personalization tokens for notification templates.
 */
class TemplatePersonalization
{
    /**
     * @return list<array{token: string, label: string, group: string, example: string}>
     */
    public static function fields(): array
    {
        return [
            ['token' => 'name', 'label' => 'Customer name', 'group' => 'Customer', 'example' => 'Asha Juma'],
            ['token' => 'first_name', 'label' => 'First name', 'group' => 'Customer', 'example' => 'Asha'],
            ['token' => 'phone', 'label' => 'Phone', 'group' => 'Customer', 'example' => '+255712345678'],
            ['token' => 'member_no', 'label' => 'Member number', 'group' => 'Customer', 'example' => 'KF-000123'],

            ['token' => 'loan_number', 'label' => 'Loan number', 'group' => 'Loan', 'example' => 'LN-2026-0042'],
            ['token' => 'application_number', 'label' => 'Application number', 'group' => 'Loan', 'example' => 'APP-8891'],
            ['token' => 'amount', 'label' => 'Amount', 'group' => 'Loan', 'example' => 'TZS 50,000'],
            ['token' => 'balance', 'label' => 'Remaining balance', 'group' => 'Loan', 'example' => 'TZS 450,000'],
            ['token' => 'due_date', 'label' => 'Due date', 'group' => 'Loan', 'example' => '15 Aug 2026'],
            ['token' => 'installment_no', 'label' => 'Installment #', 'group' => 'Loan', 'example' => '3'],
            ['token' => 'penalty_amount', 'label' => 'Penalty amount', 'group' => 'Loan', 'example' => 'TZS 2,500'],
            ['token' => 'reason', 'label' => 'Reason / note', 'group' => 'Loan', 'example' => 'Incomplete documents'],

            ['token' => 'code', 'label' => 'OTP code', 'group' => 'Security', 'example' => '482910'],
            ['token' => 'minutes', 'label' => 'OTP expiry (minutes)', 'group' => 'Security', 'example' => '10'],

            ['token' => 'expires_at', 'label' => 'Membership expiry', 'group' => 'Membership', 'example' => '01 Sep 2026'],
            ['token' => 'days_remaining', 'label' => 'Days remaining', 'group' => 'Membership', 'example' => '7'],
            ['token' => 'issued_at', 'label' => 'Issued date', 'group' => 'Membership', 'example' => '01 Sep 2025'],

            ['token' => 'upload_url', 'label' => 'Upload link', 'group' => 'Links', 'example' => 'https://…'],
            ['token' => 'application_url', 'label' => 'Application link', 'group' => 'Links', 'example' => 'https://…'],
            ['token' => 'contract_url', 'label' => 'Contract link', 'group' => 'Links', 'example' => 'https://…'],
            ['token' => 'onboarding_url', 'label' => 'Onboarding link', 'group' => 'Links', 'example' => 'https://…'],
            ['token' => 'instructions', 'label' => 'Instructions', 'group' => 'Links', 'example' => 'Upload a clearer selfie'],
        ];
    }

    /** @return array<string, list<array{token: string, label: string, group: string, example: string}>> */
    public static function grouped(): array
    {
        return collect(self::fields())->groupBy('group')->all();
    }
}
