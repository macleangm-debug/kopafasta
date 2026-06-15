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
            'asset_photo_front'       => 'Front photo',
            'asset_photo_rear'        => 'Rear photo',
            'asset_photo_left'        => 'Left side photo',
            'asset_photo_right'       => 'Right side photo',
            'ownership_certificate'   => 'Ownership certificate',
            'insurance_certificate'   => 'Insurance certificate',
        ];
    }

    /** @param array<string, mixed> $form */
    public function validateAssetDetails(Customer $customer, array $form): void
    {
        $customerAsset = $this->resolveCustomerAsset($customer, $form);
        $type = $customerAsset?->asset_type ?? (string) ($form['asset_type'] ?? '');
        $options = $this->assets->assetTypeOptions();

        if ($type === '' || ! array_key_exists($type, $options)) {
            throw ValidationException::withMessages([
                'asset_type' => 'Select a valid asset type.',
            ]);
        }

        $amount = (float) ($form['requested_amount'] ?? 0);
        $tenure = (int) ($form['requested_tenure_months'] ?? 0);

        if ($amount < 1000) {
            throw ValidationException::withMessages([
                'requested_amount' => 'Enter the loan amount you need.',
            ]);
        }

        if ($tenure < 1) {
            throw ValidationException::withMessages([
                'requested_tenure_months' => 'Enter a valid loan tenure.',
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

    /** @param array<string, mixed> $draftPayload */
    public function persistOnSubmit(LoanApplication $application, array $draftPayload): LoanApplicationAsset
    {
        $form = $draftPayload['form'] ?? [];
        $valuationFee = $draftPayload['valuation_fee'] ?? null;
        $paidAt = null;

        if (is_array($valuationFee) && in_array($valuationFee['status'] ?? '', ['paid', 'waived'], true)) {
            $paidAt = isset($valuationFee['paid_at'])
                ? \Carbon\Carbon::parse($valuationFee['paid_at'])
                : now();
        }

        $customerAsset = $this->resolveCustomerAsset($application->customer, $form);
        $assetType = (string) ($customerAsset?->asset_type ?? $form['asset_type'] ?? 'saloon_car');
        $description = $customerAsset
            ? trim(collect([$customerAsset->label, $customerAsset->description, $customerAsset->registration_number])->filter()->implode(' · '))
            : (filled($form['asset_description'] ?? null) ? (string) $form['asset_description'] : null);

        $asset = LoanApplicationAsset::updateOrCreate(
            ['loan_application_id' => $application->id],
            [
                'customer_asset_id'     => $customerAsset?->id,
                'asset_type'            => $assetType,
                'description'           => $description ?: null,
                'valuation_status'      => 'awaiting_valuation',
                'valuation_fee_paid_at' => $paidAt,
                'gps_required'          => in_array($assetType, ['motorcycle', 'saloon_car', 'suv', 'truck', 'heavy_machinery'], true),
            ],
        );

        $this->linkDocuments($application, $draftPayload);

        return $asset;
    }

    /** @param array<string, mixed> $form */
    private function resolveCustomerAsset(Customer $customer, array $form): ?CustomerAsset
    {
        $id = (int) ($form['customer_asset_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return CustomerAsset::query()
            ->where('customer_id', $customer->id)
            ->where('is_active', true)
            ->where('id', $id)
            ->first();
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
