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

    protected function findRecord(string|int $id): MarketplaceAsset
    {
        return MarketplaceAsset::query()
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->firstOrFail();
    }

    protected function rules(?Model $model = null): array
    {
        return app(MarketplaceAssetService::class)->validationRules($model instanceof MarketplaceAsset ? $model : null, true);
    }

    protected function formData(?Model $record = null): array
    {
        $lending = app(\App\Services\AssetLendingService::class);
        $assetService = app(MarketplaceAssetService::class);

        return [
            'suppliers'                   => Vendor::query()->where('category', 'supplier')->orderBy('name')->pluck('name', 'id'),
            'categories'                  => config('asset_marketplace.categories', []),
            'defaultDepositMarkupPercent' => $lending->defaultDepositMarkupPercent(),
            'maxAssetPhotos'              => $assetService->maxPhotos(),
            'prefill'                     => [
                'title'               => request()->query('title'),
                'asset_value'         => request()->query('asset_value'),
                'max_tenure_months'   => request()->query('max_tenure_months'),
                'vendor_id'           => request()->query('vendor_id'),
            ],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        unset($data['photos'], $data['remove_photos'], $data['cover_path']);

        return app(MarketplaceAssetService::class)->prepareForSave($data, $existing instanceof MarketplaceAsset ? $existing : null);
    }

    public function show($id)
    {
        $record = $this->findRecord($id);

        return view("admin.{$this->viewFolder}.show", ['record' => $record]);
    }

    public function edit($id)
    {
        $record = $this->findRecord($id);

        return view("admin.{$this->viewFolder}.edit", ['record' => $record] + $this->formData($record));
    }

    public function store(Request $request)
    {
        $service = app(MarketplaceAssetService::class);
        $service->normalizeRequest($request);
        $validated = $request->validate($this->rules());
        $data = $this->transform($validated);
        $record = MarketplaceAsset::create($data);
        $service->syncPhotos(
            $record,
            $request->file('photos', []),
            $request->input('remove_photos', []),
            $request->input('cover_path')
        );
        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function update(Request $request, $id)
    {
        $service = app(MarketplaceAssetService::class);
        $record = $this->findRecord($id);
        $service->normalizeRequest($request);
        $service->validateMinimumPhotos($record, $request->file('photos', []), $request->input('remove_photos', []));
        $before = app(\App\Services\AuditService::class)->snapshot($record);
        $validated = $request->validate($this->rules($record));
        $data = $this->transform($validated, $record);
        $record->update($data);
        $service->syncPhotos(
            $record,
            $request->file('photos', []),
            $request->input('remove_photos', []),
            $request->input('cover_path')
        );
        $this->auditAdminUpdated($record, $before);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' updated.');
    }

    public function destroy($id)
    {
        $record = $this->findRecord($id);
        $this->auditAdminDeleted($record);
        $record->delete();

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('status', ucfirst($this->singular).' deleted.');
    }
}
