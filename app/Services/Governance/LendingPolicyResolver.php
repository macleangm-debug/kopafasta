<?php

namespace App\Services\Governance;

use App\Models\LendingPolicyVersion;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Services\DisplayedRateService;
use App\Services\PublicProductPresentationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class LendingPolicyResolver
{
    public function __construct(
        private readonly DisplayedRateService $rates,
        private readonly PublicProductPresentationService $products,
    ) {}

    /**
     * Resolve live lending policy structure from Settings + products.
     *
     * @return array{document: array<string, mixed>, sections: array<string, mixed>, products: list<array<string, mixed>>, warnings: list<string>, fingerprint: string, public: array<string, mixed>}
     */
    public function resolve(): array
    {
        $company = Setting::group('company');
        $legal = Setting::group('legal');
        $credit = Setting::group('credit_policy');
        $underwriting = Setting::group('underwriting');
        $loanRules = Setting::group('loan_rules');
        $kyc = Setting::group('kyc');
        $aml = Setting::group('aml');

        $products = LoanProduct::query()
            ->with(['rateTiers', 'postApprovalFees', 'requirements'])
            ->whereIn('status', ['active', 'coming_soon'])
            ->orderBy('id')
            ->get()
            ->map(fn (LoanProduct $product) => $this->productBlock($product))
            ->values()
            ->all();

        $warnings = [];
        if ($products === []) {
            $warnings[] = 'No active loan products are configured.';
        }
        foreach ($products as $product) {
            foreach ($product['fees'] as $fee) {
                if (($fee['basis'] ?? '') === 'percent' && empty($fee['display'])) {
                    $warnings[] = "Product {$product['code']} has a percentage fee without a clear display basis.";
                }
            }
        }
        if (filled($loanRules['late_fee_enabled'] ?? null) || filled($loanRules['penalty_rate'] ?? null)) {
            $warnings[] = 'Late-payment charges appear configured — confirm regulatory approval/status before treating as legally permitted.';
        }

        $document = [
            'title' => 'Lending Policy',
            'entity' => $company['legal_name'] ?? $company['name'] ?? brand('legal_name'),
            'version' => (string) (Setting::get('governance.lending_policy_version') ?: 'DRAFT'),
            'status' => (string) (Setting::get('governance.lending_policy_status') ?: 'draft'),
            'effective_date' => Setting::get('governance.lending_policy_effective_at'),
            'approval_date' => Setting::get('governance.lending_policy_approved_at'),
            'approved_by' => Setting::get('governance.lending_policy_approved_by'),
            'next_review_date' => Setting::get('governance.lending_policy_next_review_at'),
            'jurisdiction' => $legal['jurisdiction'] ?? 'United Republic of Tanzania',
            'resolved_at' => now()->toIso8601String(),
            'bot_approval_claim' => false,
        ];

        $sections = [
            'introduction' => [
                'purpose' => 'This Lending Policy sets out how '.($document['entity']).' originates, assesses, prices, contracts, disburses and services credit.',
                'principles' => ['responsible lending', 'transparency', 'affordability', 'fair treatment', 'confidentiality', 'suitability'],
            ],
            'regulatory_framework' => [
                'references' => Setting::get('governance.regulatory_references') ?: [
                    ['name' => 'Microfinance Act, 2018', 'status' => 'configured', 'note' => 'Maintain effective dates in Settings.'],
                    ['name' => 'Non-Deposit Taking Microfinance Service Provider Regulations, 2019', 'status' => 'configured'],
                    ['name' => 'Financial consumer protection requirements', 'status' => 'configured'],
                ],
                'note' => 'Regulatory references are maintainable configuration — not a claim of Bank of Tanzania approval of this generated document.',
            ],
            'scope' => [
                'customers' => 'Borrowers and guarantors using Kopafasta digital lending channels.',
                'geography' => $document['jurisdiction'],
                'products' => collect($products)->pluck('code')->all(),
            ],
            'credit_assessment' => [
                'stages' => ['declared_affordability', 'verified_affordability', 'security_collateral', 'crb_credit_history', 'identity_people_residence', 'exceptions_final_review'],
                'outcomes' => ['HARD_FAIL', 'WAITING_RESOLVABLE', 'REFER_REVIEW'],
                'source' => array_filter([
                    'credit_policy' => $credit,
                    'underwriting' => $underwriting,
                ]),
            ],
            'kyc' => $kyc,
            'aml' => $aml,
            'loan_rules' => $loanRules,
            'approval' => [
                'flow' => ['Screening', 'Decision', 'Committee', 'Offer'],
                'offer_validity_days' => $legal['offer_validity_days'] ?? null,
            ],
            'disbursement' => [
                'states' => ['Queued', 'Processing', 'Released', 'Failed', 'Reversed'],
                'invariant' => 'Only Released activates the loan.',
            ],
            'recovery' => [
                'stages' => ['Call Centre', 'Debt Collection', 'Repossession', 'Auction Preparation', 'Auction'],
                'note' => 'Operational tactics remain internal; this section describes governed escalation only.',
            ],
            'complaints' => [
                'phone' => $company['complaints_phone'] ?? $company['phone'] ?? null,
                'email' => $company['complaints_email'] ?? $company['support_email'] ?? $company['email'] ?? null,
            ],
            'governance' => [
                'owner' => 'Management / Credit Governance',
                'change_control' => 'Approved versions are snapshotted. Live Settings resolve the current draft/preview until an approved version is made effective.',
            ],
        ];

        $public = [
            'title' => __('site.responsible_lending.title'),
            'intro' => __('site.responsible_lending.intro'),
            'principles' => __('site.responsible_lending.principles'),
            'fees' => __('site.responsible_lending.fees'),
            'repayment' => __('site.responsible_lending.repayment'),
            'arrears' => __('site.responsible_lending.arrears'),
            'complaints' => __('site.responsible_lending.complaints'),
            'privacy' => __('site.responsible_lending.privacy'),
            'more' => __('site.responsible_lending.more'),
        ];

        $payload = compact('document', 'sections', 'products', 'warnings', 'public');
        $fingerprint = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $payload + ['fingerprint' => $fingerprint];
    }

    /** @return array<string, mixed> */
    private function productBlock(LoanProduct $product): array
    {
        $presentation = $this->products->forProduct($product);
        $limits = $presentation['limits'] ?? [];

        return [
            'code' => $product->code,
            'name' => $presentation['name'] ?? $product->name,
            'status' => $product->status,
            'purpose' => $presentation['overview_short'] ?? null,
            'min_amount' => $limits['min_amount'] ?? $product->min_amount,
            'max_amount' => $limits['max_amount'] ?? $product->max_amount,
            'tenure_min_months' => $limits['tenure_min_months'] ?? $product->tenure_min_months,
            'tenure_max_months' => $limits['tenure_max_months'] ?? $product->tenure_max_months,
            'repayment_frequency' => $presentation['repayment_frequency_label'] ?? null,
            'rate' => $this->rates->formatBorrowerRateRange($product),
            'application_fee' => $presentation['fees']['application'] ?? null,
            'fees' => $presentation['fees']['post_approval_lines'] ?? [],
            'eligibility' => $presentation['eligibility'] ?? [],
            'documents' => $presentation['documents'] ?? [],
            'collateral' => (bool) ($presentation['requires_collateral'] ?? false),
            'guarantor' => (bool) ($presentation['requires_guarantor'] ?? false),
            'collateral_policy' => $presentation['collateral_policy'] ?? null,
        ];
    }

    public function approveSnapshot(?string $approvedBy = null): LendingPolicyVersion
    {
        $resolved = $this->resolve();
        $current = LendingPolicyVersion::query()->where('status', 'approved')->latest('id')->first();

        if ($current) {
            $current->update(['status' => 'superseded']);
        }

        $version = now()->format('Y.m.d').'-'.substr($resolved['fingerprint'], 0, 6);
        Setting::set('governance.lending_policy_version', $version);
        Setting::set('governance.lending_policy_status', 'approved');
        Setting::set('governance.lending_policy_effective_at', now()->toDateString());
        Setting::set('governance.lending_policy_approved_at', now()->toDateTimeString());
        Setting::set('governance.lending_policy_approved_by', $approvedBy ?: (Auth::user()?->name ?? 'System'));
        Setting::set('governance.lending_policy_next_review_at', now()->addYear()->toDateString());

        return LendingPolicyVersion::query()->create([
            'version' => $version,
            'status' => 'approved',
            'title' => $resolved['document']['title'],
            'jurisdiction' => $resolved['document']['jurisdiction'],
            'effective_at' => now(),
            'approved_at' => now(),
            'approved_by' => $approvedBy ?: (Auth::user()?->name ?? 'System'),
            'next_review_at' => now()->addYear(),
            'settings_fingerprint' => $resolved['fingerprint'],
            'supersedes_id' => $current?->id,
            'snapshot' => $resolved,
            'warnings' => $resolved['warnings'],
        ]);
    }

    public function currentApproved(): ?LendingPolicyVersion
    {
        return LendingPolicyVersion::query()->where('status', 'approved')->latest('id')->first();
    }
}
