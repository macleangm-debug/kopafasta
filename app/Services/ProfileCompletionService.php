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

        $validation = app(ProfileValidationService::class);
        $fields = config('activity_profiles.fields.'.$customer->activity_type, []);
        $details = $customer->activity_details ?? [];

        foreach ($fields as $field) {
            if (($field['type'] ?? 'text') === 'document') {
                if (($field['required'] ?? false) && ! $validation->hasDocument($customer, $field['document_code'] ?? $field['key'])) {
                    return false;
                }

                continue;
            }

            if (($field['required'] ?? false) && blank($details[$field['key']] ?? null)) {
                return false;
            }
        }

        return $validation->employmentContractComplete($customer);
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

        $personalComplete = app(ProfileValidationService::class)->isPersonalInfoComplete($customer);

        $sections = [
            [
                'key'        => 'personal',
                'label'      => __('borrower.profile.personal'),
                'status'     => $personalComplete ? 'complete' : 'missing',
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
                'status'     => in_array($faceStatus, ['verified', 'pending'], true)
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
        $requireResidenceLetter = app(ProfileValidationService::class)->requiresResidenceLetter();

        if (! $requireIncome && ! $requireResidenceLetter) {
            return true;
        }

        if ($requireIncome) {
            if (! app(IncomeProofService::class)->satisfiesRequirement($customer)) {
                return false;
            }
        }

        if ($requireResidenceLetter) {
            if (! app(ProfileValidationService::class)->hasResidenceLetter($customer)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{percent: int, remaining: list<string>, completed: list<string>} */
    public function completionSummary(Customer $customer): array
    {
        $requirements = collect(app(ApplicationProgressService::class)->requirements($customer, null, null))
            ->reject(fn (array $item) => str_starts_with((string) ($item['key'] ?? ''), 'wizard_'))
            ->values();

        $completed = $requirements->where('complete', true)->pluck('label')->values()->all();
        $remaining = $requirements->where('complete', false)->pluck('label')->values()->all();
        $percent = (int) (app(ApplicationRequirementsService::class)->onboardingBanner($customer)['percent'] ?? 0);

        return [
            'percent'   => $percent,
            'remaining' => $remaining,
            'completed' => $completed,
        ];
    }

    /** @return array{percent: int, sections: list<array{key: string, label: string, complete: bool, weight: int}>, threshold: int} */
    public function calculate(Customer $customer): array
    {
        $requirements = collect(app(ApplicationProgressService::class)->requirements($customer, null, null))
            ->reject(fn (array $item) => str_starts_with((string) ($item['key'] ?? ''), 'wizard_'))
            ->values();

        $sections = $requirements->map(fn (array $item) => [
            'key'      => (string) ($item['key'] ?? ''),
            'label'    => (string) ($item['label'] ?? ''),
            'complete' => (bool) ($item['complete'] ?? false),
            'weight'   => 1,
        ])->all();

        $totalWeight = max(1, count($sections));
        $earned = collect($sections)->where('complete', true)->count();

        return [
            'percent'   => (int) round(($earned / $totalWeight) * 100),
            'sections'  => $sections,
            'threshold' => (int) (Setting::group('loan')['qualification_min_profile_percent'] ?? 60),
        ];
    }

    public function meetsThreshold(Customer $customer): bool
    {
        $result = $this->calculate($customer);

        return $result['percent'] >= $result['threshold'];
    }

    public function isFullyComplete(Customer $customer): bool
    {
        return ($this->calculate($customer)['percent'] ?? 0) >= 100;
    }
}
