@php
    $application = $record;
    $assets = $application->relationLoaded('collateralAssets')
        ? $application->collateralAssets
        : $application->collateralAssets()->with('customerAsset')->get();
    $asset = $assets->firstWhere('is_primary', true) ?? $assets->first() ?? $application->collateralAsset;
    $isAb = app(\App\Services\AssetBackedLoanService::class)->isAssetBackedApplication($application);
    $openAssignment = $application->valuationAssignments
        ->first(fn ($a) => in_array($a->status, ['assigned', 'in_progress'], true));
@endphp

@if ($isAb)
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Asset-backed collateral</h3>
                <p class="text-xs text-gray-500 mt-1">Accept or decline each pledged asset. Vehicle comprehensive insurance must be on file and verified during underwriting. Formal offer issues only after valuation + CRB.</p>
            </div>
            <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('set-review-tab', { detail: 'borrower' })); setTimeout(() => document.getElementById('review-verification')?.scrollIntoView({behavior:'smooth'}), 50)"
                    class="shrink-0 text-xs font-semibold text-brand hover:underline">
                Need face photo retake? Open Borrower tab →
            </button>
        </div>

        @perm('applications.request_documents')
            @if ($assets->isNotEmpty())
                <div class="mb-4">
                    <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $application) }}">
                        @csrf
                        <input type="hidden" name="type" value="document">
                        <input type="hidden" name="presets[]" value="New collateral photo">
                        <input type="hidden" name="instructions" value="Please re-upload clear, recent photos of ALL your pledged collateral assets (front, back, sides).">
                        <button type="submit" class="text-xs font-semibold rounded-lg px-3 py-1.5 bg-amber-100 hover:bg-amber-200 text-amber-900 ring-1 ring-amber-200">
                            ↻ Request all asset photos retaken
                        </button>
                    </form>
                </div>
            @endif
        @endperm

        @forelse ($assets as $row)
            @php
                $ca = $row->customerAsset;
                $hasInsurance = $row->hasComprehensiveInsurance();
                $isVehicle = in_array($row->asset_type, ['vehicle', 'motorcycle', 'saloon_car', 'suv', 'truck'], true);
                $gallery = $ca?->galleryPaths() ?? [];
                $ownershipDoc = $ca?->metadata['ownership_document_path'] ?? null;
                $insuranceDoc = $ca?->metadata['insurance_document_path'] ?? null;
            @endphp
            <div class="rounded-xl ring-1 ring-gray-200 p-4 mb-4 {{ $row->uw_status === 'declined' ? 'opacity-70 bg-gray-50' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="font-semibold text-gray-900">
                            {{ $ca?->label ?? ($row->description ?: 'Collateral #'.$row->id) }}
                            @if ($row->is_primary)
                                <span class="ml-1 text-[10px] uppercase tracking-widest text-brand font-semibold">Primary</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500 mt-1 capitalize">
                            {{ str_replace('_', ' ', $row->asset_type) }}
                            · UW: {{ ucfirst($row->uw_status ?? 'pending') }}
                            · Valuation: {{ str_replace('_', ' ', $row->valuation_status ?? '—') }}
                        </p>
                    </div>
                    @if ($isVehicle)
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $hasInsurance ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-rose-50 text-rose-800 ring-1 ring-rose-200' }}">
                            {{ $hasInsurance ? 'Comprehensive insurance on file' : 'Missing comprehensive insurance' }}
                        </span>
                    @endif
                </div>

                {{-- Photo gallery --}}
                <div class="mb-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-2">Photos</p>
                    @if (! empty($gallery))
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                            @foreach ($gallery as $i => $path)
                                @php $photoLabel = 'Photo '.($i + 1); @endphp
                                <button type="button"
                                        onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$path)), @js($photoLabel), 'image')"
                                        class="block group text-left">
                                    <img src="{{ asset('storage/'.$path) }}" alt="{{ $photoLabel }}"
                                         class="w-full h-20 rounded-lg object-cover ring-1 ring-gray-200 group-hover:ring-amber-400 transition cursor-zoom-in">
                                </button>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400">No photos on file yet.</p>
                    @endif
                </div>

                <dl class="grid sm:grid-cols-2 gap-3 text-sm mb-4">
                    @if ($row->description)<div class="sm:col-span-2"><dt class="text-xs text-gray-500">Description</dt><dd>{{ $row->description }}</dd></div>@endif
                    @if ($row->market_value)<div><dt class="text-xs text-gray-500">Market value</dt><dd>{{ format_money($row->market_value) }}</dd></div>@endif
                    @if ($row->forced_sale_value)<div><dt class="text-xs text-gray-500">Forced sale value</dt><dd>{{ format_money($row->forced_sale_value) }}</dd></div>@endif
                    @if ($row->max_loan_amount)<div><dt class="text-xs text-gray-500">Max loan (LTV)</dt><dd class="font-semibold">{{ format_money($row->max_loan_amount) }} @ {{ $row->ltv_percent }}%</dd></div>@endif
                    @if ($ownershipDoc)
                        <div>
                            <dt class="text-xs text-gray-500">Ownership document</dt>
                            <dd>
                                <button type="button" onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$ownershipDoc)), 'Ownership document', @js(str_ends_with(strtolower($ownershipDoc), '.pdf') ? 'pdf' : 'image'))"
                                        class="text-brand font-semibold hover:underline">View</button>
                            </dd>
                        </div>
                    @endif
                    @if ($insuranceDoc)
                        <div>
                            <dt class="text-xs text-gray-500">Insurance document</dt>
                            <dd>
                                <button type="button" onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$insuranceDoc)), 'Insurance document', @js(str_ends_with(strtolower($insuranceDoc), '.pdf') ? 'pdf' : 'image'))"
                                        class="text-brand font-semibold hover:underline">View</button>
                            </dd>
                        </div>
                    @endif
                    @if ($row->uw_notes)
                        <div class="sm:col-span-2"><dt class="text-xs text-gray-500">UW notes</dt><dd>{{ $row->uw_notes }}</dd></div>
                    @endif
                </dl>

                @perm('applications.request_documents')
                    <div class="flex flex-wrap gap-2 mb-4 border-t border-gray-100 pt-3">
                        <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $application) }}">
                            @csrf
                            <input type="hidden" name="type" value="document">
                            <input type="hidden" name="presets[]" value="New collateral photo">
                            <button type="submit" class="text-xs font-semibold rounded-lg px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 ring-1 ring-gray-200">
                                Request retake: {{ $ca?->label ?? 'this asset' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $application) }}">
                            @csrf
                            <input type="hidden" name="type" value="document">
                            <input type="hidden" name="presets[]" value="Updated collateral ownership document">
                            <button type="submit" class="text-xs font-semibold rounded-lg px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 ring-1 ring-gray-200">
                                Request ownership document
                            </button>
                        </form>
                        @if ($isVehicle)
                            <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $application) }}">
                                @csrf
                                <input type="hidden" name="type" value="document">
                                <input type="hidden" name="presets[]" value="Updated collateral insurance certificate">
                                <button type="submit" class="text-xs font-semibold rounded-lg px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 ring-1 ring-gray-200">
                                    Request insurance certificate
                                </button>
                            </form>
                        @endif
                    </div>
                @endperm

                <form method="POST" action="{{ route('admin.loan-applications.collateral.uw-status', [$application, $row]) }}" class="flex flex-wrap gap-2 items-end border-t border-gray-100 pt-3">
                    @csrf
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Underwriting decision</label>
                        <select name="uw_status" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="pending" @selected(($row->uw_status ?? 'pending') === 'pending')>Pending</option>
                            <option value="accepted" @selected(($row->uw_status ?? '') === 'accepted')>Accept asset</option>
                            <option value="declined" @selected(($row->uw_status ?? '') === 'declined')>Decline asset</option>
                        </select>
                    </div>
                    <div class="flex-[2] min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                        <input type="text" name="uw_notes" value="{{ $row->uw_notes }}" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Reason if declined…">
                    </div>
                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">Save</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-gray-500 mb-4">Borrower has not submitted asset details yet.</p>
        @endforelse

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
            @php
                $assignList = collect(($valuers->isNotEmpty() ? $valuers : ($allValuers ?? $valuers)) ?? []);
            @endphp
            @if ($assignList->isEmpty())
                <div class="border-t border-gray-100 pt-4 space-y-2">
                    <p class="text-sm text-rose-800">No active valuers in the system. Partners Management will enroll one, then set coverage (Nationwide or this region) and activate the portal PIN. Waiting files auto-match after that.</p>
                    @include('admin.loan-applications.review._request_partner_coverage', [
                        'coverageApplication' => $application,
                        'coverageCategory' => 'valuer',
                        'coverageRegion' => $application->customer?->region,
                        'enrollLabel' => 'Add valuer',
                    ])
                </div>
            @else
            <div class="flex flex-wrap gap-3 items-end border-t border-gray-100 pt-4">
                <form method="POST" action="{{ route('admin.loan-applications.assign-valuer', $application) }}" class="flex flex-wrap gap-3 items-end flex-1">
                    @csrf
                    <div class="min-w-[220px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Assign valuation partner</label>
                        <select name="vendor_id" required class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Select valuer…</option>
                            @foreach ($assignList as $valuer)
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
                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">Assign</button>
                </form>
                <form method="POST" action="{{ route('admin.loan-applications.assign-valuer', $application) }}">
                    @csrf
                    <input type="hidden" name="auto" value="1">
                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">Auto-assign nearest</button>
                </form>
            </div>
            @endif
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
                <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">Add fee request</button>
            </div>
        </form>
    </div>
@endif
