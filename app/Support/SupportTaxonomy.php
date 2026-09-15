<?php

namespace App\Support;

use App\Models\Setting;

class SupportTaxonomy
{
    public const SETTING_KEY = 'support.ticket_categories';

    /**
     * @return array{categories: array<string, string>, subjects: array<string, list<string>>}
     */
    public static function defaults(): array
    {
        return [
            'categories' => [
                'complaint' => 'Complaint',
                'suggestion' => 'Suggestion',
                'technical' => 'Technical',
                'loan_inquiry' => 'Loan inquiry',
                'general' => 'General',
                'compliment' => 'Compliment',
                'loan' => 'Loan',
                'payment' => 'Payment',
                'profile' => 'Profile / KYC',
                'reward_fulfilment' => 'Reward fulfilment',
                'broken_page' => 'Broken page',
                'other' => 'Other',
            ],
            'subjects' => [
                'complaint' => ['Service issue', 'Staff conduct', 'Fee dispute', 'Other'],
                'suggestion' => ['Product idea', 'Process improvement', 'Other'],
                'technical' => ['Login / access', 'App / website error', 'Notifications', 'Other'],
                'loan_inquiry' => ['Application status', 'Eligibility', 'Offer / contract', 'Other'],
                'general' => ['Account question', 'How to', 'Other'],
                'compliment' => ['Staff appreciation', 'Service appreciation', 'Other'],
                'loan' => ['Application status', 'Disbursement', 'Repayment schedule', 'Restructuring', 'Other'],
                'payment' => ['Mobile money', 'Failed payment', 'Receipt / confirmation', 'Other'],
                'profile' => ['Identity / NIDA', 'Documents', 'Contact details', 'Other'],
                'reward_fulfilment' => ['Reward fulfilment', 'Other'],
                'broken_page' => ['Page error', 'Missing content', 'Other'],
                'other' => ['General inquiry', 'Other'],
            ],
        ];
    }

    /**
     * @return array{categories: array<string, string>, subjects: array<string, list<string>>}
     */
    public static function all(): array
    {
        $stored = Setting::get(self::SETTING_KEY);
        $defaults = self::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        $categories = is_array($stored['categories'] ?? null) && $stored['categories'] !== []
            ? $stored['categories']
            : $defaults['categories'];
        $subjects = is_array($stored['subjects'] ?? null) && $stored['subjects'] !== []
            ? $stored['subjects']
            : $defaults['subjects'];

        return [
            'categories' => $categories,
            'subjects' => $subjects,
        ];
    }

    /** @return array<string, string> */
    public static function categories(): array
    {
        return self::all()['categories'];
    }

    /** @return list<string> */
    public static function categoryKeys(): array
    {
        return array_keys(self::categories());
    }

    /** @return list<string> */
    public static function subjectsFor(string $category): array
    {
        $subjects = self::all()['subjects'][$category] ?? [];

        return array_values(array_filter($subjects, fn ($s) => is_string($s) && $s !== ''));
    }

    public static function resolveSubject(?string $subject, ?string $subjectOther = null): string
    {
        $subject = trim((string) $subject);
        if (strcasecmp($subject, 'Other') === 0) {
            $other = trim((string) $subjectOther);

            return $other !== '' ? $other : 'Other';
        }

        return $subject !== '' ? $subject : 'General inquiry';
    }

    public static function resolveCategory(?string $category, ?string $categoryOther = null): string
    {
        $category = trim((string) $category);
        if ($category === '' || strcasecmp($category, 'other') === 0) {
            $other = trim((string) $categoryOther);

            return $other !== '' ? $other : ($category !== '' ? 'other' : 'general');
        }

        return $category;
    }

    /**
     * Map a stored category value back to a taxonomy key for the create/edit selectors.
     */
    public static function categoryKeyForStored(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }

        $categories = self::categories();
        if (array_key_exists($stored, $categories)) {
            return $stored;
        }

        foreach ($categories as $key => $label) {
            if (strcasecmp((string) $label, $stored) === 0) {
                return (string) $key;
            }
        }

        return 'other';
    }
}
