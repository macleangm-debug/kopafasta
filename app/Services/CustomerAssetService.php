<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerAssetService
{
    /** @return Collection<int, CustomerAsset> */
    public function forCustomer(Customer $customer): Collection
    {
        return CustomerAsset::query()
            ->where('customer_id', $customer->id)
            ->where('is_active', true)
            ->latest()
            ->get();
    }

    public function resolveUwApplication(Customer $customer, ?int $applicationId): ?LoanApplication
    {
        $docs = app(ApplicationDocumentRequestService::class);

        if ($applicationId) {
            $application = LoanApplication::query()->find($applicationId);
            if ($application && $docs->customerCanViewApplication($customer, $application)) {
                return $application;
            }
        }

        return LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['withdrawn', 'rejected', 'cancelled', 'disbursed'])
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, UploadedFile|null>  $files
     */
    public function store(Customer $customer, array $data, array $files = []): CustomerAsset
    {
        $type = (string) ($data['asset_type'] ?? '');
        if (! array_key_exists($type, CustomerAsset::typeOptions())) {
            throw ValidationException::withMessages(['asset_type' => 'Select a valid asset type.']);
        }

        $photoPaths = [];
        $metadata = [];

        foreach ($files['photos'] ?? [] as $photo) {
            if ($photo) {
                $photoPaths[] = $photo->store("customer/{$customer->id}/assets", 'public');
            }
        }

        if (($files['photo'] ?? null) && count($photoPaths) < 4) {
            $photoPaths[] = $files['photo']->store("customer/{$customer->id}/assets", 'public');
        }

        if ($files['person_photo'] ?? null) {
            $metadata['person_with_asset_path'] = $files['person_photo']->store("customer/{$customer->id}/assets", 'public');
        }
        if ($files['ownership_document'] ?? null) {
            $metadata['ownership_document_path'] = $files['ownership_document']->store("customer/{$customer->id}/assets/docs", 'public');
        }
        if ($files['insurance_document'] ?? null) {
            $metadata['insurance_document_path'] = $files['insurance_document']->store("customer/{$customer->id}/assets/docs", 'public');
        }

        $details = array_filter(
            (array) ($data['details'] ?? []),
            fn ($value) => $value !== null && $value !== ''
        );
        if ($type === 'vehicle') {
            $details['insurance_type'] = $details['insurance_type'] ?? 'comprehensive';
            if (! in_array($details['insurance_type'], ['comprehensive', 'third_party'], true)) {
                $details['insurance_type'] = 'comprehensive';
            }
        }
        if ($details !== []) {
            $metadata['details'] = $details;
        }

        return CustomerAsset::create([
            'customer_id'          => $customer->id,
            'asset_type'           => $type,
            'label'                => (string) ($data['label'] ?? ucfirst($type)),
            'description'          => $data['description'] ?? null,
            'registration_number'  => $data['registration_number'] ?? null,
            'estimated_value'      => filled($data['estimated_value'] ?? null) ? (float) $data['estimated_value'] : null,
            'photo_paths'          => $photoPaths ?: null,
            'metadata'             => $metadata ?: null,
            'is_active'            => true,
        ]);
    }

    public function update(CustomerAsset $asset, array $data, ?UploadedFile $photo = null): CustomerAsset
    {
        $photoPaths = $asset->photo_paths ?? [];
        if ($photo) {
            $photoPaths[] = $photo->store("customer/{$asset->customer_id}/assets", 'public');
        }

        $asset->update([
            'asset_type'          => $data['asset_type'] ?? $asset->asset_type,
            'label'               => $data['label'] ?? $asset->label,
            'description'         => $data['description'] ?? $asset->description,
            'registration_number' => $data['registration_number'] ?? $asset->registration_number,
            'estimated_value'     => array_key_exists('estimated_value', $data)
                ? (filled($data['estimated_value']) ? (float) $data['estimated_value'] : null)
                : $asset->estimated_value,
            'photo_paths'         => $photoPaths ?: $asset->photo_paths,
        ]);

        return $asset->refresh();
    }

    public function deactivate(CustomerAsset $asset): void
    {
        $asset->update(['is_active' => false]);
    }

    /**
     * True when this profile asset is already linked to another non-terminal loan application.
     */
    public function isPledgedToAnotherApplication(CustomerAsset $asset, ?int $exceptApplicationId = null): bool
    {
        return $this->pledgedApplication($asset, $exceptApplicationId) !== null;
    }

    public function pledgedApplication(CustomerAsset $asset, ?int $exceptApplicationId = null): ?LoanApplication
    {
        $row = LoanApplicationAsset::query()
            ->where('customer_asset_id', $asset->id)
            ->whereHas('application', function ($q) use ($exceptApplicationId): void {
                $q->whereNotIn('status', ['withdrawn', 'rejected', 'cancelled']);
                if ($exceptApplicationId) {
                    $q->where('id', '!=', $exceptApplicationId);
                }
            })
            ->with('application')
            ->latest('id')
            ->first();

        return $row?->application;
    }

    public function linkOnApplication(CustomerAsset $asset, int $applicationId): ?LoanApplicationAsset
    {
        return LoanApplicationAsset::query()
            ->where('customer_asset_id', $asset->id)
            ->where('loan_application_id', $applicationId)
            ->first();
    }

    /** Why this asset cannot be pledged yet, or null when documents look complete. */
    public function incompleteReason(CustomerAsset $asset): ?string
    {
        $photos = count(array_values($asset->photo_paths ?? []));
        if ($photos < 2) {
            return 'photos';
        }
        if (! filled($asset->metadata['ownership_document_path'] ?? null)) {
            return 'ownership';
        }
        if ($asset->asset_type === 'vehicle') {
            if (! $asset->hasVehicleInsurance()) {
                return 'insurance';
            }
            $expires = $asset->detail('insurance_expires_at');
            if (filled($expires) && now()->startOfDay()->gte(\Illuminate\Support\Carbon::parse((string) $expires)->startOfDay())) {
                return 'insurance_expired';
            }
        }

        return null;
    }

    /**
     * Borrower-facing availability for picking this asset onto a loan.
     *
     * @return array{code: string, selectable: bool, application_number: ?string, incomplete: ?string}
     */
    public function availabilityForApplication(CustomerAsset $asset, ?LoanApplication $application): array
    {
        $appId = $application?->id;
        if ($appId) {
            $onThis = $this->linkOnApplication($asset, $appId);
            if ($onThis) {
                if ($onThis->isDeclined()) {
                    return [
                        'code' => 'declined',
                        'selectable' => false,
                        'application_number' => $application?->application_number,
                        'incomplete' => null,
                    ];
                }

                return [
                    'code' => 'on_this_loan',
                    'selectable' => false,
                    'application_number' => $application?->application_number,
                    'incomplete' => null,
                ];
            }
        }

        $other = $this->pledgedApplication($asset, $appId);
        if ($other) {
            return [
                'code' => 'pledged_other',
                'selectable' => false,
                'application_number' => $other->application_number,
                'incomplete' => null,
            ];
        }

        $incomplete = $this->incompleteReason($asset);
        if ($incomplete) {
            return [
                'code' => 'incomplete',
                'selectable' => false,
                'application_number' => null,
                'incomplete' => $incomplete,
            ];
        }

        return [
            'code' => 'available',
            'selectable' => (bool) $application,
            'application_number' => $application?->application_number,
            'incomplete' => null,
        ];
    }

    public function attachToApplication(CustomerAsset $asset, LoanApplication $application, Customer $actor): LoanApplicationAsset
    {
        abort_unless((int) $asset->customer_id === (int) $actor->id && $asset->is_active, 404);

        $existing = $this->linkOnApplication($asset, $application->id);
        if ($existing && ! $existing->isDeclined()) {
            app(ApplicationDocumentRequestService::class)
                ->markCollateralRequestsUploadedFromProfile($actor, $application);

            return $existing;
        }

        $availability = $this->availabilityForApplication($asset, $application);
        if (! $availability['selectable']) {
            throw ValidationException::withMessages([
                'asset' => [__('borrower.profile.collateral_cannot_use')],
            ]);
        }

        $description = trim(collect([
            $asset->label,
            $asset->description,
            $asset->registration_number,
        ])->filter()->implode(' · '));

        $created = LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => (string) $asset->asset_type,
            'description' => $description ?: null,
            'valuation_status' => 'awaiting_valuation',
            'gps_required' => in_array((string) $asset->asset_type, ['motorcycle', 'saloon_car', 'suv', 'truck', 'heavy_machinery', 'vehicle'], true),
            'uw_status' => LoanApplicationAsset::UW_PENDING,
            'is_primary' => ! LoanApplicationAsset::query()
                ->where('loan_application_id', $application->id)
                ->where('is_primary', true)
                ->exists(),
        ]);

        app(ApplicationDocumentRequestService::class)
            ->markCollateralRequestsUploadedFromProfile($actor, $application);

        return $created;
    }

    /**
     * Append additional gallery photos to an existing collateral (within a hard cap).
     *
     * @param  array<int, UploadedFile>  $photos
     */
    public function addPhotos(CustomerAsset $asset, array $photos, int $max = 6): CustomerAsset
    {
        $paths = array_values($asset->photo_paths ?? []);
        foreach ($photos as $photo) {
            if (count($paths) >= $max) {
                break;
            }
            if ($photo instanceof UploadedFile && $photo->isValid()) {
                $paths[] = $photo->store("customer/{$asset->customer_id}/assets", 'public');
            }
        }

        $asset->update(['photo_paths' => $paths ?: null]);

        return $asset->refresh();
    }

    /** Remove a single gallery photo by its zero-based index. */
    public function deletePhoto(CustomerAsset $asset, int $index): CustomerAsset
    {
        $paths = array_values($asset->photo_paths ?? []);
        if (array_key_exists($index, $paths)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($paths[$index]);
            unset($paths[$index]);
            $asset->update(['photo_paths' => array_values($paths) ?: null]);
        }

        return $asset->refresh();
    }

    /** Replace a single gallery photo by index. */
    public function replacePhoto(CustomerAsset $asset, int $index, UploadedFile $photo): CustomerAsset
    {
        $paths = array_values($asset->photo_paths ?? []);
        if (array_key_exists($index, $paths) && $photo->isValid()) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($paths[$index]);
            $paths[$index] = $photo->store("customer/{$asset->customer_id}/assets", 'public');
            $asset->update(['photo_paths' => array_values($paths)]);
        }

        return $asset->refresh();
    }
}
