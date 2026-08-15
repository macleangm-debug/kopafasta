<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\Lang;

class LoanProductReadinessService
{
    public function __construct(
        private readonly SmartLoanApplicationWizardService $wizard,
    ) {}

    /** @return array<string, mixed> */
    public function assess(Customer $customer, LoanProduct $product): array
    {
        $product->loadMissing('requirements');

        $profileSections = collect($this->wizard->profileSections($customer))->keyBy('key');
        $income = $this->wizard->incomeVerification($customer);
        $incomeProof = app(IncomeProofService::class);
        $profileValidation = app(ProfileValidationService::class);
        $nida = app(NidaVerificationService::class);
        $face = app(FaceVerificationService::class);

        $requirements = $this->requirementChecks($customer, $product, $profileSections, $income, $incomeProof, $profileValidation, $nida, $face);
        $completed = collect($requirements)->where('complete', true)->count();
        $total = max(1, count($requirements));
        $percent = (int) round(($completed / $total) * 100);

        $missing = collect($requirements)->where('complete', false)->values()->all();
        $firstMissingUrl = collect($missing)
            ->first(fn (array $item) => ! empty($item['action_url']) && empty($item['application_step']))['action_url'] ?? null;

        $groupLending = app(GroupLendingService::class);
        $applicationFee = $groupLending->isGroupProduct($product)
            ? $groupLending->quotedApplicationFee($customer, $product, $groupLending->memberLimits()['min'])
            : quoted_application_fee($customer, $product);
        $postApprovalSummary = $this->postApprovalFeeSummary($product);
        $displayedRate = app(DisplayedRateService::class);

        $readinessEmoji = match (true) {
            $percent >= 90 => '🟢',
            $percent >= 60 => '🟡',
            default         => '🔴',
        };

        $profilePercent = (int) (app(ProfileCompletionService::class)->calculate($customer)['percent'] ?? $percent);

        return [
            'product' => [
                'id'                 => $product->id,
                'code'               => $product->code,
                'name'               => $product->localizedName(),
                'loan_type'          => $this->loanTypeLabel($product),
                'description'        => $product->description,
                'features'           => $this->productFeatures($product),
                'min_amount'         => (float) $product->min_amount,
                'max_amount'         => (float) $product->max_amount,
                'tenure_min_months'  => (int) $product->tenure_min_months,
                'tenure_max_months'  => (int) $product->tenure_max_months,
                'interest_rate'      => (float) $product->interest_rate,
                'displayed_monthly_rate' => $displayedRate->displayedMonthlyRate($product),
                'displayed_monthly_rate_label' => $displayedRate->formatBorrowerRateRange($product),
                'requires_guarantor' => (bool) $product->requires_guarantor,
                'repayment_frequency'=> app(\App\Services\GroupLendingService::class)->effectiveRepaymentCadence($product),
            ],
            'profile_percent'    => $profilePercent,
            'readiness_percent'  => $percent,
            'readiness_level'    => $percent >= 90 ? 'green' : ($percent >= 60 ? 'amber' : 'red'),
            'readiness_label'    => $readinessEmoji.' '.__('borrower.apply.readiness.score', ['percent' => $percent]),
            'requirements'       => $requirements,
            'missing'            => $missing,
            'missing_titles'     => collect($missing)->pluck('label')->values()->all(),
            'missing_action_url' => $firstMissingUrl,
            'documents'          => $this->documentChecklist($customer, $product),
            'fees'               => [
                'application'          => $applicationFee,
                'application_label'    => __('borrower.apply.readiness.fees.application'),
                'post_approval'        => $postApprovalSummary['total'],
                'post_approval_label'  => __('borrower.apply.readiness.fees.post_approval'),
                'post_approval_detail' => $postApprovalSummary['detail'],
                'post_approval_lines' => $postApprovalSummary['lines'],
            ],
            'product_specific'   => $this->localizedProductSpecific($product->code),
            'processing_time'    => $this->localizedProcessingTime($product->code),
            'step_plan'          => collect($this->wizard->borrowerStepPlan($customer, $product))
                ->reject(fn (array $step) => $step['key'] === 'product')
                ->values()
                ->all(),
        ];
    }

