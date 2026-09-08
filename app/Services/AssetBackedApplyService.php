<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanApplicationDraft;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AssetBackedApplyService
{
    public function __construct(
        private readonly AssetBackedLoanService $assets,
    ) {}

    /** @return array<string, string> */
    public function documentLabels(): array
    {
        return [
            'asset_photo_front'       => __('borrower.apply.asset_documents.asset_photo_front'),
            'asset_photo_rear'        => __('borrower.apply.asset_documents.asset_photo_rear'),
            'asset_photo_left'        => __('borrower.apply.asset_documents.asset_photo_left'),
            'asset_photo_right'       => __('borrower.apply.asset_documents.asset_photo_right'),
            'ownership_certificate'   => __('borrower.apply.asset_documents.ownership_certificate'),
            'insurance_certificate'   => __('borrower.apply.asset_documents.insurance_certificate'),
        ];
    }

    /** @param array<string, mixed> $form */
    public function validateAssetDetails(Customer $customer, array $form): void
    {
        $customerAssets = $this->resolveCustomerAssets($customer, $form);

        if ($customerAssets->isEmpty()) {
            throw ValidationException::withMessages([
                'customer_asset_ids' => 'Select at least one asset from your profile. Add one under Profile → Assets if you have not yet.',
            ]);
        }

        $options = $this->assets->assetTypeOptions();
        $typeOptions = array_merge($options, CustomerAsset::typeOptions());

        foreach ($customerAssets as $customerAsset) {
            $type = $customerAsset->asset_type;
            if ($type === '' || (! array_key_exists($type, $typeOptions) && ! array_key_exists($type, $options))) {
                throw ValidationException::withMessages([
                    'customer_asset_ids' => 'A selected profile asset has an invalid type. Update it on your profile.',
                ]);
            }

            if (app(CustomerAssetService::class)->isPledgedToAnotherApplication($customerAsset)) {
                throw ValidationException::withMessages([
                    'customer_asset_ids' => __('borrower.apply.asset_details.asset_already_pledged', [
                        'label' => $customerAsset->label,
                    ]),
                ]);
            }

            // Comprehensive insurance is evaluated after credit approval as a
            // BEFORE_DISBURSEMENT collateral condition — never charged or required at apply.
        }

        $purpose = trim((string) ($form['purpose'] ?? ''));

        if ($purpose === '') {
            throw ValidationException::withMessages([
                'purpose' => __('borrower.apply.quote.select_purpose'),
            ]);
        }

        $amount = (float) ($form['requested_amount'] ?? 0);
        if ($amount < 1000) {
            throw ValidationException::withMessages([
                'requested_amount' => __('borrower.apply.asset_details.amount_required'),
            ]);
        }

        $tenure = (int) ($form['requested_tenure_months'] ?? 0);
        if ($tenure < 1) {
            throw ValidationException::withMessages([
                'requested_tenure_months' => __('borrower.apply.asset_details.tenure_required'),
            ]);
        }
    }

    public function uploadDocument(
        Customer $customer,
        LoanApplicationDraft $draft,
        string $code,
        UploadedFile $file,
    ): CustomerDocument {
        $labels = $this->documentLabels();
        abort_unless(array_key_exists($code, $labels), 422, 'Invalid document type.');

        $docType = DocumentType::firstOrCreate(
            ['code' => $code],
            [
                'name'       => $labels[$code],
                'category'   => 'collateral',
                'applies_to' => 'individual',
                'is_active'  => true,
            ],
        );

        $path = $file->store("borrower/{$customer->id}/collateral", 'public');

        $document = CustomerDocument::create([
            'customer_id'       => $customer->id,
            'document_type_id'  => $docType->id,
            'file_path'         => $path,
            'status'            => 'pending',
        ]);

        $payload = $draft->payload ?? [];
        $assetDocuments = $payload['asset_documents'] ?? [];
        $assetDocuments[$code] = [
            'customer_document_id' => $document->id,
            'code'                 => $code,
            'label'                => $labels[$code],
        ];
        $payload['asset_documents'] = $assetDocuments;
        $draft->update(['payload' => $payload, 'saved_at' => now()]);

        return $document;
    }

    /**
     * Persist one or more collateral rows for the application.
     *
     * @param  array<string, mixed>  $draftPayload
     * @return Collection<int, LoanApplicationAsset>
     */
    public function persistOnSubmit(LoanApplication $application, array $draftPayload): Collection
    {
        $form = $draftPayload['form'] ?? [];
        $valuationFee = $draftPayload['valuation_fee'] ?? null;
        $paidAt = null;

        if (is_array($valuationFee) && in_array($valuationFee['status'] ?? '', ['paid', 'waived'], true)) {
            $paidAt = isset($valuationFee['paid_at'])
                ? \Carbon\Carbon::parse($valuationFee['paid_at'])
                : now();
        }

        $customerAssets = $this->resolveCustomerAssets($application->customer, $form);

        // Replace prior draft rows for this application (idempotent re-submit).
        LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->delete();

        $created = collect();
        foreach ($customerAssets->values() as $index => $customerAsset) {
            $assetType = (string) $customerAsset->asset_type;
            $description = trim(collect([
                $customerAsset->label,
                $customerAsset->description,
                $customerAsset->registration_number,
            ])->filter()->implode(' · '));

            $created->push(LoanApplicationAsset::create([
                'loan_application_id'   => $application->id,
                'customer_asset_id'     => $customerAsset->id,
                'asset_type'            => $assetType,
                'description'           => $description ?: null,
                'valuation_status'      => 'awaiting_valuation',
                'valuation_fee_paid_at' => $index === 0 ? $paidAt : null,
                'gps_required'          => in_array($assetType, ['motorcycle', 'saloon_car', 'suv', 'truck', 'heavy_machinery', 'vehicle'], true),
                'uw_status'             => LoanApplicationAsset::UW_PENDING,
                'is_primary'            => $index === 0,
            ]));
        }

        $this->linkDocuments($application, $draftPayload);

        app(CustomerAssetService::class)->persistOnLoanIds(
            $application,
            $created->pluck('customer_asset_id')->map(fn ($id) => (int) $id)->all(),
        );

        if ($paidAt) {
            app(ValuationPartnerService::class)->autoAssignIfPossible($application->fresh());
        }

        return $created;
    }

    public function setUnderwritingStatus(
        LoanApplicationAsset $asset,
        string $status,
        ?string $notes = null,
    ): LoanApplicationAsset {
        abort_unless(in_array($status, [
            LoanApplicationAsset::UW_PENDING,
            LoanApplicationAsset::UW_ACCEPTED,
            LoanApplicationAsset::UW_DECLINED,
        ], true), 422);

        $asset->update([
            'uw_status' => $status,
            'uw_notes'  => $notes,
        ]);

        return $asset->refresh();
    }

    /** @param array<string, mixed> $form @return Collection<int, CustomerAsset> */
    private function resolveCustomerAssets(Customer $customer, array $form): Collection
    {
        $ids = collect($form['customer_asset_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        // Backward compat: single select field.
        if ($ids->isEmpty() && filled($form['customer_asset_id'] ?? null)) {
            $ids = collect([(int) $form['customer_asset_id']])->filter(fn ($id) => $id > 0);
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        $assets = CustomerAsset::query()
            ->where('customer_id', $customer->id)
            ->where('is_active', true)
            ->whereIn('id', $ids->all())
            ->get();

        return $ids
            ->map(fn ($id) => $assets->firstWhere('id', $id))
            ->filter()
            ->values();
    }

    /** @param array<string, mixed> $draftPayload */
    private function linkDocuments(LoanApplication $application, array $draftPayload): void
    {
        $assetDocuments = $draftPayload['asset_documents'] ?? [];
        if (! is_array($assetDocuments)) {
            return;
        }

        foreach ($assetDocuments as $entry) {
            $docId = (int) ($entry['customer_document_id'] ?? 0);
            if ($docId <= 0) {
                continue;
            }

            CustomerDocument::query()
                ->where('customer_id', $application->customer_id)
                ->where('id', $docId)
                ->update(['loan_application_id' => $application->id]);
        }
    }
}
