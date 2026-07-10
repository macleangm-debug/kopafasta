<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\LoanProduct;

class SmartLoanApplicationWizardService
{
    public function __construct(
        private readonly ApplicationRequirementsService $requirements,
        private readonly ProfileCompletionService $profile,
        private readonly AffordabilityService $affordability,
    ) {}

    /** @return array{can_apply: bool, completion_percent: int, items: list<array<string, mixed>>} */
    public function eligibilityForCustomer(Customer $customer): array
    {
        return $this->requirements->checklist($customer);
    }

    /** @return list<array{key: string, label: string, complete: bool, detail: string, action_url: string|null}> */
    public function profileSections(Customer $customer): array
    {
        $result = $this->profile->calculate($customer);

        $urls = [
            'personal'  => route('site.borrower.profile', ['section' => 'personal']),
            'nida'      => route('site.borrower.profile', ['section' => 'personal']),
            'face'      => route('site.borrower.face-verification'),
            'activity'  => route('site.borrower.profile', ['section' => 'activity']),
            'residence' => route('site.borrower.profile', ['section' => 'residence']),
            'kin'       => route('site.borrower.profile', ['section' => 'personal']).'#next-of-kin',
        ];

        return collect($result['sections'])->map(fn (array $section) => [
            'key'        => $section['key'],
            'label'      => $section['label'],
            'complete'   => (bool) $section['complete'],
            'detail'     => $section['complete']
                ? __('borrower.apply.readiness.profile_verified')
                : __('borrower.apply.readiness.profile_incomplete'),
            'action_url' => ($section['complete'] ?? false) ? null : ($urls[$section['key']] ?? null),
        ])->values()->all();
    }

    /** @return array{has_document: bool, status: string|null, label: string|null, can_skip: bool} */
    public function incomeVerification(Customer $customer): array
    {
        $incomeProof = app(IncomeProofService::class);
        $doc = $incomeProof->primaryDocument($customer);

        if (! $doc && $incomeProof->isEmployed($customer)) {
            foreach (config('income_proof.employed_required_codes', []) as $code) {
                $found = CustomerDocument::with('documentType')
                    ->where('customer_id', $customer->id)
                    ->whereHas('documentType', fn ($q) => $q->where('code', $code))
                    ->latest()
                    ->first();
                if ($found) {
                    $doc = $found;
                    break;
                }
            }
        }

        if (! $doc) {
            if (! $incomeProof->isRequired()) {
                return [
                    'has_document' => false,
                    'status'       => null,
                    'label'        => null,
                    'can_skip'     => true,
                ];
            }

            return [
                'has_document' => false,
                'status'       => null,
                'label'        => null,
                'can_skip'     => false,
            ];
        }

        if ($incomeProof->isRequired() && ! $incomeProof->satisfiesRequirement($customer)) {
            return [
                'has_document' => true,
                'status'       => $doc->status,
                'label'        => $doc->documentType?->name,
                'can_skip'     => false,
            ];
        }

        $status = in_array($doc->status, ['verified', 'approved'], true) ? 'approved' : $doc->status;

        return [
            'has_document' => true,
            'status'       => $status,
            'label'        => $doc->documentType?->name,
            'can_skip'     => in_array($status, ['approved', 'verified', 'pending', 'pending_review'], true),
        ];
    }

    /** @return array{monthly_installment: float, weekly_installment: float, interest_total: float, fees: float, total_repayment: float} */
    public function loanQuote(LoanProduct $product, float $amount, int $tenureMonths): array
    {
        $rate = app(DisplayedRateService::class)->displayedMonthlyRate($product, $amount);
        $emi = $this->estimateEmi($amount, $rate, $tenureMonths);
        $interestTotal = max(0, ($emi * $tenureMonths) - $amount);
        $fees = quoted_application_fee(null, $product);

        return [
            'monthly_installment'  => $emi,
            'weekly_installment'   => round($emi / 4.33, 2),
            'interest_total'       => round($interestTotal, 2),
            'fees'                 => $fees,
            'total_repayment'      => round(($emi * $tenureMonths) + $fees, 2),
        ];
    }

