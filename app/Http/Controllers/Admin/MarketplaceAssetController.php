<?php

namespace App\Http\Controllers\Admin;

use App\Models\MarketplaceAsset;
use App\Models\Vendor;
use App\Services\MarketplaceAssetService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class MarketplaceAssetController extends ResourceController
{
    protected string $model = MarketplaceAsset::class;
    protected string $routePrefix = 'admin.marketplace-assets';
    protected string $viewFolder = 'marketplace-assets';
    protected string $singular = 'marketplace asset';

    protected function rules(?Model $model = null): array
    {
        return array_merge(app(MarketplaceAssetService::class)->validationRules(), [
            'vendor_id' => ['nullable', 'exists:partners,id'],
            'slug'      => ['nullable', 'string', 'max:60'],
        ]);
    }

    protected function formData(?Model $record = null): array
    {
        $lending = app(\App\Services\AssetLendingService::class);

        return [
            'suppliers'                   => Vendor::query()->where('category', 'supplier')->where('status', 'active')->orderBy('name')->pluck('name', 'id'),
            'categories'                  => config('asset_marketplace.categories', []),
            'defaultDepositMarkupPercent' => $lending->defaultDepositMarkupPercent(),
            'defaultWaitingPeriodDays'    => $lending->defaultWaitingPeriodDays(),
            'prefill'                     => [
                'title'               => request()->query('title'),
                'asset_value'         => request()->query('asset_value'),
                'max_tenure_months'   => request()->query('max_tenure_months'),
            ],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        unset($data['photos'], $data['remove_photos']);

        return app(MarketplaceAssetService::class)->prepareForSave($data, $existing instanceof MarketplaceAsset ? $existing : null);
    }

    public function store(Request $request)
    {
        $service = app(MarketplaceAssetService::class);
        $validated = $request->validate($this->rules());
        $data = $this->transform($validated);
        $record = MarketplaceAsset::create($data);
        $service->syncPhotos($record, $request->file('photos', []), $request->input('remove_photos', []));
        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function update(Request $request, $id)
    {
        $service = app(MarketplaceAssetService::class);
        $record = MarketplaceAsset::findOrFail($id);
        $before = app(\App\Services\AuditService::class)->snapshot($record);
        $validated = $request->validate($this->rules($record));
        $data = $this->transform($validated, $record);
        $record->update($data);
        $service->syncPhotos($record, $request->file('photos', []), $request->input('remove_photos', []));
        $this->auditAdminUpdated($record, $before);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' updated.');
    }
}
