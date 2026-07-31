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
        $requireIdentity = app(IdentityVerificationPolicyService::class)->requiredDuringProfileCreation();

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
        ];

        if ($requireIdentity) {
            $nidaRevision = ($customer->nida_verification_status ?? '') === 'revision_required'
                || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'nida')
                || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'nida_docs');
            $faceRevision = $faceStatus === 'revision_required'
                || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'face');

            $sections[] = [
                'key'        => 'identity',
                'label'      => __('borrower.nida.title'),
                'status'     => $nidaVerified ? 'complete' : ($nidaRevision ? 'stale' : 'missing'),
                'action_url' => route('site.borrower.profile', ['section' => 'personal']),
            ];
            $sections[] = [
                'key'        => 'face',
                'label'      => __('borrower.nida.face_title'),
                'status'     => match (true) {
                    in_array($faceStatus, ['verified', 'pending'], true) => 'complete',
                    $faceRevision => 'stale',
                    default => 'missing',
                },
                'action_url' => route('site.borrower.face-verification'),
            ];
        }

        foreach ($sections as &$section) {
            $staleKey = match ($section['key']) {
                'personal' => 'kin',
                default    => $section['key'],
            };
            if (in_array($staleKey, $staleKeys, true) && $section['status'] === 'complete') {
                $section['status'] = 'stale';
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
        $calculated = $this->calculate($customer);

        return [
            'percent'   => $calculated['percent'],
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

    /**
     * Per-tab completion for profile navigation.
     *
     * @return array<string, array{complete: bool, required: bool, label: string, url: string}>
     */
    public function tabStatuses(Customer $customer): array
    {
        $validation = app(ProfileValidationService::class);
        $identityPolicy = app(IdentityVerificationPolicyService::class);
        $requireIdentity = $identityPolicy->requiredDuringProfileCreation();
        $paymentAccounts = app(CustomerDisbursementDetailsService::class)->accountsForCustomer($customer);

        $personalComplete = $validation->isCorePersonalComplete($customer) && $validation->isKinComplete($customer);
        if ($requireIdentity) {
            $personalComplete = $personalComplete && app(ProfileRevisionService::class)->nidaStepComplete($customer);
        }

        $residenceComplete = $this->isResidenceComplete($customer);
        if ($validation->requiresResidenceLetter()) {
            $residenceComplete = $residenceComplete && $validation->hasResidenceLetter($customer);
        }

        return [
            'personal' => [
                'complete' => $personalComplete,
                'required' => true,
                'label'    => __('borrower.profile.personal'),
                'url'      => route('site.borrower.profile', ['section' => 'personal']),
            ],
            'activity' => [
                'complete' => $this->isActivityComplete($customer),
                'required' => true,
                'label'    => __('borrower.profile.activity'),
                'url'      => route('site.borrower.profile', ['section' => 'activity']),
            ],
            'residence' => [
                'complete' => $residenceComplete,
                'required' => true,
                'label'    => __('borrower.profile.residence'),
                'url'      => route('site.borrower.profile', ['section' => 'residence']),
            ],
            'kyc' => [
                'complete' => $this->isDocumentsComplete($customer),
                'required' => true,
                'label'    => __('borrower.profile.kyc'),
                'url'      => route('site.borrower.profile', ['section' => 'kyc']),
            ],
            'security' => [
                'complete' => filled(auth()->user()?->pin_hash),
                'required' => false,
                'label'    => __('borrower.profile.security'),
                'url'      => route('site.borrower.profile', ['section' => 'security']),
            ],
            'payment' => [
                'complete' => app(CustomerDisbursementDetailsService::class)->isComplete($customer),
                'required' => app(ProfileSectionBuilderService::class)->paymentRequiredBeforeLoan(),
                'label'    => __('borrower.payment_details.tab'),
                'url'      => route('site.borrower.profile', ['section' => 'payment']),
            ],
            'assets' => [
                'complete' => $customer->assets()->exists(),
                'required' => false,
                'label'    => __('borrower.profile.my_collaterals'),
                'url'      => route('site.borrower.profile', ['section' => 'assets']),
            ],
        ];
    }

    public function identityRequiredDuringProfile(): bool
    {
        return app(IdentityVerificationPolicyService::class)->requiredDuringProfileCreation();
    }

    /**
     * Rich section statuses for profile hub cards (main categories only).
     *
     * @return array<string, array{complete: bool, required: bool, label: string, url: string, status: string, description?: string, count?: int}>
     */
    public function extendedTabStatuses(Customer $customer): array
    {
        $base = $this->tabStatuses($customer);
        $revision = app(ProfileRevisionService::class);
        $faceStatus = $customer->face_verification_status ?? 'incomplete';
        $assetCount = $customer->assets()->count();

        $resolve = function (bool $complete, string $revisionKey, ?string $pendingStatus = null) use ($revision, $customer): string {
            if ($revision->hasOpenRevision($customer, $revisionKey)) {
                return 'needs_work';
            }
            if ($complete) {
                return 'complete';
            }
            if ($pendingStatus) {
                return $pendingStatus;
            }

            return 'not_started';
        };

        $sections = [];
        foreach ($base as $key => $tab) {
            $complete = (bool) ($tab['complete'] ?? false);
            $status = match ($key) {
                'personal' => $resolve($complete, 'personal', $complete ? null : ($this->personalSectionStarted($customer) ? 'in_progress' : 'not_started')),
                'activity' => $resolve($complete, 'activity', $complete ? null : 'in_progress'),
                'residence' => $resolve($complete, 'residence', $complete ? null : 'in_progress'),
                'kyc' => $resolve($complete, 'kyc', $complete ? null : 'pending'),
                'security' => $complete ? 'complete' : 'not_started',
                'payment' => $complete ? 'complete' : 'not_started',
                'assets' => $assetCount > 0 ? 'complete' : 'not_started',
                default => $resolve($complete, $key),
            };

            // Face verification lives inside Personal — surface open face revisions on that card.
            if ($key === 'personal' && $revision->hasOpenRevision($customer, 'face')) {
                $status = 'needs_work';
                $complete = false;
            } elseif ($key === 'personal' && in_array($faceStatus, ['pending', 'rejected'], true) && ! $complete) {
                $status = $faceStatus === 'rejected' ? 'rejected' : 'under_review';
            }

            $sections[$key] = [
                'complete' => $complete,
                'required' => (bool) ($tab['required'] ?? false),
                'label'    => $tab['label'],
                'url'      => $tab['url'],
                'status'   => $status,
            ];

            if ($key === 'assets') {
                $sections[$key]['count'] = $assetCount;
            }
        }

        return $sections;
    }

    /** True when the borrower has started any personal / next-of-kin field. */
    protected function personalSectionStarted(Customer $customer): bool
    {
        return filled($customer->first_name)
            || filled($customer->last_name)
            || filled($customer->date_of_birth)
            || filled($customer->national_id)
            || filled($customer->nok_first_name)
            || filled($customer->nok_last_name)
            || filled($customer->nok_name)
            || filled($customer->nok_phone)
            || filled($customer->nok_relationship)
            || filled($customer->nok_region)
            || filled($customer->nok_district)
            || filled($customer->nok_street)
            || in_array((string) ($customer->face_verification_status ?? 'incomplete'), ['pending', 'verified', 'rejected'], true);
    }
}
