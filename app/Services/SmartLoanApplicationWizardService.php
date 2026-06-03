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
        $doc = CustomerDocument::with('documentType')
            ->where('customer_id', $customer->id)
            ->whereHas('documentType', function ($q) {
                $q->where(function ($q) {
                    $q->where('name', 'like', '%bank%')
                        ->orWhere('name', 'like', '%income%')
                        ->orWhere('name', 'like', '%statement%')
                        ->orWhere('name', 'like', '%mobile%')
                        ->orWhere('code', 'like', '%bank%')
                        ->orWhere('code', 'like', '%income%')
                        ->orWhere('code', 'like', '%statement%');
                });
            })
            ->latest()
            ->first();

        if (! $doc) {
            if ($this->profile->isActivityComplete($customer)) {
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

        $status = in_array($doc->status, ['verified', 'approved'], true) ? 'approved' : $doc->status;

        return [
            'has_document' => true,
            'status'       => $status,
            'label'        => $doc->documentType?->name,
            'can_skip'     => in_array($status, ['approved', 'verified', 'pending'], true),
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
    public function borrowerStepPlan(Customer $customer, ?LoanProduct $product = null): array
    {
        $sections = collect($this->profileSections($customer))->keyBy('key');
        $requiresGuarantor = (bool) ($product?->requires_guarantor ?? false);
        $productCode = $product?->code;
        $hasProductQuestions = $productCode && ! empty(config('loan_product_questions.'.$productCode));

        $profileKeys = ['personal', 'residence', 'kin', 'activity'];
        $isAssetLending = is_marketplace_loan_product($productCode);

        $steps = [
            ['key' => 'product', 'label' => __('borrower.apply.steps.product'), 'skippable' => false, 'skipped' => false],
        ];

        if (! $isAssetLending) {
            $steps[] = ['key' => 'quote', 'label' => __('borrower.apply.steps.quote'), 'skippable' => false, 'skipped' => false];
        } else {
            $steps[] = ['key' => 'asset_tenure', 'label' => __('borrower.apply.steps.asset_tenure'), 'skippable' => false, 'skipped' => false];
        }

        // Profile/KYC/income are completed in Profile — never duplicated in the apply wizard.

        if ($requiresGuarantor) {
            $steps[] = ['key' => 'guarantor', 'label' => __('borrower.apply.steps.guarantor'), 'skippable' => false, 'skipped' => false];
        }

        $applicationFee = quoted_application_fee($customer, $product);
        if ($applicationFee > 0) {
            $steps[] = ['key' => 'application_fee', 'label' => __('borrower.apply.steps.application_fee'), 'skippable' => false, 'skipped' => false];
        }

        if ($hasProductQuestions) {
            $steps[] = ['key' => 'product_questions', 'label' => __('borrower.apply.steps.product_questions'), 'skippable' => false, 'skipped' => false];
        }

        $steps[] = ['key' => 'review', 'label' => __('borrower.apply.steps.review'), 'skippable' => false, 'skipped' => false];
        $steps[] = ['key' => 'signature', 'label' => __('borrower.apply.steps.signature'), 'skippable' => false, 'skipped' => false];

        return $steps;
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
        $stages = [
            'submitted'           => 'Submitted',
            'screening'           => 'Document review',
            'credit_appraisal'    => 'CRB review',
            'pre_approval'        => 'Loan officer review',
            'approval'            => 'Approval / rejection',
            'awaiting_guarantor'  => 'Guarantor approval',
            'post_approval_fees'  => 'Post-approval fees',
            'contract_generation' => 'Contract generation',
            'disbursement'        => 'Disbursement',
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
