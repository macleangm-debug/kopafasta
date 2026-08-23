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

        $open = fn ($q) => $q->whereNotIn('status', ['withdrawn', 'rejected', 'cancelled', 'disbursed']);

        $own = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->tap($open)
            ->latest('id')
            ->first();

        $group = LoanApplication::query()
            ->tap($open)
            ->where(function ($q) use ($customer): void {
                $q->whereNotNull('loan_group_id')
                    ->whereHas('loanGroup.members', function ($members) use ($customer): void {
                        $members->where('customer_id', $customer->id)
                            ->where('member_status', 'active');
                    });
            })
            ->latest('id')
            ->first();

        // Auto-link only the file owner (individual borrower / group leader). Members
        // pledge only when underwriting sent them an explicit application= request.
        if ($group && (int) $group->customer_id === (int) $customer->id && $this->shouldAutoLinkOnProfileSave($group)) {
            return $group;
        }

        if ($own && $this->shouldAutoLinkOnProfileSave($own)) {
            return $own;
        }

        return $own;
    }

    /** True when a profile asset should be pledged onto this live file (not unsecured IL). */
    public function shouldAutoLinkOnProfileSave(LoanApplication $application): bool
    {
        $application->loadMissing('product');

        if (filled($application->loan_group_id)) {
            return true;
        }

        $product = $application->product;
        if ($product && (bool) $product->requires_collateral) {
            return true;
        }

        if (app(GroupLendingService::class)->isGroupProduct($product)) {
            return true;
        }

        if (app(AssetBackedLoanService::class)->isAssetBackedApplication($application)) {
            return true;
        }

        $category = strtolower((string) ($product?->category ?? ''));

        return in_array($category, ['asset_finance', 'asset_lending'], true);
    }

    /**
     * Do not auto-pledge every saved profile asset onto a group file.
     * Only an explicit attach (new asset, Use this, or an application= request) should mark On this loan.
     */
    public function syncGroupFileAssets(LoanApplication $application): void
    {
        // Intentionally a no-op. Previously this pledged every leader profile asset
        // whenever screening opened, so unused saved vehicles showed as On this loan.
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
        $angleOrder = CustomerAsset::bodyPhotoAngleKeys($type);

        foreach ($files['photos'] ?? [] as $key => $photo) {
            if ($photo) {
                $path = $photo->store("customer/{$customer->id}/assets", 'public');
                $photoPaths[] = $path;
                $angle = is_string($key) && isset(CustomerAsset::photoAngleLabels($type)[$key])
                    ? $key
                    : ($angleOrder[(int) $key] ?? null);
                if ($angle) {
                    $metadata['photo_angles'][$angle] = $path;
                }
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
     * True when this profile asset is already linked to another open loan.
     * Rejected, cancelled, withdrawn applications, and closed/written-off loans release the asset.
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
                $q->whereNotIn('status', ['withdrawn', 'rejected', 'cancelled', 'expired']);
                $q->where(function ($open) {
                    $open->whereDoesntHave('loan')
                        ->orWhereHas('loan', function ($loan): void {
                            $loan->whereNotIn('status', ['closed', 'written_off']);
                        });
                });
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
        if (! $asset->hasCompletePhotoSet()) {
            $missing = $asset->missingPhotoAngles();
            if ($missing === ['owner']) {
                return 'person_photo';
            }

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

            try {
                app(CollateralSecureService::class)->maybeAcceptMemberPledge($application, $actor, $asset);
            } catch (\Throwable $e) {
                report($e);
            }

            $this->pledgeOnLoan($application, $asset);

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

        try {
            app(CollateralSecureService::class)->maybeAcceptMemberPledge($application, $actor, $asset);
        } catch (\Throwable $e) {
            report($e);
        }

        $this->pledgeOnLoan($application, $asset);

        return $created;
    }

    /**
     * Profile assets currently pledged as security on this loan.
     *
     * @return list<int>
     */
    public function onLoanAssetIds(LoanApplication $application): array
    {
        $stored = collect((array) data_get($application->screening_payload, 'collateral_secure.customer_asset_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $legacy = (int) data_get($application->screening_payload, 'collateral_secure.customer_asset_id', 0);
        if ($stored->isEmpty() && $legacy > 0) {
            $stored = collect([$legacy]);
        }

        $live = LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->where('uw_status', '!=', LoanApplicationAsset::UW_DECLINED)
            ->whereNotNull('customer_asset_id')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        if ($stored->isNotEmpty()) {
            return $live
                ->pluck('customer_asset_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $stored->contains($id))
                ->unique()
                ->values()
                ->all();
        }

        $first = $live->first(fn ($row) => (bool) $row->is_primary) ?? $live->first();

        return $first ? [(int) $first->customer_asset_id] : [];
    }

    public function onLoanCount(LoanApplication $application): int
    {
        return count($this->onLoanAssetIds($application));
    }

    /** First pledged asset — used where a single pick is still required. */
    public function designatedAssetId(LoanApplication $application): ?int
    {
        $ids = $this->onLoanAssetIds($application);

        return $ids[0] ?? null;
    }

    public function isOnThisLoan(CustomerAsset $asset, LoanApplication $application): bool
    {
        return in_array((int) $asset->id, $this->onLoanAssetIds($application), true);
    }

    /**
     * Add this profile asset to the loan’s pledged set (borrower / leader consent).
     */
    public function pledgeOnLoan(LoanApplication $application, CustomerAsset $asset): void
    {
        $link = $this->linkOnApplication($asset, $application->id);
        if (! $link || $link->isDeclined()) {
            return;
        }

        $ids = $this->onLoanAssetIds($application);
        if (! in_array((int) $asset->id, $ids, true)) {
            $ids[] = (int) $asset->id;
        }
        if ($ids === []) {
            $ids = [(int) $asset->id];
        }

        $hasPrimary = LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->where('is_primary', true)
            ->exists();
        if (! $hasPrimary) {
            $link->update(['is_primary' => true]);
        }

        $this->persistOnLoanIds($application, $ids);
        $this->healExtraPledges($application->fresh() ?? $application);

        $application->loadMissing('assignedAnalyst');
        app(CollateralSecureService::class)->promptValuationFeeAfterPledge(
            $application->fresh() ?? $application,
            $application->assignedAnalyst,
        );
    }

    /**
     * Persist an explicit pledged set, e.g. after asset-backed apply with several vehicles.
     *
     * @param  list<int>  $ids
     */
    public function persistOnLoanIds(LoanApplication $application, array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        $payload = (array) ($application->screening_payload ?? []);
        $cs = (array) ($payload['collateral_secure'] ?? []);
        $cs['customer_asset_ids'] = $ids;
        $cs['customer_asset_id'] = $ids[0] ?? null;
        $payload['collateral_secure'] = $cs;
        $application->update(['screening_payload' => $payload]);
        $application->setAttribute('screening_payload', $payload);
    }

    /**
     * Leftover auto-sync rows that were never listed as pledged. Keep every id in customer_asset_ids.
     */
    public function healExtraPledges(LoanApplication $application): void
    {
        $keepIds = $this->onLoanAssetIds($application);
        $live = LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->where('uw_status', '!=', LoanApplicationAsset::UW_DECLINED)
            ->whereNotNull('customer_asset_id')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        $isAb = app(AssetBackedLoanService::class)->isAssetBackedApplication($application);
        $stored = collect((array) data_get($application->screening_payload, 'collateral_secure.customer_asset_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);
        if ($stored->isEmpty() && $isAb && $live->count() > 1) {
            $keepIds = $live->pluck('customer_asset_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
            $this->persistOnLoanIds($application, $keepIds);
        } elseif ($stored->isEmpty() && $keepIds !== []) {
            $this->persistOnLoanIds($application, $keepIds);
        }

        if ($keepIds === []) {
            return;
        }

        foreach ($live as $row) {
            $id = (int) $row->customer_asset_id;
            if (in_array($id, $keepIds, true)) {
                continue;
            }
            if (filled($row->forced_sale_value) || $row->uw_status === LoanApplicationAsset::UW_ACCEPTED) {
                continue;
            }
            $row->delete();
        }

        $first = $keepIds[0] ?? null;
        if ($first) {
            LoanApplicationAsset::query()
                ->where('loan_application_id', $application->id)
                ->where('customer_asset_id', $first)
                ->update(['is_primary' => true]);
        }
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
