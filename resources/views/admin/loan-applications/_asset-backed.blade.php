@php
    $application = $record;
    $asset = $application->collateralAsset;
    $isAb = app(\App\Services\AssetBackedLoanService::class)->isAssetBackedApplication($application);
@endphp

@if ($isAb)
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Asset-backed collateral</h3>

        @if ($asset)
            <dl class="grid sm:grid-cols-2 gap-3 text-sm mb-4">
                <div><dt class="text-xs text-gray-500">Asset type</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $asset->asset_type) }}</dd></div>
                <div><dt class="text-xs text-gray-500">Valuation status</dt><dd class="font-medium capitalize">{{ str_replace('_', ' ', $asset->valuation_status) }}</dd></div>
                @if ($asset->description)<div class="sm:col-span-2"><dt class="text-xs text-gray-500">Description</dt><dd>{{ $asset->description }}</dd></div>@endif
                @if ($asset->market_value)<div><dt class="text-xs text-gray-500">Market value</dt><dd>{{ format_money($asset->market_value) }}</dd></div>@endif
                @if ($asset->forced_sale_value)<div><dt class="text-xs text-gray-500">Forced sale value</dt><dd>{{ format_money($asset->forced_sale_value) }}</dd></div>@endif
                @if ($asset->max_loan_amount)<div><dt class="text-xs text-gray-500">Max loan (LTV)</dt><dd class="font-semibold">{{ format_money($asset->max_loan_amount) }} @ {{ $asset->ltv_percent }}%</dd></div>@endif
                @if ($asset->gps_required)<div><dt class="text-xs text-gray-500">GPS</dt><dd>Required</dd></div>@endif
            </dl>
        @else
            <p class="text-sm text-gray-500 mb-4">Borrower has not submitted asset details yet.</p>
        @endif

        @if ($asset && ! in_array($asset->valuation_status, ['completed'], true))
            <form method="POST" action="{{ route('admin.loan-applications.assign-valuer', $application) }}" class="flex flex-wrap gap-3 items-end border-t border-gray-100 pt-4">
                @csrf
                <div class="min-w-[220px]">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Assign valuation partner</label>
                    <select name="vendor_id" required class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Select valuer…</option>
                        @foreach ($valuers ?? [] as $valuer)
                            <option value="{{ $valuer->id }}">{{ $valuer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                    <input type="text" name="notes" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Optional">
                </div>
                <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-lg text-sm">Assign</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.loan-applications.manual-fee', $application) }}" class="mt-6 pt-4 border-t border-gray-100 grid sm:grid-cols-4 gap-3 items-end">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Manual post-approval fee</label>
                <input type="text" name="description" required placeholder="Ownership transfer, insurance…" class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <x-admin.input name="partner_cost" label="Partner cost" type="number" step="1" min="0" required />
            <x-admin.input name="markup_percent" label="Markup %" type="number" step="0.1" min="0" value="10" required />
            <div class="sm:col-span-4">
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-2 rounded-lg text-sm">Add fee request</button>
            </div>
        </form>
    </div>
@endif