    /**
     * Borrower wizard step plan — skips sections already complete on profile.
     *
     * @return list<array{key: string, label: string, skippable: bool, skipped: bool}>
     */
    public function borrowerStepPlan(Customer $customer, ?LoanProduct $product = null, float $requestedAmount = 0): array
    {
        $sections = collect($this->profileSections($customer))->keyBy('key');
        $policy = app(LoanPolicyService::class);
        $amount = $requestedAmount > 0 ? $requestedAmount : (float) ($product?->min_amount ?? 0);
        $requiresGuarantor = $product && $policy->requiresGuarantorForApplication($product, $amount);
        $productCode = $product?->code;
        $hasProductQuestions = $productCode && ! empty(config('loan_product_questions.'.$productCode));

        $profileKeys = ['personal', 'residence', 'kin', 'activity'];
        $isAssetLending = is_marketplace_loan_product($productCode);
        $isAssetBacked = $productCode && strtoupper((string) $productCode) === 'AB';
        $isGroupLending = $product && is_group_loan_product($product);

        $steps = [];

        if ($isGroupLending) {
            $steps[] = ['key' => 'group_setup', 'label' => __('borrower.apply.steps.group_setup'), 'skippable' => false, 'skipped' => false];
            $steps[] = ['key' => 'group_members', 'label' => __('borrower.apply.steps.group_members'), 'skippable' => false, 'skipped' => false];
        } elseif ($isAssetBacked) {
            $steps[] = ['key' => 'asset_details', 'label' => __('borrower.apply.steps.asset_details'), 'skippable' => false, 'skipped' => false];
        } elseif (! $isAssetLending) {
            $steps[] = ['key' => 'quote', 'label' => __('borrower.apply.steps.quote'), 'skippable' => false, 'skipped' => false];
        } else {
            $steps[] = ['key' => 'asset_tenure', 'label' => __('borrower.apply.steps.asset_tenure'), 'skippable' => false, 'skipped' => false];
        }

        // Profile/KYC/income are completed in Profile — never duplicated in the apply wizard.

        // Application fee immediately after quote (same gateway as registration fee).
        $steps[] = ['key' => 'application_fee', 'label' => __('borrower.apply.steps.application_fee'), 'skippable' => false, 'skipped' => false];

        if ($requiresGuarantor) {
            $steps[] = ['key' => 'guarantor', 'label' => __('borrower.apply.steps.guarantor'), 'skippable' => false, 'skipped' => false];
        }

        if ($hasProductQuestions) {
            $steps[] = ['key' => 'product_questions', 'label' => __('borrower.apply.steps.product_questions'), 'skippable' => false, 'skipped' => false];
        }

        $steps[] = ['key' => 'review', 'label' => __('borrower.apply.steps.review'), 'skippable' => false, 'skipped' => false];
        $steps[] = ['key' => 'signature', 'label' => __('borrower.apply.steps.signature'), 'skippable' => false, 'skipped' => false];
        $steps[] = ['key' => 'submit', 'label' => __('borrower.apply.steps.submit'), 'skippable' => false, 'skipped' => false];

        return $steps;
    }

    /**
     * Short plan used when underwriting asks the borrower to add another guarantor.
     *
     * @return list<array{key: string, label: string, skippable: bool, skipped: bool}>
     */
    public function guarantorSupplementStepPlan(): array
    {
        return [
            ['key' => 'guarantor', 'label' => __('borrower.apply.steps.guarantor'), 'skippable' => false, 'skipped' => false],
            ['key' => 'submit', 'label' => __('borrower.apply.steps.submit'), 'skippable' => false, 'skipped' => false],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    public function adminStepLabels(): array
    {
        return [
            ['key' => 'applicant', 'label' => 'Applicant'],
            ['key' => 'product', 'label' => 'Product & quote'],
            ['key' => 'profile', 'label' => 'Profile check'],
            ['key' => 'details', 'label' => 'Application'],
            ['key' => 'review', 'label' => 'Review'],
        ];
    }

    /** @return list<array{key: string, label: string, status: string}> */
    public function underwritingStages(?string $currentStage = 'submitted'): array
    {
        $labels = app(LoanApplicationWorkflowService::class);

        $stages = [
            'submitted'           => $labels->stageLabel('submitted'),
            'awaiting_guarantor'  => $labels->stageLabel('awaiting_guarantor'),
            'screening'           => $labels->stageLabel('screening'),
            'credit_appraisal'    => $labels->stageLabel('credit_appraisal'),
            'pre_approval'        => $labels->stageLabel('pre_approval'),
            'approval'            => $labels->stageLabel('approval'),
            'post_approval_fees'  => $labels->stageLabel('post_approval_fees'),
            'contract_generation' => $labels->stageLabel('contract_generation'),
            'disbursement'        => $labels->stageLabel('disbursement'),
        ];

        $order = array_keys($stages);
        $currentIndex = array_search($currentStage, $order, true);
        if ($currentStage === 'rejected') {
            $currentIndex = false;
        }

        return collect($stages)->map(function (string $label, string $key) use ($order, $currentIndex) {
            $index = array_search($key, $order, true);
            $status = 'upcoming';
            if ($currentIndex !== false) {
                if ($index < $currentIndex) {
                    $status = 'done';
                } elseif ($index === $currentIndex) {
                    $status = 'active';
                }
            }

            return ['key' => $key, 'label' => $label, 'status' => $status];
        })->values()->all();
    }

    public function estimateEmi(float $principal, float $monthlyRate, int $months): float
    {
        if ($principal <= 0 || $months <= 0) {
            return 0.0;
        }
        if ($monthlyRate <= 0) {
            return round($principal / $months, 2);
        }
        $pow = (1 + $monthlyRate) ** $months;

        return round($principal * $monthlyRate * $pow / ($pow - 1), 2);
    }
}
