<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;

class ProfileCompletionService
{
    public function isActivityComplete(Customer $customer): bool
    {
        if (! filled($customer->activity_type) || ! filled($customer->income_range)) {
            return false;
        }

        $fields = config('activity_profiles.fields.'.$customer->activity_type, []);
        $details = $customer->activity_details ?? [];

        foreach ($fields as $field) {
            if (($field['required'] ?? false) && blank($details[$field['key']] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function isResidenceComplete(Customer $customer): bool
    {
        return filled($customer->region)
            && filled($customer->district)
            && filled($customer->street);
    }

    /**
     * Profile page sections — synced with onboarding banner labels and status icons.
     *
     * @return list<array{key: string, label: string, status: string, action_url: string|null}>
     */
    public function displaySections(Customer $customer, bool $onlyActionable = true): array
    {
        $faceStatus = $customer->face_verification_status ?? 'incomplete';
        $nidaVerified = app(NidaVerificationService::class)->isVerified($customer);
        $freshness = app(KycFreshnessService::class);
        $staleKeys = $freshness->sectionsDueForRefresh($customer);

        $sections = [
            [
                'key'        => 'personal',
                'label'      => __('borrower.profile.personal'),
                'status'     => (filled($customer->first_name) && filled($customer->last_name) && filled($customer->date_of_birth))
                    ? 'complete' : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'personal']),
            ],
            [
                'key'        => 'activity',
                'label'      => __('borrower.profile.activity'),
                'status'     => $this->isActivityComplete($customer) ? 'complete' : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'activity']),
            ],
            [
                'key'        => 'residence',
                'label'      => __('borrower.profile.residence'),
                'status'     => $this->isResidenceComplete($customer) ? 'complete' : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'residence']),
            ],
            [
                'key'        => 'kin',
                'label'      => __('borrower.profile.kin'),
                'status'     => filled($customer->nok_name) && filled($customer->nok_phone) && filled($customer->nok_relationship)
                    && filled($customer->nok_region) && filled($customer->nok_district)
                    ? 'complete' : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'kin']),
            ],
            [
                'key'        => 'documents',
                'label'      => __('borrower.profile.documents_proof'),
                'status'     => $this->isDocumentsComplete($customer) ? 'complete' : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'kyc']),
            ],
            [
                'key'        => 'identity',
                'label'      => __('borrower.nida.title'),
                'status'     => $nidaVerified ? 'complete' : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'personal']),
            ],
            [
                'key'        => 'face',
                'label'      => __('borrower.nida.face_title'),
                'status'     => $faceStatus === 'verified'
                    ? 'complete'
                    : (in_array($faceStatus, ['pending'], true) ? 'pending' : 'missing'),
                'action_url' => route('site.borrower.face-verification'),
            ],
        ];

        foreach ($sections as &$section) {
            if (in_array($section['key'], $staleKeys, true) && $section['status'] === 'complete') {
                $section['status'] = 'missing';
                $section['label'] .= ' '.__('borrower.profile.refresh_required');
            }
        }
        unset($section);

        if ($onlyActionable) {
            return array_values(array_filter(
                $sections,
                fn (array $section) => ! in_array($section['status'], ['complete'], true)
            ));
        }

        return $sections;
    }

    public function isDocumentsComplete(Customer $customer): bool
    {
        $requireIncome = (bool) (Setting::group('kyc')['require_income_proof'] ?? false);
        $requireResidenceLetter = (bool) (Setting::group('kyc')['require_residence_letter'] ?? false);

        if (! $requireIncome && ! $requireResidenceLetter) {
            return true;
        }

        if ($requireIncome) {
            $types = ['bank_statement', 'mobile_money_statement', 'mpesa_statement'];
            $hasIncome = \App\Models\CustomerDocument::query()
                ->where('customer_id', $customer->id)
                ->whereHas('documentType', fn ($q) => $q->whereIn('code', $types))
                ->exists();

            if (! $hasIncome) {
                return false;
            }
        }

        if ($requireResidenceLetter) {
            $hasLetter = \App\Models\CustomerDocument::query()
                ->where('customer_id', $customer->id)
                ->whereHas('documentType', fn ($q) => $q->where('code', 'residence_letter'))
                ->exists();

            if (! $hasLetter) {
                return false;
            }
        }

        return true;
    }

    /** @return array{percent: int, sections: list<array{key: string, label: string, complete: bool, weight: int}>} */
    public function calculate(Customer $customer): array
    {
        $sections = [
            [
                'key'      => 'personal',
                'label'    => __('borrower.profile.personal'),
                'complete' => filled($customer->first_name) && filled($customer->last_name) && filled($customer->date_of_birth),
                'weight'   => 15,
            ],
            [
                'key'      => 'nida',
                'label'    => __('borrower.profile.nida_verification'),
                'complete' => app(NidaVerificationService::class)->isVerified($customer),
                'weight'   => 20,
            ],
            [
                'key'      => 'face',
                'label'    => __('borrower.nida.face_title'),
                'complete' => in_array($customer->face_verification_status, ['pending', 'verified'], true),
                'weight'   => 20,
            ],
            [
                'key'      => 'activity',
                'label'    => __('borrower.profile.activity'),
                'complete' => $this->isActivityComplete($customer),
                'weight'   => 15,
            ],
            [
                'key'      => 'residence',
                'label'    => __('borrower.profile.residence'),
                'complete' => $this->isResidenceComplete($customer),
                'weight'   => 15,
            ],
            [
                'key'      => 'kin',
                'label'    => __('borrower.profile.kin'),
                'complete' => filled($customer->nok_name) && filled($customer->nok_phone) && filled($customer->nok_relationship)
                    && filled($customer->nok_region) && filled($customer->nok_district),
                'weight'   => 15,
            ],
        ];

        $totalWeight = array_sum(array_column($sections, 'weight'));
        $earned = 0;
        foreach ($sections as $section) {
            if ($section['complete']) {
                $earned += $section['weight'];
            }
        }

        return [
            'percent'   => $totalWeight > 0 ? (int) round(($earned / $totalWeight) * 100) : 0,
            'sections'  => $sections,
            'threshold' => (int) (Setting::group('loan')['qualification_min_profile_percent'] ?? 60),
        ];
    }

    public function meetsThreshold(Customer $customer): bool
    {
        $result = $this->calculate($customer);

        return $result['percent'] >= $result['threshold'];
    }
}
