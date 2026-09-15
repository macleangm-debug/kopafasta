<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;

class ProfileCompletionService
{
    public function isActivityComplete(Customer $customer): bool
    {
        return $this->isActivityFieldsComplete($customer)
            && app(ProfileValidationService::class)->employmentContractComplete($customer)
            && app(IncomeProofService::class)->satisfiesRequirement($customer);
    }

    /**
     * Activity section fields only (type, income, type-specific details) — used for UI ticks.
     * Document evidence configured on the activity type (e.g. employment contract) still blocks Complete.
     * Income proof remains on its own card via isActivityComplete().
     */
    public function isActivityFieldsComplete(Customer $customer): bool
    {
        $type = $customer->activity_type ?: $customer->employment_type;
        if (! filled($type) || ! filled($customer->income_range)) {
            return false;
        }

        $validation = app(ProfileValidationService::class);
        $fields = config('activity_profiles.fields.'.$type, []);
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

        return true;
    }

    public function isResidenceComplete(Customer $customer): bool
    {
        return filled($customer->region)
            && filled($customer->district)
            && filled($customer->street)
            && filled($customer->lga_officer_name)
            && filled($customer->lga_officer_position)
            && filled($customer->lga_officer_phone);
    }

    /**
     * Profile page sections — synced with onboarding banner labels and status icons.
     *
     * @return list<array{key: string, label: string, status: string, action_url: string|null}>
     */
    public function displaySections(Customer $customer, bool $onlyActionable = true): array
    {
        $faceStatus = $customer->face_verification_status ?? 'incomplete';
        $freshness = app(KycFreshnessService::class);
        $staleKeys = $freshness->sectionsDueForRefresh($customer);

        $personalComplete = app(ProfileValidationService::class)->isPersonalInfoComplete($customer);
        $identityPolicy = app(IdentityVerificationPolicyService::class);
        $requireIdentity = $identityPolicy->requiredDuringProfileCreation();
        $nidaRequired = $identityPolicy->nidaRequired();
        $facialRequired = $identityPolicy->facialRequired();

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
                'status'     => $this->isActivityFieldsComplete($customer) ? 'complete' : 'missing',
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
                'action_url' => route('site.borrower.profile', ['section' => 'activity', 'focus' => 'income']),
            ],
        ];

        if ($nidaRequired || $facialRequired || $requireIdentity) {
            $nidaRevision = ($customer->nida_verification_status ?? '') === 'revision_required'
                || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'nida')
                || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'nida_docs');
            $faceRevision = $faceStatus === 'revision_required'
                || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'face');
            $validation = app(ProfileValidationService::class);
            $idImagesUrl = route('site.borrower.profile', ['section' => 'personal', 'focus' => 'id_images']).'#profile-id-images';
            $identityUrl = route('site.borrower.profile', ['section' => 'personal', 'focus' => 'identity']).'#profile-identity';
            $numberPresent = filled($customer->national_id);

            // Granular identity gaps — never treat a saved NIDA number as missing when only images lack.
            if ($nidaRequired) {
                if ($requireIdentity && ! app(NidaVerificationService::class)->isVerified($customer)) {
                    $sections[] = [
                        'key'        => 'nida',
                        'label'      => __('borrower.profile.gaps.nida_verify'),
                        'status'     => $nidaRevision ? 'stale' : 'missing',
                        'action_url' => $identityUrl,
                    ];
                } elseif (! $numberPresent) {
                    $sections[] = [
                        'key'        => 'nida_number',
                        'label'      => __('borrower.profile.gaps.nida_number'),
                        'status'     => 'missing',
                        'action_url' => $identityUrl,
                    ];
                }

                if (! (bool) $customer->no_physical_nida_card) {
                    if (! $validation->hasDocument($customer, 'national_id_front')) {
                        $sections[] = [
                            'key'        => 'nida_front',
                            'label'      => __('borrower.profile.gaps.nida_front'),
                            'status'     => $nidaRevision ? 'stale' : 'missing',
                            'action_url' => $idImagesUrl,
                        ];
                    }
                    if (! $validation->hasDocument($customer, 'national_id_back')) {
                        $sections[] = [
                            'key'        => 'nida_back',
                            'label'      => __('borrower.profile.gaps.nida_back'),
                            'status'     => $nidaRevision ? 'stale' : 'missing',
                            'action_url' => $idImagesUrl,
                        ];
                    }
                }
            }
            if ($facialRequired) {
                $sections[] = [
                    'key'        => 'face',
                    'label'      => __('borrower.profile.gaps.face'),
                    'status'     => match (true) {
                        app(ProfileRevisionService::class)->faceStepComplete($customer) => 'complete',
                        $faceRevision => 'stale',
                        default => 'missing',
                    },
                    'action_url' => route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']).'#profile-face',
                ];
            }
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

    /**
     * @return array{
     *     percent: int,
     *     remaining: list<string>,
     *     completed: list<string>,
     *     remaining_count: int,
     *     actionable: list<array{key: string, label: string, url: string|null}>
     * }
     */
    public function completionSummary(Customer $customer): array
    {
        $requirements = collect(app(ApplicationProgressService::class)->requirements($customer, null, null))
            ->reject(fn (array $item) => str_starts_with((string) ($item['key'] ?? ''), 'wizard_'))
            ->values();

        $completed = $requirements->where('complete', true)->pluck('label')->values()->all();
        $incomplete = $requirements->where('complete', false)->values();
        $remaining = $incomplete->pluck('label')->values()->all();
        $calculated = $this->calculate($customer);

        $actionable = $incomplete
            ->map(fn (array $item) => [
                'key' => (string) ($item['key'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'url' => $item['action_url'] ?? null,
            ])
            ->filter(fn (array $item) => $item['label'] !== '')
            ->values()
            ->all();

        // Prefer incomplete hub sections when requirement rows are empty.
        if ($actionable === []) {
            foreach ($calculated['sections'] as $section) {
                if (! empty($section['complete'])) {
                    continue;
                }
                $tab = $this->tabStatuses($customer)[$section['key']] ?? null;
                $actionable[] = [
                    'key' => (string) $section['key'],
                    'label' => (string) $section['label'],
                    'url' => $tab['url'] ?? route('site.borrower.profile', ['section' => $section['key']]),
                ];
            }
            $remaining = collect($actionable)->pluck('label')->all();
        }

        return [
            'percent' => $calculated['percent'],
            'remaining' => $remaining,
            'completed' => $completed,
            'remaining_count' => count($actionable),
            'actionable' => $actionable,
        ];
    }

    /** @return array{percent: int, sections: list<array{key: string, label: string, complete: bool, weight: int}>, threshold: int} */
    public function calculate(Customer $customer): array
    {
        // Layman % = required profile hub sections only (not collateral / security / deferred ID).
        $tabs = $this->tabStatuses($customer);
        $sections = [];
        foreach (['personal', 'activity', 'residence', 'kyc', 'payment'] as $key) {
            $tab = $tabs[$key] ?? null;
            if (! $tab || empty($tab['required'])) {
                continue;
            }
            $sections[] = [
                'key'      => $key,
                'label'    => (string) ($tab['label'] ?? $key),
                'complete' => (bool) ($tab['complete'] ?? false),
                'weight'   => 1,
            ];
        }

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

        $personalComplete = $validation->isCorePersonalComplete($customer)
            && $validation->isFamilyComplete($customer)
            && $validation->isKinComplete($customer);

        // Persisted identity evidence (images / face) counts for Profile completion whenever
        // country/product flags require them — not merely “visited cards”.
        if ($identityPolicy->nidaRequired()) {
            $personalComplete = $personalComplete && $validation->nationalIdUploadsComplete($customer);
            if ($requireIdentity) {
                $personalComplete = $personalComplete
                    && app(ProfileRevisionService::class)->nidaStepComplete($customer);
            }
        }
        if ($identityPolicy->facialRequired()) {
            $personalComplete = $personalComplete
                && app(ProfileRevisionService::class)->faceStepComplete($customer);
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
                'complete' => $this->isActivityFieldsComplete($customer),
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
                'url'      => route('site.borrower.profile', ['section' => 'activity', 'focus' => 'income']),
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
                // Optional section: empty is fine — do not look "incomplete" or falsely "complete".
                'assets' => $assetCount > 0 ? 'complete' : 'optional',
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
