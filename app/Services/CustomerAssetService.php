<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAsset;
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
        return \App\Models\LoanApplicationAsset::query()
            ->where('customer_asset_id', $asset->id)
            ->whereHas('application', function ($q) use ($exceptApplicationId): void {
                $q->whereNotIn('status', ['withdrawn', 'rejected', 'cancelled']);
                if ($exceptApplicationId) {
                    $q->where('id', '!=', $exceptApplicationId);
                }
            })
            ->exists();
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
