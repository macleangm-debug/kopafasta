<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;

class ApplicationProgressService
{
    public function __construct(
        private readonly ProfileCompletionService $profileCompletion,
        private readonly ProfileValidationService $profileValidation,
        private readonly IncomeProofService $incomeProof,
        private readonly SmartLoanApplicationWizardService $wizard,
        private readonly NidaVerificationService $nida,
        private readonly FaceVerificationService $face,
    ) {}

    /**
     * Profile/KYC requirements only — shared source of truth with profile completion.
     *
     * @return list<array{key: string, label: string, complete: bool, action_url: string|null}>
     */
    public function profileRequirements(Customer $customer, ?LoanProduct $product = null): array
    {
        return $this->requirements($customer, $product, null);
    }

    /**
     * @return array{
     *     percent: int,
     *     completed: list<string>,
     *     missing: list<string>,
     *     steps: list<array{label: string, complete: bool, key?: string}>,
     *     profile_incomplete: bool,
     *     docs_incomplete: bool
     * }
     */
    public function profileProgress(Customer $customer, ?LoanProduct $product = null): array
    {
        $requirements = $this->profileRequirements($customer, $product);
        $completed = collect($requirements)->where('complete', true);
        $missing = collect($requirements)->where('complete', false);
        $total = max(1, count($requirements));

        $profileKeys = ['personal', 'kin', 'residence', 'activity', 'income', 'nida_docs', 'residence_letter', 'face'];
        $docKeys = ['income', 'nida_docs', 'residence_letter', 'employment_contract', 'salary_slip', 'bank_statement', 'mobile_money_statement'];

        return [
            'percent'            => (int) round(($completed->count() / $total) * 100),
            'completed'          => $completed->pluck('label')->values()->all(),
            'missing'            => $missing->pluck('label')->values()->all(),
            'steps'              => collect($requirements)->map(fn (array $item) => [
                'label'    => $item['label'],
                'complete' => (bool) $item['complete'],
                'key'      => $item['key'] ?? null,
            ])->values()->all(),
            'profile_incomplete' => $missing->contains(fn (array $item) => in_array($item['key'] ?? '', $profileKeys, true)),
            'docs_incomplete'    => $missing->contains(fn (array $item) => in_array($item['key'] ?? '', $docKeys, true)),
        ];
    }

    /**
     * Wizard-only steps for draft timeline display.
     *
     * @return list<array{label: string, complete: bool, key: string|null}>
     */
    public function wizardTimeline(Customer $customer, LoanApplicationDraft $draft, ?LoanProduct $product): array
    {
        if (! $product) {
            return [];
        }

        $payload = $draft->payload ?? [];
        if ($draft->phase !== 'application' && empty($payload['application_started'])) {
            return [];
        }

        $wizardSteps = collect($this->wizard->borrowerStepPlan($customer, $product))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values();

        $stepKey = $payload['step_key'] ?? null;
        $currentIndex = $this->resolveWizardStepIndex($wizardSteps, $stepKey, (int) $draft->step);

        return $wizardSteps->map(fn (array $step, int $index) => [
            'label'    => (string) $step['label'],
            'complete' => $index < $currentIndex,
            'key'      => $step['key'] ?? null,
        ])->values()->all();
    }

    /**
     * @return array{
     *     percent: int,
     *     completed: list<string>,
     *     missing: list<string>,
     *     steps: list<array{label: string, complete: bool, key?: string}>,
     *     profile_incomplete: bool,
     *     docs_incomplete: bool
     * }
     */
    public function draftProgress(Customer $customer, LoanApplicationDraft $draft, ?LoanProduct $product): array
    {
        return $this->profileProgress($customer, $product);
    }

    /**
     * @return list<array{key: string, label: string, complete: bool, action_url: string|null}>
     */
    public function requirements(Customer $customer, ?LoanProduct $product, ?LoanApplicationDraft $draft = null): array
    {
        $items = [];

        $items[] = [
            'key'        => 'personal',
            'label'      => __('borrower.loan_profile.sections.personal'),
            'complete'   => $this->profileValidation->isPersonalInfoComplete($customer),
            'action_url' => route('site.borrower.profile', ['section' => 'personal']),
        ];

        $items[] = [
            'key'        => 'nida_docs',
            'label'      => __('borrower.profile.nida_front'),
            'complete'   => $this->profileValidation->nationalIdUploadsComplete($customer),
            'action_url' => route('site.borrower.profile', ['section' => 'personal']),
        ];

        $items[] = [
            'key'        => 'face',
            'label'      => __('borrower.nida.face_title'),
            'complete'   => $this->face->canApply($customer),
            'action_url' => route('site.borrower.face-verification'),
        ];

        $items[] = [
            'key'        => 'kin',
            'label'      => __('borrower.loan_profile.sections.kin'),
            'complete'   => $this->profileValidation->isKinComplete($customer),
            'action_url' => route('site.borrower.profile', ['section' => 'personal']).'#next-of-kin',
        ];

        $items[] = [
            'key'        => 'residence',
            'label'      => __('borrower.loan_profile.sections.residence'),
            'complete'   => $this->profileCompletion->isResidenceComplete($customer),
            'action_url' => route('site.borrower.profile', ['section' => 'residence']),
        ];

        if ($this->profileValidation->requiresResidenceLetter()) {
            $items[] = [
                'key'        => 'residence_letter',
                'label'      => __('borrower.profile.residence_letter'),
                'complete'   => $this->profileValidation->hasResidenceLetter($customer),
                'action_url' => route('site.borrower.profile', ['section' => 'residence']),
            ];
        }

        $items[] = [
            'key'        => 'activity',
            'label'      => __('borrower.loan_profile.sections.employment'),
            'complete'   => $this->profileCompletion->isActivityComplete($customer),
            'action_url' => route('site.borrower.profile', ['section' => 'activity']),
        ];

        foreach ($this->incomeProof->requirementItems($customer) as $incomeItem) {
            $items[] = $incomeItem;
        }

        if ($draft && $product) {
            $wizardSteps = collect($this->wizard->borrowerStepPlan($customer, $product))
                ->reject(fn (array $step) => $step['key'] === 'product')
                ->values();

            $payload = $draft->payload ?? [];
            $stepKey = $payload['step_key'] ?? null;
            $currentIndex = $this->resolveWizardStepIndex($wizardSteps, $stepKey, (int) $draft->step);

            if ($draft->phase === 'application' || ! empty($payload['application_started'])) {
                foreach ($wizardSteps as $index => $step) {
                    $items[] = [
                        'key'        => 'wizard_'.$step['key'],
                        'label'      => (string) $step['label'],
                        'complete'   => $index < $currentIndex,
                        'action_url' => null,
                    ];
                }
            }
        }

        return $items;
    }

    /** @param  \Illuminate\Support\Collection<int, array{key: string, label: string}>  $wizardSteps */
    private function resolveWizardStepIndex(\Illuminate\Support\Collection $wizardSteps, ?string $stepKey, int $fallbackIndex): int
    {
        if ($stepKey) {
            $byKey = $wizardSteps->search(fn (array $step) => $step['key'] === $stepKey);
            if ($byKey !== false) {
                return (int) $byKey;
            }
        }

        return max(0, min($fallbackIndex, max(0, $wizardSteps->count() - 1)));
    }
}
