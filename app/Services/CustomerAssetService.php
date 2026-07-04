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

        if ($files['photo'] ?? null) {
            $photoPaths[] = $files['photo']->store("customer/{$customer->id}/assets", 'public');
        }
        if ($files['person_photo'] ?? null) {
            $metadata['person_with_asset_path'] = $files['person_photo']->store("customer/{$customer->id}/assets", 'public');
        }
        if ($files['ownership_document'] ?? null) {
            $metadata['ownership_document_path'] = $files['ownership_document']->store("customer/{$customer->id}/assets/docs", 'public');
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
}
