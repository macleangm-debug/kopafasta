<x-admin.edit-page :title="'Edit '.$record->title" :heading="$record->title" subheading="Update marketplace asset"
    :action="route('admin.marketplace-assets.update', $record)" :destroyAction="route('admin.marketplace-assets.destroy', $record)" :cancelUrl="route('admin.marketplace-assets.show', $record)" submitLabel="Save changes"
    enctype="multipart/form-data">
    @include('admin.marketplace-assets._form', [
        'record' => $record,
        'productMaxTenureMonths' => $productMaxTenureMonths ?? app(\App\Services\AssetLendingService::class)->productMaxTenureMonths(),
        'defaultDepositMarkupPercent' => $defaultDepositMarkupPercent ?? 10,
        'depositTiers' => $depositTiers ?? [],
        'maxAssetPhotos' => $maxAssetPhotos ?? 7,
        'suppliers' => $suppliers ?? [],
        'categories' => $categories ?? [],
    ])
</x-admin.edit-page>
