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
        return [
            'vendor_id'              => ['nullable', 'exists:vendors,id'],
            'slug'                   => ['nullable', 'string', 'max:60'],
            'category'               => ['required', 'string', 'max:40'],
            'title'                  => ['required', 'string', 'max:150'],
            'description'            => ['nullable', 'string'],
            'supplier_name'          => ['nullable', 'string', 'max:150'],
            'asset_value'            => ['required', 'numeric', 'min:0'],
            'supplier_deposit'       => ['required', 'numeric', 'min:0'],
            'deposit_markup_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'weekly_installment'     => ['required', 'numeric', 'min:0'],
            'max_tenure_months'      => ['required', 'integer', 'min:1', 'max:120'],
            'is_active'              => ['nullable', 'boolean'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'suppliers'  => Vendor::query()->where('category', 'supplier')->where('status', 'active')->orderBy('name')->pluck('name', 'id'),
            'categories' => config('asset_marketplace.categories', []),
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return app(MarketplaceAssetService::class)->prepareForSave($data, $existing instanceof MarketplaceAsset ? $existing : null);
    }

    public function store(Request $request)
    {
        $data = $this->transform($request->validate($this->rules()));
        $record = MarketplaceAsset::create($data);
        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function update(Request $request, $id)
    {
        $record = MarketplaceAsset::findOrFail($id);
        $before = app(\App\Services\AuditService::class)->snapshot($record);
        $data = $this->transform($request->validate($this->rules($record)), $record);
        $record->update($data);
        $this->auditAdminUpdated($record, $before);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' updated.');
    }
}