    /** @return array{total: float, detail: string, lines: list<array{name: string, amount: float}>} */
    private function postApprovalFeeSummary(LoanProduct $product): array
    {
        $product->loadMissing('postApprovalFees');
        $fees = $product->postApprovalFees()->where('is_active', true)->orderBy('sort_order')->get();
        $principal = (float) $product->min_amount;
        $postApproval = app(PostApprovalFeeService::class);

        $lines = [];
        $total = 0.0;
        foreach ($fees as $fee) {
            $amount = $postApproval->calculateAmount($fee, $principal);
            $total += $amount;
            $lines[] = ['name' => $fee->name, 'amount' => $amount];
        }

        if ($lines === []) {
            $catalog = app(FeeCatalogService::class)->postApprovalFees();
            foreach ($catalog as $fee) {
                $lines[] = [
                    'name'   => $fee->name,
                    'amount' => $fee->basis === 'percentage'
                        ? round($principal * ((float) $fee->amount / 100), 2)
                        : (float) $fee->amount,
                ];
            }
            $total = collect($lines)->sum('amount');
        }

        $detail = $lines === []
            ? __('borrower.apply.readiness.fees.post_approval_detail')
            : collect($lines)->map(fn (array $l) => $l['name'].' (from '.format_money($principal).')')->join(' · ');

        return [
            'total'  => round($total, 2),
            'detail' => $detail,
            'lines'  => $lines,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function requirementChecks(
        Customer $customer,
        LoanProduct $product,
        \Illuminate\Support\Collection $profileSections,
        array $income,
        IncomeProofService $incomeProof,
        ProfileValidationService $profileValidation,
        NidaVerificationService $nida,
        FaceVerificationService $face,
    ): array {
        $isGroup = strtoupper($product->code) === 'GL' || ($product->category ?? '') === 'group';
        $membershipActive = $customer->isMembershipActive() || $customer->isMembershipInGrace();
        $kinComplete = (bool) ($profileSections['kin']['complete'] ?? false);
        $incomeComplete = $incomeProof->satisfiesRequirement($customer);

        $checks = [
            [
                'key'        => 'membership',
                'label'      => __('borrower.apply.readiness.requirements.membership.label'),
                'complete'   => $membershipActive,
                'detail'     => $membershipActive
                    ? __('borrower.apply.readiness.requirements.membership.valid')
                    : __('borrower.apply.readiness.requirements.membership.renew'),
                'action_url' => $membershipActive ? null : route('site.membership.renew'),
            ],
        ];

        $identityPolicy = app(IdentityVerificationPolicyService::class);
        if ($identityPolicy->requiredDuringProfileCreation() && $identityPolicy->nidaRequired()) {
            $checks[] = [
                'key'        => 'nida',
                'label'      => __('borrower.apply.readiness.requirements.nida.label'),
                'complete'   => $nida->isVerified($customer),
                'detail'     => $nida->isVerified($customer)
                    ? __('borrower.apply.readiness.requirements.nida.confirmed')
                    : __('borrower.apply.readiness.requirements.nida.pending'),
                'action_url' => $nida->isVerified($customer) ? null : route('site.borrower.profile', ['section' => 'personal']),
            ];
        }
        if ($identityPolicy->requiredDuringProfileCreation() && $identityPolicy->facialRequired()) {
            $checks[] = [
                'key'        => 'face',
                'label'      => __('borrower.apply.readiness.requirements.face.label'),
                'complete'   => $face->canApply($customer),
                'detail'     => match ($customer->face_verification_status) {
                    'verified' => __('borrower.apply.readiness.requirements.face.verified'),
                    'pending'  => __('borrower.apply.readiness.requirements.face.pending'),
                    'rejected' => __('borrower.apply.readiness.requirements.face.rejected'),
                    default    => __('borrower.apply.readiness.requirements.face.incomplete'),
                },
                'action_url' => $face->canApply($customer) ? null : route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']).'#profile-face',
            ];
        }

        $checks = array_merge($checks, [
            [
                'key'        => 'kin',
                'label'      => __('borrower.apply.readiness.requirements.kin.label'),
                'complete'   => $kinComplete,
                'detail'     => $kinComplete
                    ? __('borrower.apply.readiness.requirements.kin.on_file')
                    : __('borrower.apply.readiness.requirements.kin.complete_profile'),
                'action_url' => $kinComplete ? null : route('site.borrower.profile', ['section' => 'personal', 'focus' => 'kin']),
            ],
            [
                'key'        => 'income',
                'label'      => __('borrower.loan_profile.sections.proof_of_income'),
                'complete'   => $incomeComplete,
                'detail'     => $incomeComplete
                    ? __('borrower.apply.readiness.on_file', [
                        'item' => $income['label'] ?? __('borrower.apply.income.income_document'),
                    ])
                    : __('borrower.apply.readiness.requirements.income.upload'),
                'action_url' => $incomeComplete ? null : route('site.borrower.profile', ['section' => 'kyc']),
            ],
        ]);

        if (strtoupper((string) $product->code) === 'FC') {
            $activityComplete = (bool) ($profileSections['activity']['complete'] ?? false);
            $isArtisan = ($customer->activity_type ?? '') === 'artisan' && $activityComplete;
            $checks[] = [
                'key'        => 'artisan_activity',
                'label'      => __('borrower.apply.readiness.requirements.artisan_activity.label'),
                'complete'   => $isArtisan,
                'detail'     => $isArtisan
                    ? __('borrower.apply.readiness.requirements.artisan_activity.on_file')
                    : __('borrower.apply.readiness.requirements.artisan_activity.complete_profile'),
                'action_url' => $isArtisan ? null : route('site.borrower.profile', ['section' => 'activity']),
            ];
        }

        if ($profileValidation->requiresResidenceLetter()) {
            $hasLetter = $profileValidation->hasResidenceLetter($customer);
            $checks[] = [
                'key'        => 'residence_letter',
                'label'      => __('borrower.profile.residence_letter'),
                'complete'   => $hasLetter,
                'detail'     => $hasLetter
                    ? __('borrower.apply.readiness.on_file', ['item' => __('borrower.profile.residence_letter')])
                    : __('borrower.loan_profile.residence_letter_missing'),
                'action_url' => $hasLetter ? null : route('site.borrower.profile', ['section' => 'residence']),
            ];
        }

        if ($product->requires_guarantor && ! $isGroup) {
            $checks[] = [
                'key'        => 'guarantor',
                'label'      => __('borrower.apply.readiness.requirements.guarantor.label'),
                'complete'   => false,
                'detail'     => __('borrower.apply.readiness.requirements.guarantor.during_application'),
                'action_url' => null,
                'application_step' => true,
            ];
        }

        if ($isGroup) {
            $checks[] = [
                'key'        => 'group',
                'label'      => __('borrower.apply.readiness.requirements.group.label'),
                'complete'   => false,
                'detail'     => __('borrower.apply.readiness.requirements.group.during_application'),
                'action_url' => null,
                'application_step' => true,
            ];
        }

        return $checks;
    }

    /** @return list<array{label: string, detail: string}> */
    private function localizedProductSpecific(string $code): array
    {
        $key = 'borrower.apply.readiness.specific.'.$code;

        if (Lang::has($key)) {
            return Lang::get($key);
        }

        return config('loan_product_apply.specific.'.$code, []);
    }

    private function localizedProcessingTime(string $code): string
    {
        $key = 'borrower.apply.readiness.processing_time.'.$code;

        if (Lang::has($key)) {
            return __($key);
        }

        return __('borrower.apply.readiness.processing_time.default');
    }

    /** @return list<array<string, mixed>> */
    private function documentChecklist(Customer $customer, LoanProduct $product): array
    {
        $uploads = CustomerDocument::with('documentType')
            ->where('customer_id', $customer->id)
            ->latest()
            ->get();

        return $product->requirements
            ->where('is_required', true)
            ->map(function ($req) use ($uploads) {
                $reqName = strtolower($req->name);
                $matched = $uploads->first(function (CustomerDocument $doc) use ($reqName) {
                    $code = strtolower($doc->documentType?->code ?? '');
                    $name = strtolower($doc->documentType?->name ?? '');

                    if (str_contains($reqName, 'income') || str_contains($reqName, 'bank') || str_contains($reqName, 'statement')) {
                        return str_contains($name, 'income')
                            || str_contains($name, 'bank')
                            || str_contains($name, 'statement')
                            || str_contains($name, 'mobile')
                            || str_contains($code, 'bank')
                            || str_contains($code, 'statement')
                            || str_contains($code, 'income');
                    }

                    if (str_contains($reqName, 'residence') || str_contains($reqName, 'address')) {
                        return str_contains($code, 'residence') || str_contains($name, 'residence');
                    }

                    $reqWords = preg_split('/\s+/', $reqName) ?: [];
                    $docWords = preg_split('/\s+/', $name) ?: [];

                    return collect($reqWords)->contains(fn (string $word) => strlen($word) > 2 && in_array($word, $docWords, true));
                });

                return [
                    'name'     => $req->name,
                    'detail'   => $req->description,
                    'complete' => (bool) $matched,
                    'status'   => $matched?->status,
                ];
            })
            ->values()
            ->all();
    }

    private function loanTypeLabel(LoanProduct $product): string
    {
        $category = (string) ($product->category ?? '');

        return match ($category) {
            'salary_loan'    => __('borrower.apply.product_type.salary'),
            'business_loan'  => __('borrower.apply.product_type.business'),
            'agriculture'    => __('borrower.apply.product_type.agriculture'),
            'asset_finance'  => __('borrower.apply.product_type.asset'),
            'emergency'      => __('borrower.apply.product_type.emergency'),
            default          => ucfirst(str_replace('_', ' ', $category ?: __('borrower.apply.product_type.general'))),
        };
    }

    /** @return list<string> */
    private function productFeatures(LoanProduct $product): array
    {
        $features = [];

        if ($product->description) {
            $features[] = $product->description;
        }

        if ($product->requires_collateral) {
            $features[] = __('borrower.apply.product_features.collateral');
        }

        return $features;
    }
}
