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

        return app(ProfileValidationService::class)->businessVerificationComplete($customer);
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

    public function residenceSectionComplete(Customer $customer): bool
    {
        $validation = app(ProfileValidationService::class);
        $complete = $this->isResidenceComplete($customer);
        if ($validation->requiresResidenceLetter()) {
            $complete = $complete && $validation->hasResidenceLetter($customer);
        }

        return $complete;
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
                'status'     => $this->residenceSectionComplete($customer) ? 'complete' : 'missing',
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
        $requireIncome = app(IncomeProofService::class)->isRequired();
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
     * Field-level gaps for one hub section — same source as card status / %.
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    public function sectionGaps(Customer $customer, string $key): array
    {
        return match ($key) {
            'personal' => app(ProfileValidationService::class)->personalGaps($customer),
            // Work & Money lists Activity gaps and Income Verification gaps separately.
            'activity' => $this->uniqueGaps(array_merge(
                $this->activityGaps($customer),
                $this->incomeProofGaps($customer),
            )),
            'residence' => $this->residenceGaps($customer),
            'payment' => $this->paymentGaps($customer),
            'kyc' => $this->documentGaps($customer),
            default => [],
        };
    }

    /**
     * Field progress for one hub section — derived only from sectionGaps() + the same
     * applicable requirement set (never invent totals like max(missing+1, 3)).
     *
     * @return array{done: int, total: int, remaining: int}
     */
    public function sectionProgress(Customer $customer, string $key): array
    {
        $requirements = $this->sectionRequirements($customer, $key);
        $gaps = $this->sectionGaps($customer, $key);
        $total = count($requirements);
        $remaining = count($gaps);
        if ($total < $remaining) {
            // Requirements can shrink when activity type is unset; never invent "done".
            $total = $remaining;
        }

        return [
            'done' => max(0, $total - $remaining),
            'total' => $total,
            'remaining' => $remaining,
        ];
    }

    /**
     * Applicable requirement keys for a section (same policy as sectionGaps).
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    public function sectionRequirements(Customer $customer, string $key): array
    {
        return match ($key) {
            'personal' => $this->personalRequirements($customer),
            'activity' => array_merge(
                $this->activityRequirements($customer),
                $this->incomeProofRequirements($customer),
            ),
            'residence' => $this->residenceRequirements($customer),
            'payment' => $this->paymentRequirements($customer),
            'kyc' => $this->documentRequirements($customer),
            default => [],
        };
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function documentRequirements(Customer $customer): array
    {
        $items = [];
        foreach (app(IncomeProofService::class)->requirementItems($customer) as $item) {
            $items[] = [
                'key' => (string) ($item['key'] ?? 'income'),
                'label' => (string) ($item['label'] ?? __('borrower.loan_profile.sections.proof_of_income')),
                'url' => $item['action_url'] ?? route('site.borrower.profile', [
                    'section' => 'activity',
                    'focus' => 'income',
                    'edit' => 1,
                ]),
            ];
        }
        $validation = app(ProfileValidationService::class);
        if ($validation->requiresResidenceLetter()) {
            $items[] = [
                'key' => 'residence_letter',
                'label' => __('borrower.profile.residence_letter'),
                'url' => route('site.borrower.profile', [
                    'section' => 'residence',
                    'focus' => 'verification',
                    'edit' => 1,
                ]).'#profile-residence-verification',
            ];
        }

        return $this->uniqueGaps($items);
    }

    /**
     * Applicable Activity gaps only (type-specific fields + employment evidence + income when required).
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    public function activityGaps(Customer $customer): array
    {
        $type = $customer->activity_type ?: $customer->employment_type;
        $validation = app(ProfileValidationService::class);
        $details = is_array($customer->activity_details) ? $customer->activity_details : [];
        $gaps = [];

        foreach ($this->activityRequirements($customer) as $req) {
            $key = (string) ($req['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $kind = (string) ($req['kind'] ?? 'field');
            $missing = match ($kind) {
                'activity_type' => ! filled($type),
                'income_range' => ! filled($customer->income_range),
                'employment_contract' => ! $validation->hasDocument($customer, 'employment_contract'),
                'document' => ! $validation->hasDocument($customer, $req['document_code'] ?? $key),
                default => blank($details[$key] ?? null),
            };

            if (! $missing) {
                continue;
            }

            $gaps[] = [
                'key' => $key,
                'label' => (string) ($req['label'] ?? $key),
                'url' => (string) ($req['url'] ?? ''),
            ];
        }

        return $this->uniqueGaps($gaps);
    }

    /**
     * Pre-screening only. Explains a drop caused by a KYC policy change.
     * Applications already in Screening or later are left alone.
     *
     * @return array{title: string, body: string, items: list<array{key: string, label: string, url: string}>}|null
     */
    public function policyUpdateNotice(Customer $customer): ?array
    {
        $changedRaw = Setting::get('kyc.policy_changed_at');
        if (! filled($changedRaw)) {
            return null;
        }

        try {
            $changedAt = \Illuminate\Support\Carbon::parse((string) $changedRaw);
        } catch (\Throwable) {
            return null;
        }

        if (! $this->preScreeningPolicyApplies($customer, $changedAt)) {
            return null;
        }

        $items = [];
        foreach (['activity', 'residence'] as $section) {
            foreach ($this->sectionGaps($customer, $section) as $gap) {
                if ($this->isPolicyGatedGap($customer, (string) ($gap['key'] ?? ''))) {
                    $items[] = $gap;
                }
            }
        }

        $items = $this->uniqueGaps($items);
        if ($items === []) {
            return null;
        }

        return [
            'title' => __('borrower.profile.requirements_updated_title'),
            'body' => __('borrower.profile.requirements_updated_body'),
            'items' => $items,
        ];
    }

    private function preScreeningPolicyApplies(Customer $customer, \Illuminate\Support\Carbon $changedAt): bool
    {
        $applicationIds = \App\Models\GuarantorInvitation::query()
            ->where('guarantor_customer_id', $customer->id)
            ->pluck('loan_application_id')
            ->filter()
            ->all();

        return \App\Models\LoanApplication::query()
            ->where('status', 'awaiting_guarantor')
            ->where(function ($query) use ($customer, $applicationIds) {
                $query->where('customer_id', $customer->id);
                if ($applicationIds !== []) {
                    $query->orWhereIn('id', $applicationIds);
                }
            })
            ->where(function ($query) use ($changedAt) {
                $query->where('submitted_at', '<', $changedAt)
                    ->orWhere(function ($inner) use ($changedAt) {
                        $inner->whereNull('submitted_at')->where('created_at', '<', $changedAt);
                    });
            })
            ->exists();
    }

    private function isPolicyGatedGap(Customer $customer, string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $validation = app(ProfileValidationService::class);
        if (in_array($key, ['tin_number', 'tin_certificate'], true)) {
            return $validation->requiresBusinessTin($customer);
        }
        if (in_array($key, ['licence_number', 'licence_authority', 'licence_issued_on', 'business_license'], true)) {
            return $validation->requiresBusinessLicence($customer);
        }
        if ($key === 'residence_letter') {
            return $validation->requiresResidenceLetter();
        }
        if (! app(IncomeProofService::class)->isRequired()) {
            return false;
        }

        return collect($this->incomeProofGaps($customer))
            ->contains(fn (array $gap) => ($gap['key'] ?? '') === $key);
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public function residenceGaps(Customer $customer): array
    {
        $gaps = [];
        $validation = app(ProfileValidationService::class);

        foreach ($this->residenceRequirements($customer) as $req) {
            $key = (string) ($req['key'] ?? '');
            if ($key === 'residence_letter') {
                if (! $validation->hasResidenceLetter($customer)) {
                    $gaps[] = [
                        'key' => $key,
                        'label' => (string) $req['label'],
                        'url' => (string) $req['url'],
                    ];
                }

                continue;
            }
            if ($key === 'street') {
                if (! filled($customer->street ?: $customer->address)) {
                    $gaps[] = [
                        'key' => $key,
                        'label' => (string) $req['label'],
                        'url' => (string) $req['url'],
                    ];
                }

                continue;
            }
            if (! filled($customer->{$key} ?? null)) {
                $gaps[] = [
                    'key' => $key,
                    'label' => (string) $req['label'],
                    'url' => (string) $req['url'],
                ];
            }
        }

        return $gaps;
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public function paymentGaps(Customer $customer): array
    {
        $requirements = $this->paymentRequirements($customer);
        if ($requirements === []) {
            return [];
        }
        if (app(CustomerDisbursementDetailsService::class)->isComplete($customer)) {
            return [];
        }

        return $requirements;
    }

    /**
     * Legacy document-bucket gaps (income + residence letter). Prefer activity/residence gaps in hub UI.
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    public function documentGaps(Customer $customer): array
    {
        $gaps = [];
        foreach (app(IncomeProofService::class)->requirementItems($customer) as $item) {
            if (! empty($item['complete'])) {
                continue;
            }
            $gaps[] = [
                'key' => (string) ($item['key'] ?? 'income'),
                'label' => (string) ($item['label'] ?? __('borrower.loan_profile.sections.proof_of_income')),
                'url' => $item['action_url'] ?? route('site.borrower.profile', ['section' => 'activity', 'focus' => 'income']),
            ];
        }
        $validation = app(ProfileValidationService::class);
        if ($validation->requiresResidenceLetter() && ! $validation->hasResidenceLetter($customer)) {
            $gaps[] = [
                'key' => 'residence_letter',
                'label' => __('borrower.profile.residence_letter'),
                'url' => route('site.borrower.profile', ['section' => 'residence']).'#profile-residence-verification',
            ];
        }

        return $this->uniqueGaps($gaps);
    }

    /**
     * @param  list<array{key: string, label: string, url: string}>  $gaps
     * @return list<array{key: string, label: string, url: string}>
     */
    protected function uniqueGaps(array $gaps): array
    {
        $seen = [];
        $unique = [];
        foreach ($gaps as $gap) {
            $key = (string) ($gap['key'] ?? '');
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            if ($key !== '') {
                $seen[$key] = true;
            }
            $unique[] = $gap;
        }

        return $unique;
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
        // One canonical source: calculate() + sectionGaps(). Never mix apply-checklist rows into %.
        $calculated = $this->calculate($customer);
        $tabs = $this->tabStatuses($customer);

        $completed = [];
        $actionable = [];

        foreach ($calculated['sections'] as $section) {
            $key = (string) ($section['key'] ?? '');
            $label = (string) ($section['label'] ?? $key);
            if (! empty($section['complete'])) {
                $completed[] = $label;
                continue;
            }

            $gaps = $this->sectionGaps($customer, $key);
            if ($gaps === []) {
                $actionable[] = [
                    'key' => $key,
                    'label' => $label,
                    'url' => $tabs[$key]['url'] ?? route('site.borrower.profile', ['section' => $key]),
                ];
                continue;
            }

            foreach ($gaps as $gap) {
                $actionable[] = [
                    'key' => (string) ($gap['key'] ?? $key),
                    'label' => (string) ($gap['label'] ?? $label),
                    'url' => $gap['url'] ?? ($tabs[$key]['url'] ?? null),
                ];
            }
        }

        $actionable = $this->uniqueGaps(array_map(static fn (array $item) => [
            'key' => (string) ($item['key'] ?? ''),
            'label' => (string) ($item['label'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
        ], $actionable));

        // Restore nullable url for callers that expect null.
        $actionable = array_map(static function (array $item) {
            $url = trim((string) ($item['url'] ?? ''));

            return [
                'key' => $item['key'],
                'label' => $item['label'],
                'url' => $url !== '' ? $url : null,
            ];
        }, $actionable);

        return [
            'percent' => $calculated['percent'],
            'remaining' => collect($actionable)->pluck('label')->values()->all(),
            'completed' => $completed,
            'remaining_count' => count($actionable),
            'actionable' => $actionable,
        ];
    }

    /** @return array{percent: int, sections: list<array{key: string, label: string, complete: bool, weight: int}>, threshold: int} */
    public function calculate(Customer $customer): array
    {
        // Requirement-level % — each canonical gap/requirement counts equally.
        // Collateral/assets never enter the denominator. Categories stay for UI only.
        $tabs = $this->tabStatuses($customer);
        $sections = [];
        $total = 0;
        $done = 0;

        foreach (['personal', 'activity', 'residence', 'payment'] as $key) {
            $tab = $tabs[$key] ?? null;
            if (! $tab || empty($tab['required'])) {
                continue;
            }
            $progress = $this->sectionProgress($customer, $key);
            $total += (int) $progress['total'];
            $done += (int) $progress['done'];
            $sections[] = [
                'key'      => $key,
                'label'    => (string) ($tab['label'] ?? $key),
                'complete' => (bool) ($tab['complete'] ?? false),
                'weight'   => max(1, (int) $progress['total']),
            ];
        }

        $percent = $total > 0
            ? (int) round(($done / $total) * 100)
            : 0;

        return [
            'percent'   => $percent,
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
                // Hub complete when Activity fields + Income Verification (when required) are done.
                // Activity *card* tick uses isActivityFieldsComplete() — never waits on income proof alone.
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
            // Folded into activity (income) + residence (letter). Kept for legacy redirects; never in %.
            'kyc' => [
                'complete' => $this->isDocumentsComplete($customer),
                'required' => false,
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

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function personalRequirements(Customer $customer): array
    {
        $items = [
            [
                'key' => 'name',
                'label' => __('borrower.profile.gaps.full_name'),
                'url' => route('site.borrower.profile', [
                    'section' => 'personal',
                    'focus' => 'identity',
                    'edit' => 1,
                ]).'#profile-identity',
            ],
            [
                'key' => 'dob',
                'label' => __('borrower.profile.gaps.date_of_birth'),
                'url' => route('site.borrower.profile', [
                    'section' => 'personal',
                    'focus' => 'about',
                    'edit' => 1,
                    'field' => 'date_of_birth',
                ]).'#profile-about',
            ],
        ];

        $identityPolicy = app(IdentityVerificationPolicyService::class);
        $kycRequireNida = (bool) (Setting::group('kyc')['require_nida'] ?? true);
        if ($kycRequireNida && $identityPolicy->requiredDuringProfileCreation() && $identityPolicy->nidaRequired()) {
            $items[] = [
                'key' => 'nida',
                'label' => __('borrower.profile.gaps.nida_verify'),
                'url' => route('site.borrower.profile', [
                    'section' => 'personal',
                    'focus' => 'identity',
                    'edit' => 1,
                ]).'#profile-identity',
            ];
        }
        if ($kycRequireNida && $identityPolicy->nidaRequired() && ! $customer->no_physical_nida_card) {
            $idImagesUrl = route('site.borrower.profile', [
                'section' => 'personal',
                'focus' => 'id_images',
                'edit' => 1,
            ]).'#profile-id-images';
            $items[] = [
                'key' => 'nida_front',
                'label' => __('borrower.profile.gaps.nida_front'),
                'url' => $idImagesUrl,
            ];
            $items[] = [
                'key' => 'nida_back',
                'label' => __('borrower.profile.gaps.nida_back'),
                'url' => $idImagesUrl,
            ];
        }

        $items[] = [
            'key' => 'family',
            'label' => __('borrower.profile.gaps.family'),
            'url' => route('site.borrower.profile', [
                'section' => 'personal',
                'focus' => 'family',
                'edit' => 1,
            ]).'#profile-family',
        ];
        $items[] = [
            'key' => 'kin',
            'label' => __('borrower.profile.gaps.next_of_kin'),
            'url' => route('site.borrower.profile', [
                'section' => 'personal',
                'focus' => 'kin',
                'edit' => 1,
            ]).'#next-of-kin',
        ];

        if ($identityPolicy->facialRequired()) {
            $items[] = [
                'key' => 'face',
                'label' => __('borrower.profile.gaps.face'),
                'url' => route('site.borrower.profile', [
                    'section' => 'personal',
                    'focus' => 'face',
                    'edit' => 1,
                ]).'#profile-face',
            ];
        }

        return $items;
    }

    /**
     * @return list<array{key: string, label: string, url: string, kind?: string, complete?: bool, document_code?: string}>
     */
    private function activityRequirements(Customer $customer): array
    {
        $type = $customer->activity_type ?: $customer->employment_type;
        $validation = app(ProfileValidationService::class);

        $items = [[
            'key' => 'activity_type',
            'label' => __('borrower.profile.activity_type'),
            'url' => route('site.borrower.profile', [
                'section' => 'activity',
                'focus' => 'activity',
                'edit' => 1,
                'field' => 'activity_type',
            ]).'#profile-activity',
            'kind' => 'activity_type',
        ]];

        if (! filled($type)) {
            return $items;
        }

        $items[] = [
            'key' => 'income_range',
            'label' => __('borrower.profile.income_range'),
            'url' => route('site.borrower.profile', [
                'section' => 'activity',
                'focus' => 'activity',
                'edit' => 1,
                'field' => 'income_range',
            ]).'#profile-activity',
            'kind' => 'income_range',
        ];

        $fields = activity_fields_localized()[$type] ?? config('activity_profiles.fields.'.$type, []);
        foreach ($fields as $field) {
            $fieldKey = (string) ($field['key'] ?? '');
            if ($fieldKey === '' || ! ($field['required'] ?? false)) {
                continue;
            }
            if (($field['type'] ?? 'text') === 'document') {
                $items[] = [
                    'key' => $fieldKey,
                    'label' => (string) ($field['label'] ?? $fieldKey),
                    'url' => route('site.borrower.profile', [
                        'section' => 'activity',
                        'focus' => 'activity',
                        'edit' => 1,
                        'field' => $fieldKey,
                    ]).'#profile-activity',
                    'kind' => 'document',
                    'document_code' => $field['document_code'] ?? $fieldKey,
                ];

                continue;
            }
            $items[] = [
                'key' => $fieldKey,
                'label' => (string) ($field['label'] ?? $fieldKey),
                'url' => route('site.borrower.profile', [
                    'section' => 'activity',
                    'focus' => 'activity',
                    'edit' => 1,
                    'field' => $fieldKey,
                ]).'#profile-activity',
                'kind' => 'field',
            ];
        }

        $items = array_merge($items, $this->businessVerificationRequirements($customer));

        if ($validation->requiresEmploymentContract($customer)) {
            $items[] = [
                'key' => 'employment_contract',
                'label' => __('borrower.profile.employment_contract'),
                'url' => route('site.borrower.profile', [
                    'section' => 'activity',
                    'focus' => 'activity',
                    'edit' => 1,
                    'field' => 'employment_contract',
                ]).'#profile-activity',
                'kind' => 'employment_contract',
            ];
        }

        // Income Verification is a separate card — never folded into Activity Information requirements.

        return $items;
    }

    /**
     * Business Owner evidence gated by KYC settings. Stored details are kept if the activity changes.
     *
     * @return list<array{key: string, label: string, url: string, kind?: string, document_code?: string}>
     */
    private function businessVerificationRequirements(Customer $customer): array
    {
        $validation = app(ProfileValidationService::class);
        $url = route('site.borrower.profile', [
            'section' => 'activity',
            'focus' => 'activity',
            'edit' => 1,
        ]).'#profile-business-verification';
        $items = [];

        if ($validation->requiresBusinessTin($customer)) {
            $items[] = [
                'key' => 'tin_number',
                'label' => __('borrower.profile.tin_number'),
                'url' => $url,
                'kind' => 'field',
            ];
            $items[] = [
                'key' => 'tin_certificate',
                'label' => __('borrower.profile.tin_certificate'),
                'url' => $url,
                'kind' => 'document',
                'document_code' => 'tin_certificate',
            ];
        }

        if ($validation->requiresBusinessLicence($customer)) {
            foreach ([
                'licence_number' => __('borrower.profile.licence_number'),
                'licence_authority' => __('borrower.profile.licence_authority'),
                'licence_issued_on' => __('borrower.profile.licence_issued_on'),
            ] as $key => $label) {
                $items[] = [
                    'key' => $key,
                    'label' => $label,
                    'url' => $url,
                    'kind' => 'field',
                ];
            }
            $items[] = [
                'key' => 'business_license',
                'label' => __('borrower.profile.business_license'),
                'url' => $url,
                'kind' => 'document',
                'document_code' => 'business_license',
            ];
        }

        return $items;
    }

    /**
     * Income Verification card requirements (independent of Activity Information).
     *
     * @return list<array{key: string, label: string, url: string, kind?: string, complete?: bool}>
     */
    private function incomeProofRequirements(Customer $customer): array
    {
        $items = [];
        foreach (app(IncomeProofService::class)->requirementItems($customer) as $item) {
            $items[] = [
                'key' => (string) ($item['key'] ?? 'income'),
                'label' => (string) ($item['label'] ?? __('borrower.loan_profile.sections.proof_of_income')),
                'url' => $item['action_url'] ?? route('site.borrower.profile', [
                    'section' => 'activity',
                    'focus' => 'income',
                    'edit' => 1,
                ]).'#profile-income-statement',
                'kind' => 'income_proof',
                'complete' => ! empty($item['complete']),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public function incomeProofGaps(Customer $customer): array
    {
        $gaps = [];
        foreach ($this->incomeProofRequirements($customer) as $req) {
            if (! empty($req['complete'])) {
                continue;
            }
            $gaps[] = [
                'key' => (string) ($req['key'] ?? 'income'),
                'label' => (string) ($req['label'] ?? ''),
                'url' => (string) ($req['url'] ?? ''),
            ];
        }

        return $this->uniqueGaps($gaps);
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function residenceRequirements(Customer $customer): array
    {
        $items = [];
        foreach ([
            'region' => __('borrower.profile.region'),
            'district' => __('borrower.profile.district'),
            'street' => __('borrower.profile.street'),
        ] as $field => $label) {
            $items[] = [
                'key' => $field,
                'label' => $label,
                'url' => route('site.borrower.profile', [
                    'section' => 'residence',
                    'focus' => 'address',
                    'edit' => 1,
                    'field' => $field,
                ]).'#profile-residence-address',
            ];
        }
        foreach ([
            'lga_officer_name' => __('borrower.profile.lga_officer_name'),
            'lga_officer_position' => __('borrower.profile.lga_officer_position'),
            'lga_officer_phone' => __('borrower.profile.lga_officer_phone'),
        ] as $field => $label) {
            $items[] = [
                'key' => $field,
                'label' => $label,
                'url' => route('site.borrower.profile', [
                    'section' => 'residence',
                    'focus' => 'verification',
                    'edit' => 1,
                    'field' => $field,
                ]).'#profile-residence-verification',
            ];
        }

        if (app(ProfileValidationService::class)->requiresResidenceLetter()) {
            $items[] = [
                'key' => 'residence_letter',
                'label' => __('borrower.profile.residence_letter'),
                'url' => route('site.borrower.profile', [
                    'section' => 'residence',
                    'focus' => 'verification',
                    'edit' => 1,
                ]).'#profile-residence-verification',
            ];
        }

        return $items;
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function paymentRequirements(Customer $customer): array
    {
        if (! app(ProfileSectionBuilderService::class)->paymentRequiredBeforeLoan()) {
            return [];
        }

        return [[
            'key' => 'payment',
            'label' => __('borrower.payment_details.section_title'),
            'url' => route('site.borrower.profile', ['section' => 'payment', 'add' => 1]),
        ]];
    }
}
