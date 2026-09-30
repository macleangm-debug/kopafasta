<?php

namespace App\Support;

use App\Models\Setting;

class SupportTaxonomy
{
    public const SETTING_KEY = 'support.ticket_categories';

    /**
     * @return array{
     *     categories: array<string, string>,
     *     subjects: array<string, list<string>>,
     *     default_priority: array<string, string>
     * }
     */
    public static function defaults(): array
    {
        return [
            'categories' => [
                'account_access' => 'Account & access',
                'loan_application' => 'Loan application',
                'payments' => 'Payments',
                'repayments' => 'Repayments',
                'asset_marketplace' => 'Asset marketplace',
                'partner_affiliate' => 'Partner/Affiliate',
                'technical' => 'Technical',
                'other' => 'Other',
            ],
            'subjects' => [
                'account_access' => ['PIN/Login', 'Profile/KYC', 'Activation'],
                'loan_application' => [
                    'Application progress',
                    'Guarantor',
                    'Underwriting',
                    'Offer',
                    'Documents',
                    'Disbursement',
                ],
                'payments' => [
                    'Payment not reflecting',
                    'Failed payment',
                    'Wrong amount',
                    'Refund/reversal',
                ],
                'repayments' => [
                    'Instalment',
                    'Balance',
                    'Late payment',
                    'Payment allocation',
                ],
                'asset_marketplace' => ['Order/asset', 'Supplier', 'Deposit', 'Delivery'],
                'partner_affiliate' => [
                    'Account/access',
                    'Application',
                    'Commission',
                    'Payment',
                    'Workspace',
                ],
                'technical' => [
                    'Page/error',
                    'Unable to continue',
                    'Notification/SMS',
                    'Other technical issue',
                ],
                'other' => ['General follow-up'],
            ],
            'default_priority' => [
                'account_access' => 'high',
                'loan_application' => 'normal',
                'payments' => 'high',
                'repayments' => 'high',
                'asset_marketplace' => 'normal',
                'partner_affiliate' => 'normal',
                'technical' => 'high',
                'other' => 'normal',
            ],
        ];
    }

    /**
     * @return array{
     *     categories: array<string, string>,
     *     subjects: array<string, list<string>>,
     *     default_priority: array<string, string>
     * }
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
        $defaultPriority = is_array($stored['default_priority'] ?? null) && $stored['default_priority'] !== []
            ? $stored['default_priority']
            : ($defaults['default_priority'] ?? []);

        return [
            'categories' => $categories,
            'subjects' => $subjects,
            'default_priority' => $defaultPriority,
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

    /** Settings-backed default priority for a category key (normal/high/urgent). */
    public static function defaultPriorityFor(?string $category): string
    {
        $key = self::categoryKeyForStored($category);
        $map = self::all()['default_priority'] ?? [];
        $priority = is_string($map[$key] ?? null) ? strtolower($map[$key]) : 'normal';

        return in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal';
    }

    public static function resolveSubject(?string $subject, ?string $subjectOther = null): string
    {
        $subject = trim((string) $subject);
        if (strcasecmp($subject, 'Other') === 0) {
            $other = trim((string) $subjectOther);

            return $other !== '' ? $other : 'Other';
        }

        return $subject !== '' ? $subject : 'General follow-up';
    }

    public static function resolveCategory(?string $category, ?string $categoryOther = null): string
    {
        $category = trim((string) $category);
        if ($category === '' || strcasecmp($category, 'other') === 0) {
            $other = trim((string) $categoryOther);

            return $other !== '' ? $other : 'other';
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
