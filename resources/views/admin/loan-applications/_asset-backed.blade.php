@php
    $application = $record;
    $asset = $application->collateralAsset;
    $isAb = app(\App\Services\AssetBackedLoanService::class)->isAssetBackedApplication($application);
    $openAssignment = $application->valuationAssignments
        ->first(fn ($a) => in_array($a->status, ['assigned', 'in_progress'], true));
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

        @if ($valuationReport ?? null)
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 p-4 mb-4 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-sky-800">Valuation report</p>
                        <p class="font-semibold text-sky-950">{{ $valuationReport['valuer_name'] ?? 'Valuer' }} · {{ ucfirst(str_replace('_', ' ', $valuationReport['status'] ?? 'pending')) }}</p>
                    </div>
                    @if ($valuationReport['completed_at'] ?? null)
                        <p class="text-xs text-sky-700">Completed {{ $valuationReport['completed_at']->format('d M Y H:i') }}</p>
                    @endif
                </div>
                @if (($valuationReport['status'] ?? '') === 'completed')
                    <dl class="grid sm:grid-cols-2 gap-3">
                        <div><dt class="text-xs text-sky-800">Market value</dt><dd class="font-semibold">{{ format_money($valuationReport['market_value'] ?? 0) }}</dd></div>
                        <div><dt class="text-xs text-sky-800">Forced sale value</dt><dd class="font-semibold">{{ format_money($valuationReport['forced_sale_value'] ?? 0) }}</dd></div>
                        @if ($valuationReport['max_loan_amount'] ?? null)
                            <div><dt class="text-xs text-sky-800">Max loan</dt><dd class="font-semibold">{{ format_money($valuationReport['max_loan_amount']) }} @ {{ $valuationReport['ltv_percent'] }}%</dd></div>
                        @endif
                    </dl>
                    @if ($valuationReport['notes'] ?? null)
                        <p class="mt-3 text-xs text-sky-900 whitespace-pre-line">{{ $valuationReport['notes'] }}</p>
                    @endif
                    @if (! empty($valuationReport['photos']))
                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach ($valuationReport['photos'] as $photo)
                                <a href="{{ $photo['url'] }}" target="_blank" class="block rounded-lg overflow-hidden ring-1 ring-sky-200 bg-white">
                                    <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}" class="w-full h-24 object-cover">
                                    <p class="px-2 py-1 text-[10px] text-sky-800 truncate">{{ $photo['label'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @else
                    <p class="text-sky-900">Valuation in progress with {{ $valuationReport['valuer_name'] ?? 'assigned valuer' }}.</p>
                @endif
            </div>
        @endif

        @if ($asset && ! in_array($asset->valuation_status, ['completed'], true) && ! $openAssignment)
            <div class="flex flex-wrap gap-3 items-end border-t border-gray-100 pt-4">
                <form method="POST" action="{{ route('admin.loan-applications.assign-valuer', $application) }}" class="flex flex-wrap gap-3 items-end flex-1">
                    @csrf
                    <div class="min-w-[220px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Assign valuation partner</label>
                        <select name="vendor_id" required class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Select valuer…</option>
                            @foreach ($valuers ?? [] as $valuer)
                                <option value="{{ $valuer->id }}" @selected(($suggestedValuer?->id ?? null) === $valuer->id)>
                                    {{ $valuer->name }}
                                    @if (! empty($valuer->regions))
                                        · {{ implode(', ', array_slice($valuer->regions, 0, 2)) }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @if ($suggestedValuer ?? null)
                            <p class="text-[11px] text-gray-500 mt-1">Suggested for {{ $application->customer?->region ?? 'borrower region' }}: {{ $suggestedValuer->name }}</p>
                        @endif
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                        <input type="text" name="notes" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Optional inspection notes">
                    </div>
                    <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-lg text-sm">Assign</button>
                </form>
                <form method="POST" action="{{ route('admin.loan-applications.assign-valuer', $application) }}">
                    @csrf
                    <input type="hidden" name="auto" value="1">
                    <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-2 rounded-lg text-sm">Auto-assign nearest</button>
                </form>
            </div>
        @elseif ($openAssignment)
            <p class="text-sm text-amber-800 border-t border-gray-100 pt-4">Open valuation with <strong>{{ $openAssignment->vendor?->name }}</strong> ({{ ucfirst(str_replace('_', ' ', $openAssignment->status)) }}).</p>
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
