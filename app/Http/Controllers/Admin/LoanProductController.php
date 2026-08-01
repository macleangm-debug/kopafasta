<?php

namespace App\Http\Controllers\Admin;

use App\Models\DocumentTemplate;
use App\Models\LoanProduct;
use App\Models\LoanProductRequirement;
use App\Services\AuditService;
use App\Services\DisplayedRateService;
use App\Models\LoanProductRateTier;
use App\Services\FeeCatalogService;
use App\Services\LoanRateTierTemplateService;
use App\Support\MoneyFormat;
use App\Support\RatePercent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanProductController extends ResourceController
{
    protected string $model = LoanProduct::class;
    protected string $routePrefix = 'admin.loan-products';
    protected string $viewFolder = 'loan-products';
    protected string $singular = 'loan product';

    protected function formData(?Model $record = null): array
    {
        return [
            'requirements' => $record
                ? $record->requirements()->orderBy('id')->get()
                : collect(),
            'postApprovalFees' => $record
                ? $record->postApprovalFees()->orderBy('sort_order')->get()
                : collect(),
            'rateTiers' => $record
                ? $record->rateTiers()->orderBy('sort_order')->get()
                : collect(app(LoanRateTierTemplateService::class)->previewRows(
                    old('code'),
                    MoneyFormat::toNumber(old('min_amount', $record?->min_amount ?? 100_000)),
                    MoneyFormat::toNumber(old('max_amount', $record?->max_amount ?? 5_000_000)),
                    (float) old('interest_rate', $record?->interest_rate ?? 0.17),
                )),
            'documentTemplates' => DocumentTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'postApprovalFeeCatalog' => app(FeeCatalogService::class)->postApprovalFees(),
            'cloneSources' => LoanProduct::query()
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ];
    }

    protected function rules(?Model $model = null): array
    {
        return [
            'code'                => ['required', 'string', 'max:30'],
            'name'                => ['required', 'string', 'max:150'],
            'name_sw'             => ['nullable', 'string', 'max:150'],
            'category'            => ['nullable', 'string', 'max:50'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'image'               => ['nullable', 'image', 'max:4096'],
            'clone_from_id'       => ['nullable', 'integer', 'exists:loan_products,id'],
            'interest_rate'       => ['nullable', 'numeric', 'min:0', 'max:1'],
            'application_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'offer_letter_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'loan_contract_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'guarantor_agreement_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'asset_lending_agreement_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'tenure_min_months'   => ['required', 'integer', 'min:1', 'max:120'],
            'tenure_max_months'   => ['required', 'integer', 'min:1', 'max:120'],
            'repayment_cadence'   => ['required', 'in:weekly,monthly'],
            'min_amount'          => ['required', 'numeric', 'min:0'],
            'max_amount'          => ['required', 'numeric', 'min:0'],
            'default_grace_days' => ['required', 'integer', 'min:0', 'max:90'],
            'penalty_rate_percent' => ['required', 'numeric', 'min:0', 'max:5'],
            'penalty_basis' => ['required', 'in:per_day,per_month,one_time'],
            'requires_collateral' => ['nullable', 'boolean'],
            'requires_guarantor'  => ['nullable', 'boolean'],
            'uses_capital_partner' => ['nullable', 'in:0,1'],
            'status'              => ['required', 'in:active,inactive,coming_soon'],
            'requirements'        => ['nullable', 'array'],
            'requirements.*.id'   => ['nullable', 'integer'],
            'requirements.*.name' => ['nullable', 'string', 'max:150'],
            'requirements.*.description' => ['nullable', 'string', 'max:500'],
            'requirements.*.is_required' => ['nullable', 'boolean'],
            'post_approval_fees' => ['nullable', 'array'],
            'post_approval_fees.*.charges_fee_id' => ['nullable', 'integer', 'exists:charges_fees,id'],
            'post_approval_fees.*.code' => ['nullable', 'string', 'max:40'],
            'post_approval_fees.*.name' => ['nullable', 'string', 'max:150'],
            'post_approval_fees.*.fee_type' => ['nullable', 'in:fixed,percent,gps'],
            'post_approval_fees.*.amount' => ['nullable', 'numeric', 'min:0'],
            'post_approval_fees.*.is_active' => ['nullable', 'boolean'],
            'rate_tiers' => ['nullable', 'array'],
            'rate_tiers.*.min_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_tiers.*.max_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_tiers.*.bot_regulated_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rate_tiers.*.processing_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rate_tiers.*.service_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rate_tiers.*.administration_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rate_tiers.*.monthly_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['requires_collateral'] = (bool) ($data['requires_collateral'] ?? false);
        $data['requires_guarantor']  = (bool) ($data['requires_guarantor'] ?? false);
        $data['uses_capital_partner'] = in_array((string) ($data['uses_capital_partner'] ?? '1'), ['1', 'true'], true);
        $category = strtolower((string) ($data['category'] ?? ''));
        if (in_array($category, ['asset_finance', 'asset_lending'], true)) {
            $data['uses_capital_partner'] = false;
        }
        $data['status']              = $data['status'] ?? 'inactive';
        $data['is_active']           = $data['status'] === 'active';
        $data['repayment_cadence']   = $data['repayment_cadence'] ?? 'weekly';

        $data['min_amount'] = MoneyFormat::toNumber($data['min_amount'] ?? 0);
        $data['max_amount'] = MoneyFormat::toNumber($data['max_amount'] ?? 0);

        $appFee = $data['application_fee_amount'] ?? null;
        $data['application_fee_amount'] = blank($appFee)
            ? null
            : MoneyFormat::toInteger($appFee);

        foreach ([
            'offer_letter_template_id',
            'loan_contract_template_id',
            'guarantor_agreement_template_id',
            'asset_lending_agreement_template_id',
        ] as $nullable) {
            if (blank($data[$nullable] ?? null)) {
                $data[$nullable] = null;
            }
        }

        return $data;
    }

    public function show($id)
    {
        $record = LoanProduct::with([
            'requirements',
            'rateTiers',
            'postApprovalFees',
            'offerLetterTemplate',
            'loanContractTemplate',
        ])->findOrFail($id);

        return view("admin.{$this->viewFolder}.show", ['record' => $record]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeMoneyRequest($request);
        $validated = $request->validate($this->rules());
        $requirements = $validated['requirements'] ?? [];
        $postApprovalFees = $validated['post_approval_fees'] ?? [];
        $rateTiers = $validated['rate_tiers'] ?? [];
        $cloneFromId = $validated['clone_from_id'] ?? null;
        unset($validated['requirements'], $validated['post_approval_fees'], $validated['rate_tiers'], $validated['clone_from_id'], $validated['image']);

        $source = $cloneFromId ? LoanProduct::with(['requirements', 'rateTiers', 'postApprovalFees'])->find($cloneFromId) : null;
        if ($source) {
            $validated = $this->mergeCloneDefaults($validated, $source);
            if ($requirements === []) {
                $requirements = $source->requirements->map(fn ($r) => [
                    'name' => $r->name,
                    'description' => $r->description,
                    'is_required' => $r->is_required,
                ])->all();
            }
            if ($postApprovalFees === []) {
                $postApprovalFees = $source->postApprovalFees->map(fn ($f) => [
                    'charges_fee_id' => $f->charges_fee_id,
                    'code' => $f->code,
                    'name' => $f->name,
                    'fee_type' => $f->fee_type,
                    'amount' => $f->amount,
                    'is_active' => $f->is_active,
                ])->all();
            }
            if ($rateTiers === []) {
                $rateTiers = $source->rateTiers->map(fn ($t) => [
                    'min_amount' => $t->min_amount,
                    'max_amount' => $t->max_amount,
                    'bot_regulated_rate' => $t->bot_regulated_rate,
                    'processing_fee_rate' => $t->processing_fee_rate,
                    'service_fee_rate' => $t->service_fee_rate,
                    'administration_fee_rate' => $t->administration_fee_rate,
                    'monthly_rate' => $t->monthly_rate,
                ])->all();
            }
            $validated['clone_source_id'] = $source->id;
            if (blank($validated['image_path'] ?? null) && filled($source->image_path)) {
                $validated['image_path'] = $source->image_path;
            }
        }

        $payload = $this->transform($validated);
        if ($request->hasFile('image')) {
            $payload['image_path'] = $request->file('image')->store('loan-products', 'public');
        }

        $record = LoanProduct::create($payload);
        $this->syncRequirements($record, $requirements);
        $this->syncPostApprovalFees($record, $postApprovalFees);
        $this->syncRateTiers($record, $rateTiers, applyDefaultsIfEmpty: true);
        $this->syncInterestRateFromTiers($record);
        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $record = LoanProduct::findOrFail($id);
        $before = app(AuditService::class)->snapshot($record);
        $this->normalizeMoneyRequest($request);
        $validated = $request->validate($this->rules($record));
        $requirements = $validated['requirements'] ?? [];
        $postApprovalFees = $validated['post_approval_fees'] ?? [];
        $rateTiers = $validated['rate_tiers'] ?? [];
        unset($validated['requirements'], $validated['post_approval_fees'], $validated['rate_tiers'], $validated['clone_from_id'], $validated['image']);

        $payload = $this->transform($validated, $record);
        if ($request->hasFile('image')) {
            $payload['image_path'] = $request->file('image')->store('loan-products', 'public');
        }

        $record->update($payload);
        $this->syncRequirements($record, $requirements);
        $this->syncPostApprovalFees($record, $postApprovalFees);
        $this->syncRateTiers($record, $rateTiers, applyDefaultsIfEmpty: true);
        $this->syncInterestRateFromTiers($record);
        $regeneratedFees = app(\App\Services\PostApprovalFeeService::class)->syncFromProductUpdate($record->fresh());
        $record->refresh();
        $this->auditAdminUpdated($record, $before);

        $message = ucfirst($this->singular).' updated.';
        if ($regeneratedFees > 0) {
            $message .= " Regenerated post-approval fees for {$regeneratedFees} pending application(s).";
        }

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', $message);
    }

    /** @param array<string, mixed> $validated */
    protected function mergeCloneDefaults(array $validated, LoanProduct $source): array
    {
        foreach ([
            'category', 'description', 'interest_rate', 'application_fee_amount',
            'offer_letter_template_id', 'loan_contract_template_id',
            'guarantor_agreement_template_id', 'asset_lending_agreement_template_id',
            'tenure_min_months', 'tenure_max_months', 'repayment_cadence',
            'min_amount', 'max_amount', 'default_grace_days', 'penalty_rate_percent',
            'penalty_basis', 'requires_collateral', 'requires_guarantor', 'uses_capital_partner',
        ] as $key) {
            if (! array_key_exists($key, $validated) || blank($validated[$key])) {
                $validated[$key] = $source->{$key};
            }
        }

        return $validated;
    }

    public function regenerateRateTiers(LoanProduct $loanProduct): RedirectResponse
    {
        app(LoanRateTierTemplateService::class)->applyDefaults($loanProduct, replaceExisting: true);
        $this->syncInterestRateFromTiers($loanProduct->fresh());

        $this->auditAdmin('loan_products.rate_tiers.regenerated', $loanProduct);

        return redirect()
            ->route("{$this->routePrefix}.edit", $loanProduct)
            ->with('status', 'Default amount-band tiers regenerated from product limits and interest rate.');
    }

    protected function normalizeMoneyRequest(Request $request): void
    {
        $request->merge([
            'min_amount' => MoneyFormat::toNumber($request->input('min_amount')),
            'max_amount' => MoneyFormat::toNumber($request->input('max_amount')),
            'application_fee_amount' => $request->filled('application_fee_amount')
                ? MoneyFormat::toNumber($request->input('application_fee_amount'))
                : null,
        ]);

        $tiers = $request->input('rate_tiers', []);
        if (is_array($tiers)) {
            foreach ($tiers as $index => $tier) {
                if (! is_array($tier)) {
                    continue;
                }
                $tiers[$index]['min_amount'] = MoneyFormat::toNumber($tier['min_amount'] ?? 0);
                $tiers[$index]['max_amount'] = MoneyFormat::toNumber($tier['max_amount'] ?? 0);
            }
            $request->merge(['rate_tiers' => $tiers]);
        }

        $fees = $request->input('post_approval_fees', []);
        if (! is_array($fees)) {
            $fees = [];
        }

        $catalogIds = $request->input('post_approval_catalog', []);
        if (is_array($catalogIds)) {
            foreach ($catalogIds as $feeId) {
                $feeId = (int) $feeId;
                if ($feeId <= 0) {
                    continue;
                }
                $exists = collect($fees)->contains(
                    fn ($row) => is_array($row) && (int) ($row['charges_fee_id'] ?? 0) === $feeId,
                );
                if (! $exists) {
                    $fees[] = ['charges_fee_id' => $feeId, 'is_active' => true];
                }
            }
        }

        foreach ($fees as $index => $fee) {
            if (! is_array($fee) || ! empty($fee['charges_fee_id'])) {
                continue;
            }
            if (($fee['fee_type'] ?? 'fixed') === 'fixed') {
                $fees[$index]['amount'] = MoneyFormat::toNumber($fee['amount'] ?? 0);
            }
        }

        $request->merge(['post_approval_fees' => $fees]);
    }

    protected function syncInterestRateFromTiers(LoanProduct $product): void
    {
        $product->load('rateTiers');
        $range = app(DisplayedRateService::class)->borrowerRateRange($product);
        $fallback = max($range['min'], $range['max']);

        if ($fallback > 0) {
            $product->update(['interest_rate' => $fallback]);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    protected function syncRequirements(LoanProduct $product, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $payload = [
                'type'        => 'document',
                'name'        => $name,
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
                'is_required' => (bool) ($row['is_required'] ?? false),
            ];

            $existingId = $row['id'] ?? null;
            if ($existingId) {
                $requirement = LoanProductRequirement::query()
                    ->where('loan_product_id', $product->id)
                    ->whereKey($existingId)
                    ->first();

                if ($requirement) {
                    $requirement->update($payload);
                    $keptIds[] = $requirement->id;

                    continue;
                }
            }

            $created = $product->requirements()->create($payload);
            $keptIds[] = $created->id;
        }

        $query = $product->requirements();
        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds);
        }
        $query->delete();
    }

    /** @param list<array<string, mixed>> $rows */
    protected function syncPostApprovalFees(LoanProduct $product, array $rows): void
    {
        $catalog = app(FeeCatalogService::class);
        $product->postApprovalFees()->delete();

        $order = 0;
        foreach ($rows as $row) {
            $feeId = (int) ($row['charges_fee_id'] ?? 0);
            $catalogFee = $feeId > 0 ? $catalog->findPostApprovalFee($feeId) : null;

            if ($catalogFee) {
                $snapshot = $catalog->snapshotForProduct($catalogFee);
                $product->postApprovalFees()->create([
                    'charges_fee_id' => $snapshot['charges_fee_id'],
                    'code'           => $snapshot['code'],
                    'name'           => $snapshot['name'],
                    'fee_type'       => $snapshot['fee_type'],
                    'amount'         => $snapshot['amount'],
                    'sort_order'     => $order++,
                    'is_active'      => (bool) ($row['is_active'] ?? true),
                ]);

                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            if ($name === '' || $code === '') {
                continue;
            }

            $feeType = (string) ($row['fee_type'] ?? 'fixed');
            $amount = $feeType === 'percent'
                ? RatePercent::toDecimal($row['amount'] ?? 0)
                : MoneyFormat::toNumber($row['amount'] ?? 0);

            $product->postApprovalFees()->create([
                'charges_fee_id' => null,
                'code'           => $code,
                'name'           => $name,
                'fee_type'       => in_array($feeType, ['percent', 'gps'], true) ? $feeType : 'fixed',
                'amount'         => $amount,
                'sort_order'     => $order++,
                'is_active'      => (bool) ($row['is_active'] ?? true),
            ]);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    protected function syncRateTiers(LoanProduct $product, array $rows, bool $applyDefaultsIfEmpty = false): void
    {
        $product->rateTiers()->delete();

        $order = 0;
        $created = 0;

        foreach ($rows as $row) {
            if (! isset($row['min_amount'], $row['max_amount'])) {
                continue;
            }

            $bot = min(RatePercent::toDecimal($row['bot_regulated_rate'] ?? 0), LoanProductRateTier::BOT_MAX);
            $processing = RatePercent::toDecimal($row['processing_fee_rate'] ?? 0);
            $risk = RatePercent::toDecimal($row['service_fee_rate'] ?? 0);
            $insurance = RatePercent::toDecimal($row['administration_fee_rate'] ?? 0);
            $monthly = LoanProductRateTier::totalFromComponents($bot, $processing, $risk, $insurance);

            if ($monthly <= 0 || $monthly > 1) {
                continue;
            }

            $product->rateTiers()->create([
                'min_amount'              => MoneyFormat::toNumber($row['min_amount']),
                'max_amount'              => MoneyFormat::toNumber($row['max_amount']),
                'bot_regulated_rate'      => $bot,
                'processing_fee_rate'     => $processing,
                'service_fee_rate'        => $risk,
                'administration_fee_rate' => $insurance,
                'monthly_rate'            => $monthly,
                'sort_order'              => $order++,
            ]);
            $created++;
        }

        if ($created === 0 && $applyDefaultsIfEmpty) {
            app(LoanRateTierTemplateService::class)->applyDefaults($product);
        }
    }
}
