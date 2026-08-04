@php
    $person = $person ?? 'borrower';
    $who = $person === 'guarantor' ? 'guarantor' : 'borrower';
    $isGuarantor = $person === 'guarantor';
    $assets = collect($review['customer_assets'] ?? []);
    $canRequestDocs = auth()->user()?->hasPermission('applications.request_documents');
    $collateralPresets = \App\Services\ApplicationDocumentRequestService::COLLATERAL_PRESET_LABELS;
    $typeOptions = \App\Models\CustomerAsset::typeOptions();
    $typeIcons = \App\Models\CustomerAsset::typeIcons();
@endphp

<section
    class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
    x-data="{ openAsset: null, lightbox: null }"
    @keydown.escape.window="if (lightbox) { lightbox = null } else { openAsset = null }"
>
    <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Collateral</p>
        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">
            {{ $isGuarantor ? 'Guarantor collateral' : 'Borrower collateral' }}
        </h2>
        <p class="text-xs text-gray-500 mt-0.5">
            @if ($isGuarantor)
                Assets on the guarantor’s profile. Read-only here — request updates below when something is missing.
            @else
                Assets on the borrower’s profile. Read-only here — request updates below when something is missing.
            @endif
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
            @if ($isGuarantor)
                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200/80 px-4 py-3 text-xs text-slate-700">
                    Guarantor assets support the guarantee. They are not automatically pledged to this loan.
                    If underwriting wants to use one as loan collateral, that needs explicit guarantor consent and a separate pledge step.
                </div>
            @endif

            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-3">
                    {{ $assets->count() }} saved
                </p>
                <div class="-mx-1 px-1 flex gap-4 overflow-x-auto snap-x snap-mandatory pb-1"
                     style="scrollbar-width: thin;">
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
                                        'label' => __('borrower.profile.collateral_fields.'.$field['key']),
                                        'value' => $val,
                                    ] : null;
                                })
                                ->filter()
                                ->take(4)
                                ->values();
                        @endphp
                        <div class="snap-start shrink-0 w-[80%] sm:w-72">
                            <div class="overflow-hidden rounded-2xl ring-1 ring-gray-200/80 bg-white h-full flex flex-col">
                                <div class="relative h-40 bg-gradient-to-br from-brand-muted/60 to-white">
                                    @if ($thumb)
                                        <img src="{{ asset('storage/'.$thumb) }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                    @else
                                        <span class="absolute inset-0 grid place-items-center text-5xl" aria-hidden="true">{{ $typeIcons[$asset->asset_type] ?? '📦' }}</span>
                                    @endif
                                    <span class="absolute top-3 left-3 inline-flex items-center gap-1 text-[11px] font-semibold bg-white/90 backdrop-blur px-2.5 py-1 rounded-full text-gray-800 ring-1 ring-black/5">
                                        {{ $typeIcons[$asset->asset_type] ?? '📦' }} {{ $typeLabel }}
                                    </span>
                                    <span class="absolute top-3 right-3 inline-flex items-center gap-1 text-[11px] font-semibold bg-emerald-500/90 text-white px-2.5 py-1 rounded-full">
                                        Saved
                                    </span>
                                    @if (count($gallery) > 1)
                                        <span class="absolute bottom-3 right-3 text-[11px] font-semibold bg-black/55 text-white px-2 py-0.5 rounded-full">
                                            {{ count($gallery) }} 📷
                                        </span>
                                    @endif
                                </div>
                                <div class="p-4 flex-1 flex flex-col">
                                    <h3 class="font-bold text-gray-900 truncate">{{ $asset->label }}</h3>
                                    @if ($asset->estimated_value)
                                        <p class="text-sm text-brand font-semibold mt-1 tabular-nums">{{ format_money($asset->estimated_value) }}</p>
                                    @elseif (! $isGuarantor)
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
                                    @elseif (filled($asset->description))
                                        <p class="mt-2 text-xs text-gray-500 line-clamp-2">{{ $asset->description }}</p>
                                    @endif
                                    <button type="button" @click="openAsset = {{ $asset->id }}"
                                            class="mt-4 inline-flex items-center justify-center w-full bg-gray-900 hover:bg-black text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                        View
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Read-only detail drawers (profile layout, no edit) --}}
            @foreach ($assets as $asset)
                @php
                    $meta = $asset->metadata ?? [];
                    $gallery = $asset->galleryPaths();
                    $ownershipDoc = $meta['ownership_document_path'] ?? null;
                    $insuranceDoc = $meta['insurance_document_path'] ?? null;
                    $typeLabel = $typeOptions[$asset->asset_type] ?? $asset->asset_type;
                    $detailRows = collect(\App\Models\CustomerAsset::detailFieldsFor($asset->asset_type))
                        ->map(function ($field) use ($asset) {
                            $val = ($field['column'] ?? false) ? $asset->{$field['key']} : $asset->detail($field['key']);

                            return filled($val) ? [
                                'label' => __('borrower.profile.collateral_fields.'.$field['key']),
                                'value' => $val,
                            ] : null;
                        })
                        ->filter()
                        ->values();
                    if ($asset->asset_type === 'vehicle') {
                        foreach (['insurance_policy_number', 'insurance_expires_at'] as $insKey) {
                            $insVal = $asset->detail($insKey);
                            if (filled($insVal)) {
                                $detailRows->push([
                                    'label' => __('borrower.profile.collateral_fields.'.$insKey),
                                    'value' => $insVal,
                                ]);
                            }
                        }
                    }
                    $gallerySlides = collect($asset->photo_paths ?? [])
                        ->values()
                        ->map(fn ($path) => ['url' => asset('storage/'.$path)])
                        ->all();
                    if ($meta['person_with_asset_path'] ?? null) {
                        $gallerySlides[] = [
                            'url' => asset('storage/'.$meta['person_with_asset_path']),
                            'label' => __('borrower.profile.person_with_asset'),
                        ];
                    }
                @endphp
                <div x-show="openAsset === {{ $asset->id }}" x-cloak x-transition
                     class="fixed inset-0 z-[80] bg-black/60 flex items-end sm:items-center justify-center p-0 sm:p-4"
                     @click.self="openAsset = null">
                    <div class="bg-white w-full sm:max-w-2xl sm:rounded-2xl rounded-t-2xl max-h-[92vh] overflow-y-auto shadow-xl">
                        <div class="sticky top-0 bg-white/95 backdrop-blur px-5 py-4 border-b border-gray-100 flex items-center justify-between z-10">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $typeLabel }}</p>
                                <h2 class="font-bold text-gray-900 truncate">{{ $asset->label }}</h2>
                            </div>
                            <button type="button" @click="openAsset = null" class="shrink-0 size-9 grid place-items-center rounded-full hover:bg-gray-100 text-gray-500 text-xl" aria-label="Close">×</button>
                        </div>

                        <div class="p-5 space-y-6">
                            <div class="rounded-2xl bg-brand-muted/25 ring-1 ring-brand/10 p-4 space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs uppercase tracking-widest text-brand font-semibold">Details</p>
                                    <span class="text-[10px] font-semibold uppercase tracking-widest text-gray-500 bg-white px-2 py-1 rounded-full ring-1 ring-gray-200">Read only</span>
                                </div>
                                <dl class="grid sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <dt class="text-[11px] font-medium text-gray-600 mb-0.5">Collateral name</dt>
                                        <dd class="text-sm font-semibold text-gray-900">{{ $asset->label }}</dd>
                                    </div>
                                    @foreach ($detailRows as $row)
                                        <div>
                                            <dt class="text-[11px] font-medium text-gray-600 mb-0.5">{{ $row['label'] }}</dt>
                                            <dd class="text-sm font-semibold text-gray-900 break-words">{{ $row['value'] }}</dd>
                                        </div>
                                    @endforeach
                                    @if (filled($asset->description))
                                        <div class="sm:col-span-2">
                                            <dt class="text-[11px] font-medium text-gray-600 mb-0.5">Description</dt>
                                            <dd class="text-sm text-gray-800 whitespace-pre-wrap">{{ $asset->description }}</dd>
                                        </div>
                                    @endif
                                    @if ($asset->estimated_value)
                                        <div>
                                            <dt class="text-[11px] font-medium text-gray-600 mb-0.5">Valuation</dt>
                                            <dd class="text-sm font-semibold text-brand tabular-nums">{{ format_money($asset->estimated_value) }}</dd>
                                        </div>
                                    @elseif (! $isGuarantor)
                                        <div class="sm:col-span-2">
                                            <p class="text-[11px] text-gray-500">Valuation is set by the team after review when this asset is pledged to a loan.</p>
                                        </div>
                                    @endif
                                </dl>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-1">Photos</p>
                                <p class="text-[11px] text-gray-400 mb-3">Swipe across photos · tap to enlarge</p>
                                @if (count($gallerySlides))
                                    <div x-data="{
                                            gIndex: 0,
                                            slides: @js($gallerySlides),
                                            touchStartX: 0,
                                            prev() { this.gIndex = (this.gIndex - 1 + this.slides.length) % this.slides.length },
                                            next() { this.gIndex = (this.gIndex + 1) % this.slides.length },
                                            onTouchStart(e) { this.touchStartX = e.changedTouches[0].screenX },
                                            onTouchEnd(e) {
                                                const diff = e.changedTouches[0].screenX - this.touchStartX;
                                                if (Math.abs(diff) > 50) diff > 0 ? this.prev() : this.next();
                                            }
                                         }" class="space-y-3">
                                        <div class="relative aspect-square sm:aspect-[4/3] rounded-2xl overflow-hidden ring-1 ring-gray-200 bg-gray-100"
                                             @touchstart="onTouchStart($event)" @touchend="onTouchEnd($event)">
                                            <template x-for="(slide, i) in slides" :key="'ag-'+i">
                                                <div class="absolute inset-0 transition-opacity duration-300"
                                                     :class="i === gIndex ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'">
                                                    <button type="button" @click="lightbox = slide.url"
                                                            class="absolute inset-0 block w-full h-full cursor-zoom-in">
                                                        <img :src="slide.url" alt="" class="h-full w-full object-cover">
                                                    </button>
                                                    <p x-show="slide.label" x-cloak class="absolute bottom-3 left-3 z-20 text-[11px] font-semibold bg-black/55 text-white px-2 py-0.5 rounded-full" x-text="slide.label"></p>
                                                </div>
                                            </template>
                                            <template x-if="slides.length > 1">
                                                <div>
                                                    <button type="button" @click="prev()"
                                                            class="absolute left-2 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 shadow grid place-items-center text-gray-800"
                                                            aria-label="Previous photo">‹</button>
                                                    <button type="button" @click="next()"
                                                            class="absolute right-2 top-1/2 -translate-y-1/2 z-20 size-9 rounded-full bg-white/90 shadow grid place-items-center text-gray-800"
                                                            aria-label="Next photo">›</button>
                                                    <div class="absolute top-3 right-3 z-20 rounded-full bg-black/45 text-white text-xs px-2.5 py-1"
                                                         x-text="(gIndex + 1) + ' / ' + slides.length"></div>
                                                </div>
                                            </template>
                                        </div>
                                        <div x-show="slides.length > 1" class="flex gap-2 overflow-x-auto pb-1">
                                            <template x-for="(slide, i) in slides" :key="'agt-'+i">
                                                <button type="button" @click="gIndex = i"
                                                        class="shrink-0 size-14 rounded-lg overflow-hidden ring-2 transition"
                                                        :class="gIndex === i ? 'ring-brand' : 'ring-transparent opacity-70 hover:opacity-100'">
                                                    <img :src="slide.url" alt="" class="w-full h-full object-cover">
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-xs text-gray-500">No photos uploaded yet.</p>
                                @endif
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-2xl ring-1 ring-gray-200 p-4">
                                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-2">Ownership document</p>
                                    @if ($ownershipDoc)
                                        @if (str_ends_with(strtolower($ownershipDoc), '.pdf'))
                                            <a href="{{ asset('storage/'.$ownershipDoc) }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-2 text-sm text-brand font-semibold">📄 View document</a>
                                        @else
                                            <button type="button" @click="lightbox = @js(asset('storage/'.$ownershipDoc))"
                                                    class="block h-28 w-28 rounded-xl overflow-hidden ring-1 ring-gray-200 cursor-zoom-in">
                                                <img src="{{ asset('storage/'.$ownershipDoc) }}" alt="" class="h-full w-full object-cover">
                                            </button>
                                        @endif
                                    @else
                                        <p class="text-xs text-gray-500">No document on file.</p>
                                    @endif
                                </div>

                                @if ($asset->asset_type === 'vehicle')
                                    <div class="rounded-2xl ring-1 ring-brand/20 bg-brand-muted/20 p-4">
                                        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">Comprehensive insurance</p>
                                        @if ($insuranceDoc)
                                            @if (str_ends_with(strtolower($insuranceDoc), '.pdf'))
                                                <a href="{{ asset('storage/'.$insuranceDoc) }}" target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-2 text-sm text-brand font-semibold">📄 View document</a>
                                            @else
                                                <button type="button" @click="lightbox = @js(asset('storage/'.$insuranceDoc))"
                                                        class="block h-28 w-28 rounded-xl overflow-hidden ring-1 ring-gray-200 cursor-zoom-in">
                                                    <img src="{{ asset('storage/'.$insuranceDoc) }}" alt="" class="h-full w-full object-cover">
                                                </button>
                                            @endif
                                        @else
                                            <p class="text-xs text-amber-700">No insurance certificate on file.</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
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

    <div x-show="lightbox" x-cloak x-transition
         class="fixed inset-0 z-[90] bg-black/85 flex items-center justify-center p-4"
         @click.self="lightbox = null">
        <button type="button" @click="lightbox = null" class="absolute top-4 right-4 size-10 rounded-full bg-white/10 text-white text-2xl grid place-items-center" aria-label="Close">×</button>
        <img :src="lightbox" alt="" class="max-h-[90vh] max-w-full rounded-xl object-contain">
    </div>
</section>
