@php
    $person = $person ?? 'borrower';
    $who = $person === 'guarantor' ? 'guarantor' : 'borrower';
    $assets = collect($review['customer_assets'] ?? []);
    $canRequestDocs = auth()->user()?->hasPermission('applications.request_documents');
    $collateralPresets = \App\Services\ApplicationDocumentRequestService::COLLATERAL_PRESET_LABELS;
    $typeOptions = \App\Models\CustomerAsset::typeOptions();
    $typeIcons = \App\Models\CustomerAsset::typeIcons();
@endphp

<section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
    <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Collateral</p>
        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">
            {{ $person === 'guarantor' ? 'Guarantor collateral' : 'Borrower collateral' }}
        </h2>
        <p class="text-xs text-gray-500 mt-0.5">
            Uploaded collateral stays visible for screening and committee. Request more when needed.
        </p>
    </div>

    <div class="p-5 sm:p-6 space-y-5">
        @if ($assets->isEmpty())
            <div class="rounded-xl bg-amber-50/60 ring-1 ring-amber-100 px-4 py-4">
                <p class="text-sm font-semibold text-amber-950">No collateral on file</p>
                <p class="text-xs text-amber-900/80 mt-1">
                    This {{ $who }} has not uploaded collateral assets yet.
                </p>
            </div>
        @else
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-3">
                    {{ $assets->count() }} saved
                </p>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($assets as $asset)
                        @php
                            $thumb = $asset->thumbnailPath();
                            $gallery = $asset->galleryPaths();
                            $typeLabel = $typeOptions[$asset->asset_type] ?? $asset->asset_type;
                            $cardDetails = collect(\App\Models\CustomerAsset::detailFieldsFor($asset->asset_type))
                                ->map(function ($field) use ($asset) {
                                    $val = ($field['column'] ?? false) ? $asset->{$field['key']} : $asset->detail($field['key']);

                                    return filled($val) ? [
                                        'key' => $field['key'],
                                        'label' => str_replace('_', ' ', $field['key']),
                                        'value' => $val,
                                    ] : null;
                                })
                                ->filter()
                                ->take(4)
                                ->values();
                        @endphp
                        <div class="rounded-xl overflow-hidden ring-1 ring-gray-200 bg-white flex flex-col">
                            <div class="relative h-36 bg-gradient-to-br from-brand-muted/60 to-white">
                                @if ($thumb)
                                    <img src="{{ asset('storage/'.$thumb) }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                @else
                                    <span class="absolute inset-0 grid place-items-center text-4xl" aria-hidden="true">{{ $typeIcons[$asset->asset_type] ?? '📦' }}</span>
                                @endif
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1 text-[11px] font-semibold bg-white/90 backdrop-blur px-2.5 py-1 rounded-full text-gray-800 ring-1 ring-black/5">
                                    {{ $typeIcons[$asset->asset_type] ?? '📦' }} {{ $typeLabel }}
                                </span>
                                <span class="absolute top-3 right-3 inline-flex items-center gap-1 text-[11px] font-semibold bg-emerald-500/90 text-white px-2.5 py-1 rounded-full">
                                    Saved
                                </span>
                                @if (count($gallery) > 1)
                                    <span class="absolute bottom-3 right-3 text-[11px] font-semibold bg-black/55 text-white px-2 py-0.5 rounded-full">
                                        {{ count($gallery) }} photos
                                    </span>
                                @endif
                            </div>
                            <div class="p-4 flex-1 flex flex-col">
                                <h3 class="font-bold text-gray-900 truncate">{{ $asset->label }}</h3>
                                @if ($asset->estimated_value)
                                    <p class="text-sm text-brand font-semibold mt-1 tabular-nums">{{ format_money($asset->estimated_value) }}</p>
                                @else
                                    <p class="text-xs text-gray-500 mt-1">Awaiting valuation by our team</p>
                                @endif
                                @if ($cardDetails->isNotEmpty())
                                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
                                        @foreach ($cardDetails as $detail)
                                            <div class="min-w-0">
                                                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold truncate">{{ $detail['label'] }}</dt>
                                                <dd class="text-xs font-semibold text-gray-900 truncate" title="{{ $detail['value'] }}">{{ $detail['value'] }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @elseif (filled($asset->registration_number))
                                    <p class="mt-2 text-xs text-gray-600">
                                        <span class="text-gray-500">Registration</span>
                                        <span class="font-semibold text-gray-900 ml-1">{{ $asset->registration_number }}</span>
                                    </p>
                                @elseif (filled($asset->description))
                                    <p class="mt-2 text-xs text-gray-500 line-clamp-2">{{ $asset->description }}</p>
                                @endif

                                @if (count($gallery) > 0)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach (array_slice($gallery, 0, 4) as $path)
                                            <a href="{{ asset('storage/'.$path) }}" target="_blank" rel="noopener"
                                               class="block size-12 rounded-lg overflow-hidden ring-1 ring-gray-200 bg-gray-50">
                                                <img src="{{ asset('storage/'.$path) }}" alt="" class="h-full w-full object-cover">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($canRequestDocs)
            <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}" class="space-y-3 {{ $assets->isNotEmpty() ? 'pt-2 border-t border-brand/10' : '' }}">
                @csrf
                <input type="hidden" name="type" value="document">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">
                    Request collateral from {{ $who }}
                </p>
                <div class="grid sm:grid-cols-2 gap-2">
                    @foreach ($collateralPresets as $preset)
                        <label class="flex items-start gap-2 text-sm text-gray-700 bg-emerald-50/80 rounded-xl px-3 py-2 ring-1 ring-brand/10">
                            <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                            <span>{{ $preset }}</span>
                        </label>
                    @endforeach
                </div>
                <textarea name="instructions" rows="2" maxlength="2000"
                          placeholder="Optional note shown to the {{ $who }}"
                          class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5"></textarea>
                <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                    Request collateral
                </button>
            </form>
        @endif
    </div>
</section>
