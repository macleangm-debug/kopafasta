<?php

namespace App\Services;

use App\Models\LoanProduct;
use Illuminate\Support\Facades\Lang;

class PublicProductPresentationService
{
    public function __construct(
        private readonly DisplayedRateService $rates,
        private readonly LoanRateTierService $tiers,
    ) {}

    /** @return array<string, mixed> */
    public function forProduct(LoanProduct $product): array
    {
        $product->loadMissing(['requirements', 'postApprovalFees', 'rateTiers']);
        $code = strtoupper((string) $product->code);
        $theme = config("loan_product_themes.{$code}", config('loan_product_themes.default'));
        $postApproval = $this->postApprovalSummary($product);
        $applicationFee = (int) ($product->application_fee_amount ?? 0);

        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->localizedName(),
            'status' => $product->status,
            'is_active' => $product->status === 'active',
            'icon' => $theme['icon'] ?? '💼',
            'theme' => $theme['theme'] ?? 'slate',
            'category_label' => loan_product_type_label($product),
            'tagline' => $theme['label'] ?? loan_product_type_label($product),
            'description' => (string) ($product->description ?? ''),
            'overview' => $this->overview($product),
            'overview_short' => \Illuminate\Support\Str::limit($this->overview($product), 160),
            'target_audience' => $this->targetAudience($product),
            'target_audience_short' => \Illuminate\Support\Str::limit($this->targetAudience($product), 120),
            'features' => array_slice(loan_product_features($product), 0, 4),
            'benefits' => array_slice($this->benefits($product), 0, 4),
            'eligibility' => $this->eligibility($product),
            'requirements' => $product->requirements->map(fn ($r) => [
                'name' => $r->name,
                'description' => $r->description,
                'required' => (bool) $r->is_required,
            ])->values()->all(),
            'limits' => [
                'min_amount' => (float) $product->min_amount,
                'max_amount' => (float) $product->max_amount,
                'tenure_min_months' => (int) $product->tenure_min_months,
                'tenure_max_months' => (int) $product->tenure_max_months,
            ],
            'rate_label' => $this->rates->formatBorrowerRateRange($product),
            'rate_disclosure' => $this->rates->borrowerDisclosureLines($product, (float) $product->min_amount),
            'monthly_rate_components' => $this->rates->borrowerMonthlyRateComponents($product),
            'repayment_frequency' => $product->repayment_cadence ?? $product->repayment_frequency ?? 'monthly',
            'repayment_frequency_label' => $this->repaymentFrequencyLabel($product),
            'fees' => [
                'application' => $applicationFee,
                'post_approval_total' => $postApproval['total'],
                'post_approval_lines' => $postApproval['lines'],
                'post_approval_detail' => $postApproval['detail'],
            ],
            'penalties' => $this->penalties($product),
            'documents' => $this->documents($product),
            'product_specific' => $this->productSpecific($code),
            'processing_time' => config("loan_product_apply.processing_time.{$code}")
                ?? config('loan_product_apply.processing_time.default'),
            'faq' => $this->faq($product),
            'requires_collateral' => (bool) $product->requires_collateral,
            'requires_guarantor' => (bool) $product->requires_guarantor,
            'highlights' => $this->highlights($product),
            'tiers' => $this->tiers->tiersForProduct($product),
        ];
    }

    private function repaymentFrequencyLabel(LoanProduct $product): string
    {
        $cadence = app(GroupLendingService::class)->effectiveRepaymentCadence($product);

        return $cadence === 'weekly'
            ? __('site.product_detail.repayment_weekly')
            : __('site.product_detail.repayment_monthly');
    }

    /** @return list<string> */
    private function highlights(LoanProduct $product): array
    {
        $items = [
            $this->repaymentFrequencyLabel($product),
        ];

        if ($product->requires_guarantor) {
            $items[] = __('site.product_detail.highlight_guarantor');
        } else {
            $items[] = __('site.product_detail.highlight_no_guarantor');
        }

        if ($product->requires_collateral) {
            $items[] = __('site.product_detail.highlight_collateral');
        } else {
            $items[] = __('site.product_detail.highlight_no_collateral');
        }

        return $items;
    }

    private function overview(LoanProduct $product): string
    {
        $key = 'site.product_detail.overview.'.$product->code;
        if (Lang::has($key)) {
            return (string) __($key);
        }

        return (string) ($product->description ?? '');
    }

    private function targetAudience(LoanProduct $product): string
    {
        $key = 'site.product_detail.audience.'.$product->code;
        if (Lang::has($key)) {
            return (string) __($key);
        }

        return (string) __('site.product_detail.audience.default', [
            'type' => loan_product_type_label($product),
        ]);
    }

    /** @return list<string> */
    private function benefits(LoanProduct $product): array
    {
        $key = 'site.product_detail.benefits.'.$product->code;
        if (Lang::has($key)) {
            return Lang::get($key);
        }

        $benefits = [];
        if (! $product->requires_collateral) {
            $benefits[] = __('site.product_detail.benefits.no_collateral');
        }
        if ((float) $product->max_amount >= 1_000_000) {
            $benefits[] = __('site.product_detail.benefits.high_limits');
        }
        $benefits[] = __('site.product_detail.benefits.mobile_first');
        $benefits[] = __('site.product_detail.benefits.transparent_fees');

        return $benefits;
    }

    /** @return list<array{label: string, detail: string}> */
    private function eligibility(LoanProduct $product): array
    {
        $items = [
            ['label' => __('site.product_detail.eligibility.membership'), 'detail' => __('site.product_detail.eligibility.membership_detail')],
            ['label' => __('site.product_detail.eligibility.identity'), 'detail' => __('site.product_detail.eligibility.identity_detail')],
            ['label' => __('site.product_detail.eligibility.income'), 'detail' => __('site.product_detail.eligibility.income_detail')],
        ];

        if ($product->requires_guarantor) {
            $items[] = ['label' => __('site.product_detail.eligibility.guarantor'), 'detail' => __('site.product_detail.eligibility.guarantor_detail')];
        }

        if ($product->requires_collateral) {
            $items[] = ['label' => __('site.product_detail.eligibility.collateral'), 'detail' => __('site.product_detail.eligibility.collateral_detail')];
        }

        return $items;
    }

    /** @return list<array{name: string, detail: string}> */
    private function documents(LoanProduct $product): array
    {
        $docs = [
            ['name' => __('site.product_detail.documents.nida'), 'detail' => __('site.product_detail.documents.nida_detail')],
            ['name' => __('site.product_detail.documents.income'), 'detail' => __('site.product_detail.documents.income_detail')],
        ];

        foreach ($product->requirements as $req) {
            $docs[] = [
                'name' => $req->name,
                'detail' => $req->description ?: ($req->is_required ? __('site.product_detail.documents.required') : __('site.product_detail.documents.optional')),
            ];
        }

        return $docs;
    }

    /** @return list<array{label: string, detail: string}> */
    private function productSpecific(string $code): array
    {
        $key = 'borrower.apply.readiness.specific.'.$code;
        if (Lang::has($key)) {
            return Lang::get($key);
        }

        return config('loan_product_apply.specific.'.$code, []);
    }

    /** @return list<array{q: string, a: string}> */
    private function faq(LoanProduct $product): array
    {
        $key = 'site.product_detail.faq.'.$product->code;
        if (Lang::has($key)) {
            return Lang::get($key);
        }

        return Lang::get('site.product_detail.faq.default', []);
    }

    /** @return array{total: float, detail: string, lines: list<array{name: string, amount: float}>} */
    private function postApprovalSummary(LoanProduct $product): array
    {
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
                    'name' => $fee->name,
                    'amount' => $fee->basis === 'percentage'
                        ? round($principal * ((float) $fee->amount / 100), 2)
                        : (float) $fee->amount,
                ];
            }
            $total = collect($lines)->sum('amount');
        }

        $detail = $lines === []
            ? __('site.product_detail.fees.post_approval_default')
            : collect($lines)->map(fn (array $l) => $l['name'].' ('.format_money($principal).')')->join(' · ');

        return [
            'total' => round($total, 2),
            'detail' => $detail,
            'lines' => $lines,
        ];
    }

    /** @return array{grace_days: int, rate_percent: float, basis: string, basis_label: string, cap_percent: float} */
    private function penalties(LoanProduct $product): array
    {
        $defaults = LoanPenaltyPolicy::defaultsForProduct($product);
        $basis = (string) ($defaults['penalty_basis'] ?? 'per_day');
        $basisLabel = match ($basis) {
            'per_month' => __('site.product_detail.penalty_basis_per_month'),
            'one_time' => __('site.product_detail.penalty_basis_one_time'),
            default => __('site.product_detail.penalty_basis_per_day'),
        };

        $cap = (float) (
            (\App\Models\Setting::group('loan')['penalty_cap_percent'] ?? null)
            ?? LoanPenaltyPolicy::BOT_MAX_PENALTY_CAP_PERCENT
        );
        $cap = min(LoanPenaltyPolicy::BOT_MAX_PENALTY_CAP_PERCENT, max(0, $cap));

        return [
            'grace_days' => (int) ($defaults['default_grace_days'] ?? 7),
            'rate_percent' => (float) ($defaults['penalty_rate_percent'] ?? LoanPenaltyPolicy::DEFAULT_PENALTY_RATE_PERCENT_PER_DAY),
            'basis' => $basis,
            'basis_label' => $basisLabel,
            'cap_percent' => $cap,
        ];
    }
}
