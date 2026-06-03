<?php

namespace App\Http\Controllers\Admin;

use App\Models\DocumentTemplate;
use App\Models\LoanProduct;
use App\Models\LoanProductRequirement;
use App\Services\AuditService;
use App\Services\DisplayedRateService;
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
                : collect(app(LoanRateTierTemplateService::class)->previewRows(old('code'))),
            'documentTemplates' => DocumentTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ];
    }

    protected function rules(?Model $model = null): array
    {
        return [
            'code'                => ['required', 'string', 'max:30'],
            'name'                => ['required', 'string', 'max:150'],
            'category'            => ['nullable', 'string', 'max:50'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'interest_rate'       => ['nullable', 'numeric', 'min:0', 'max:1'],
            'bot_regulated_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'processing_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_fee_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'administration_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
            'requires_collateral' => ['nullable', 'boolean'],
            'requires_guarantor'  => ['nullable', 'boolean'],
            'status'              => ['required', 'in:active,inactive,coming_soon'],
            'requirements'        => ['nullable', 'array'],
            'requirements.*.id'   => ['nullable', 'integer'],
            'requirements.*.name' => ['nullable', 'string', 'max:150'],
            'requirements.*.description' => ['nullable', 'string', 'max:500'],
            'requirements.*.is_required' => ['nullable', 'boolean'],
            'post_approval_fees' => ['nullable', 'array'],
            'post_approval_fees.*.code' => ['nullable', 'string', 'max:40'],
            'post_approval_fees.*.name' => ['nullable', 'string', 'max:150'],
            'post_approval_fees.*.fee_type' => ['nullable', 'in:fixed,percent'],
            'post_approval_fees.*.amount' => ['nullable', 'numeric', 'min:0'],
            'post_approval_fees.*.is_active' => ['nullable', 'boolean'],
            'rate_tiers' => ['nullable', 'array'],
            'rate_tiers.*.min_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_tiers.*.max_amount' => ['nullable', 'numeric', 'min:0'],
            'rate_tiers.*.monthly_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['requires_collateral'] = (bool) ($data['requires_collateral'] ?? false);
        $data['requires_guarantor']  = (bool) ($data['requires_guarantor'] ?? false);
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

        foreach ([
            'bot_regulated_rate',
            'processing_fee_rate',
            'service_fee_rate',
            'administration_fee_rate',
        ] as $rateField) {
            if (array_key_exists($rateField, $data) && $data[$rateField] !== null && $data[$rateField] !== '') {
                $data[$rateField] = RatePercent::toDecimal($data[$rateField]);
            }
        }

        if (isset($data['bot_regulated_rate']) && $data['bot_regulated_rate'] !== null) {
            $data['bot_regulated_rate'] = min((float) $data['bot_regulated_rate'], 0.035);
        }

        foreach (['processing_fee_rate', 'service_fee_rate', 'administration_fee_rate'] as $feeField) {
            $data[$feeField] = (float) ($data[$feeField] ?? 0);
        }

        return $data;
    }

    public function show($id)
    {
        $record = LoanProduct::with(['requirements', 'rateTiers', 'offerLetterTemplate', 'loanContractTemplate'])->findOrFail($id);

        return view("admin.{$this->viewFolder}.show", ['record' => $record]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeMoneyRequest($request);
        $validated = $request->validate($this->rules());
        $requirements = $validated['requirements'] ?? [];
        $postApprovalFees = $validated['post_approval_fees'] ?? [];
        $rateTiers = $validated['rate_tiers'] ?? [];
        unset($validated['requirements'], $validated['post_approval_fees'], $validated['rate_tiers']);

        $record = LoanProduct::create($this->transform($validated));
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
        unset($validated['requirements'], $validated['post_approval_fees'], $validated['rate_tiers']);

        $record->update($this->transform($validated, $record));
        $this->syncRequirements($record, $requirements);
        $this->syncPostApprovalFees($record, $postApprovalFees);
        $this->syncRateTiers($record, $rateTiers, applyDefaultsIfEmpty: true);
        $this->syncInterestRateFromTiers($record);
        $record->refresh();
        $this->auditAdminUpdated($record, $before);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' updated.');
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
        if (is_array($fees)) {
            foreach ($fees as $index => $fee) {
                if (! is_array($fee) || ($fee['fee_type'] ?? 'fixed') !== 'fixed') {
                    continue;
                }
                $fees[$index]['amount'] = MoneyFormat::toNumber($fee['amount'] ?? 0);
            }
            $request->merge(['post_approval_fees' => $fees]);
        }
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
        $product->postApprovalFees()->delete();

        $order = 0;
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            if ($name === '' || $code === '') {
                continue;
            }

            $amount = ($row['fee_type'] ?? 'fixed') === 'percent'
                ? RatePercent::toDecimal($row['amount'] ?? 0)
                : MoneyFormat::toNumber($row['amount'] ?? 0);

            $product->postApprovalFees()->create([
                'code'       => $code,
                'name'       => $name,
                'fee_type'   => ($row['fee_type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed',
                'amount'     => $amount,
                'sort_order' => $order++,
                'is_active'  => (bool) ($row['is_active'] ?? true),
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
            if (! isset($row['min_amount'], $row['max_amount']) || blank($row['monthly_rate'] ?? null)) {
                continue;
            }

            $product->rateTiers()->create([
                'min_amount'   => MoneyFormat::toNumber($row['min_amount']),
                'max_amount'   => MoneyFormat::toNumber($row['max_amount']),
                'monthly_rate' => RatePercent::toDecimal($row['monthly_rate']),
                'sort_order'   => $order++,
            ]);
            $created++;
        }

        if ($created === 0 && $applyDefaultsIfEmpty) {
            app(LoanRateTierTemplateService::class)->applyDefaults($product);
        }
    }
}
