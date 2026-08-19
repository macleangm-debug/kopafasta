@php
    $person = $person ?? 'borrower';
    $isGroupFile = filled($record->loan_group_id);
    $who = match ($person) {
        'guarantor' => 'guarantor',
        'member' => 'member',
        default => $isGroupFile ? 'group leader' : 'borrower',
    };
    $isGuarantor = $person === 'guarantor';
    $isMember = $person === 'member';
    $pledgeRows = collect();
    $profileAssets = collect($review['customer_assets'] ?? [])->each(fn ($asset) => $asset->loadMissing('customer'));
    $record->loadMissing(['collateralAssets.customerAsset.customer', 'valuationAssignments.vendor', 'loanGroup.members.customer']);
    if ($isGuarantor || $isMember) {
        $pledgeRows = collect();
    } else {
        $pledgeRows = collect($record->collateralAssets);
    }
    $pledgeByAssetId = $pledgeRows->keyBy(fn ($row) => (int) $row->customer_asset_id);
    $assetService = app(\App\Services\CustomerAssetService::class);
    $onLoanAssetId = $assetService->designatedAssetId($record);
    $onLoanCount = $onLoanAssetId ? 1 : 0;
    $assets = $profileAssets
        ->concat($pledgeRows->map(fn ($row) => $row->customerAsset)->filter())
        ->unique(fn ($asset) => (int) $asset->id)
        ->sortBy(fn ($asset) => $pledgeByAssetId->has((int) $asset->id) ? 0 : 1)
        ->values()
        ->each(fn ($asset) => $asset->loadMissing('customer'));
    $canRequestDocs = auth()->user()?->hasPermission('applications.request_documents');
    $collateralPresets = \App\Services\ApplicationDocumentRequestService::COLLATERAL_PRESET_LABELS;
    $typeOptions = \App\Models\CustomerAsset::typeOptions();
    $typeIcons = \App\Models\CustomerAsset::typeIcons();
    $isAb = app(\App\Services\AssetBackedLoanService::class)->isAssetBackedApplication($record);
    $openValuation = collect($record->valuationAssignments ?? [])
        ->first(fn ($a) => in_array($a->status, ['assigned', 'in_progress'], true));
    $pledgedForValuation = $pledgeRows->first(fn ($row) => ($row->uw_status ?? '') !== \App\Models\LoanApplicationAsset::UW_DECLINED);
    $showValuerCta = ! $isGuarantor && ! $isMember && $pledgedForValuation && ! $isAb;
    $csSvc = app(\App\Services\CollateralSecureService::class);
    $csState = $csSvc->state($record);
    $coverage = app(\App\Services\CollateralCoverageService::class)->forApplication($record);
    $valuationFeeDue = $csSvc->needsValuationFeePayment($record)
        || ($csState['status'] ?? '') === \App\Services\CollateralSecureService::STATUS_AWAITING_VALUATION_FEE;
    $activeMemberCount = collect($record->loanGroup?->members ?? [])
        ->filter(fn ($m) => strtolower((string) ($m->role ?? 'member')) !== 'leader'
            && (int) $m->customer_id !== (int) $record->customer_id
            && ($m->member_status ?? 'active') === 'active')
        ->count();
    $ownerRoleFor = function ($asset) use ($record, $isGuarantor, $isMember, $isGroupFile) {
        $ownerId = (int) ($asset->customer_id ?? 0);
        if ($isGuarantor) {
            return 'Guarantor';
        }
        if ($ownerId === (int) $record->customer_id) {
            return $isGroupFile ? 'Group leader' : 'Borrower';
        }
        if ($isMember) {
            return 'Member';
        }

        return 'Member';
    };
    $isCreditManagement = in_array((string) ($record->current_stage ?? ''), [
        'approval',
        'post_approval_fees',
        'awaiting_disbursement_details',
        'contract_generation',
        'disbursement',
    ], true) || $record->status === 'disbursed' || $record->hasActiveFacility();
@endphp

<section
    class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
    x-data="{ openAsset: null, lightbox: null, openSecure: false }"
    @keydown.escape.window="if (lightbox) { lightbox = null } else { openAsset = null }"
>
    <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Collateral</p>
        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">
            {{ $isGuarantor ? 'Guarantor collateral' : ($isMember ? 'Member collateral' : ($isGroupFile ? 'Group leader collateral' : 'Borrower collateral')) }}
        </h2>
        <p class="text-xs text-gray-500 mt-0.5">
            @if ($isGuarantor)
                Assets on the guarantor’s profile. Read-only here — request updates below when something is missing.
            @elseif ($isMember)
                This member’s own profile assets. Loan collateral for the group file is on the leader’s desk.
            @else
                Assets pledged on this loan, marked with who they belong to. Request starts with the {{ $who }}.
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
                    @if (! $isGuarantor && $onLoanCount > 0)
                        · {{ $onLoanCount }} on this loan
                    @endif
                </p>
                <div class="-mx-1 px-1 flex gap-4 overflow-x-auto snap-x snap-mandatory pb-1"
                     style="scrollbar-width: thin;">
                    @foreach ($assets as $asset)
                        @php
                            $thumb = $asset->thumbnailPath();
                            $gallery = $asset->galleryPaths();
                            $typeLabel = $typeOptions[$asset->asset_type] ?? $asset->asset_type;
                            $pledge = $pledgeByAssetId->get((int) $asset->id);
                            $pledgeStatus = (string) ($pledge->uw_status ?? '');
                            $isOnThisLoan = $onLoanAssetId && (int) $asset->id === (int) $onLoanAssetId;
                            $pledgeBadge = match (true) {
                                $pledgeStatus === 'declined' => ['Declined', 'bg-rose-500/90 text-white'],
                                $isOnThisLoan => ['On this loan', $pledgeStatus === 'accepted' ? 'bg-emerald-500/90 text-white' : 'bg-brand/90 text-white'],
                                default => ['Saved', 'bg-slate-600/90 text-white'],
                            };
                            $cardDetails = collect(\App\Models\CustomerAsset::detailFieldsFor($asset->asset_type))
                                ->take(4)
                                ->map(function ($field) use ($asset) {
                                    $val = ($field['column'] ?? false) ? $asset->{$field['key']} : $asset->detail($field['key']);
                                    $display = filled($val) ? (string) $val : '—';

                                    return [
                                        'key' => $field['key'],
                                        'label' => __('borrower.profile.collateral_fields.'.$field['key']),
                                        'value' => $display,
                                        'missing' => ! filled($val),
                                    ];
                                })
                                ->values();
                            $previewUrl = $thumb ? asset('storage/'.$thumb) : null;
                        @endphp
                        <div class="snap-start shrink-0 w-[80%] sm:w-72">
                            <div class="overflow-hidden rounded-2xl ring-1 ring-gray-200/80 bg-white h-full flex flex-col">
                                <div class="relative h-40 bg-gradient-to-br from-brand-muted/60 to-white shrink-0">
                                    @if ($thumb)
                                        <img src="{{ $previewUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                                    @else
                                        <span class="absolute inset-0 grid place-items-center text-5xl" aria-hidden="true">{{ $typeIcons[$asset->asset_type] ?? '📦' }}</span>
                                    @endif
                                    <span class="absolute top-3 left-3 inline-flex items-center gap-1 text-[11px] font-semibold bg-white/90 backdrop-blur px-2.5 py-1 rounded-full text-gray-800 ring-1 ring-black/5">
                                        {{ $typeIcons[$asset->asset_type] ?? '📦' }} {{ $typeLabel }}
                                    </span>
                                    <span class="absolute top-3 right-3 inline-flex items-center gap-1 text-[11px] font-semibold {{ $pledgeBadge[1] }} px-2.5 py-1 rounded-full">
                                        {{ $pledgeBadge[0] }}
                                    </span>
                                    @if ($previewUrl)
                                        <div class="absolute bottom-3 left-3 z-10">
                                            <x-admin.document-preview :url="$previewUrl" :label="$asset->label.' photo'" variant="icon" />
                                        </div>
                                    @endif
                                    @if (count($gallery) > 1)
                                        <span class="absolute bottom-3 right-3 text-[11px] font-semibold bg-black/55 text-white px-2 py-0.5 rounded-full">
                                            {{ count($gallery) }} 📷
                                        </span>
                                    @endif
                                </div>
                                <div class="p-4 flex-1 flex flex-col">
                                    <h3 class="font-bold text-gray-900 truncate">{{ $asset->label }}</h3>
                                    <p class="mt-1 text-[11px] font-semibold text-slate-600">
                                        Belongs to {{ $asset->customer?->full_name ?? '—' }} · {{ $ownerRoleFor($asset) }}
                                    </p>
                                    <p class="mt-1 min-h-[1.25rem] {{ $asset->estimated_value ? 'text-sm text-brand font-semibold tabular-nums' : 'text-xs text-gray-500' }}">
                                        @if ($asset->estimated_value)
                                            {{ format_money($asset->estimated_value) }}
                                        @elseif (! $isGuarantor)
                                            Awaiting valuation by our team
                                        @else
                                            &nbsp;
                                        @endif
                                    </p>
                                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 min-h-[4.5rem]">
                                        @foreach ($cardDetails as $detail)
                                            <div class="min-w-0">
                                                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold truncate">{{ $detail['label'] }}</dt>
                                                <dd @class([
                                                    'text-xs font-semibold truncate',
                                                    'text-gray-900' => empty($detail['missing']),
                                                    'text-gray-400' => ! empty($detail['missing']),
                                                ]) title="{{ $detail['value'] }}">{{ $detail['value'] }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                    <div class="mt-auto pt-4 space-y-2">
                                        <button type="button" @click="openAsset = {{ $asset->id }}"
                                                class="inline-flex items-center justify-center w-full bg-gray-900 hover:bg-black text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                            View
                                        </button>
                                        @if (! $isGuarantor && ! $isMember && $canRequestDocs && $pledgeBadge[0] === 'Saved')
                                            <form method="POST" action="{{ route('admin.loan-applications.collateral.use-on-loan', [$record, $asset]) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center justify-center w-full bg-white hover:bg-brand-muted/40 text-brand font-semibold px-4 py-2 rounded-xl text-sm ring-1 ring-brand/20">
                                                    Use on this loan
                                                </button>
                                            </form>
                                        @endif
                                    </div>
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
                            $display = filled($val) ? $val : '—';
                            if (($field['key'] ?? '') === 'insurance_type' && filled($val)) {
                                $display = \App\Models\CustomerAsset::insuranceTypeOptions()[(string) $val] ?? $val;
                            }

                            return [
                                'label' => __('borrower.profile.collateral_fields.'.$field['key']),
                                'value' => $display,
                                'missing' => ! filled($val),
                            ];
                        })
                        ->values();
                    if ($asset->asset_type === 'vehicle') {
                        foreach (['insurance_type', 'insurance_policy_number', 'insurance_expires_at'] as $insKey) {
                            if ($detailRows->contains(fn ($row) => ($row['label'] ?? '') === __('borrower.profile.collateral_fields.'.$insKey))) {
                                continue;
                            }
                            $insVal = $insKey === 'insurance_type'
                                ? $asset->insuranceType()
                                : $asset->detail($insKey);
                            $display = filled($insVal) ? $insVal : '—';
                            if ($insKey === 'insurance_type' && filled($insVal)) {
                                $display = \App\Models\CustomerAsset::insuranceTypeOptions()[(string) $insVal] ?? $insVal;
                            }
                            $detailRows->push([
                                'label' => __('borrower.profile.collateral_fields.'.$insKey),
                                'value' => $display,
                                'missing' => ! filled($insVal),
                            ]);
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
                                    <div class="sm:col-span-2">
                                        <dt class="text-[11px] font-medium text-gray-600 mb-0.5">Belongs to</dt>
                                        <dd class="text-sm font-semibold text-gray-900">{{ $asset->customer?->full_name ?? '—' }} · {{ $ownerRoleFor($asset) }}</dd>
                                    </div>
                                    @foreach ($detailRows as $row)
                                        <div>
                                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-0.5">{{ $row['label'] }}</dt>
                                            <dd @class([
                                                'text-sm font-extrabold break-words',
                                                'text-gray-900' => empty($row['missing']),
                                                'text-gray-400 font-semibold' => ! empty($row['missing']),
                                            ])>{{ $row['value'] }}</dd>
                                        </div>
                                    @endforeach
                                    @if (filled($asset->description))
                                        <div class="sm:col-span-2">
                                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-0.5">Description</dt>
                                            <dd class="text-sm font-semibold text-gray-800 whitespace-pre-wrap">{{ $asset->description }}</dd>
                                        </div>
                                    @else
                                        <div class="sm:col-span-2">
                                            <dt class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-0.5">Description</dt>
                                            <dd class="text-sm font-semibold text-gray-400">—</dd>
                                        </div>
                                    @endif
                                    <div>
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-0.5">Valuation</dt>
                                        @if ($asset->estimated_value)
                                            <dd class="text-sm font-extrabold text-brand tabular-nums">{{ format_money($asset->estimated_value) }}</dd>
                                        @else
                                            <dd class="text-sm font-semibold text-gray-400">—</dd>
                                            @unless ($isGuarantor)
                                                <p class="text-[11px] text-gray-500 mt-1">Set by the team after this collateral is pledged.</p>
                                            @endunless
                                        @endif
                                    </div>
                                </dl>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-1">Photos</p>
                                <p class="text-[11px] text-gray-400 mb-3">Swipe across photos · use the preview icon to enlarge</p>
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
                                            },
                                            previewCurrent() {
                                                const slide = this.slides[this.gIndex] || {};
                                                window.kfOpenDocumentPreview(slide.url, slide.label || 'Photo', 'image');
                                            }
                                         }" class="space-y-3">
                                        <div class="relative aspect-square sm:aspect-[4/3] rounded-2xl overflow-hidden ring-1 ring-gray-200 bg-gray-100"
                                             @touchstart="onTouchStart($event)" @touchend="onTouchEnd($event)">
                                            <template x-for="(slide, i) in slides" :key="'ag-'+i">
                                                <div class="absolute inset-0 transition-opacity duration-300"
                                                     :class="i === gIndex ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'">
                                                    <img :src="slide.url" alt="" class="h-full w-full object-cover">
                                                    <p x-show="slide.label" x-cloak class="absolute bottom-3 left-3 z-20 text-[11px] font-semibold bg-black/55 text-white px-2 py-0.5 rounded-full" x-text="slide.label"></p>
                                                </div>
                                            </template>
                                            <button type="button" @click="previewCurrent()"
                                                    class="absolute top-3 left-3 z-30 size-9 grid place-items-center rounded-full bg-black/55 hover:bg-black/75 text-white shadow-sm"
                                                    aria-label="Preview photo">
                                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
                                            </button>
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
                                        <x-admin.document-preview
                                            :url="asset('storage/'.$ownershipDoc)"
                                            label="Ownership document"
                                            variant="thumbnail" />
                                    @else
                                        <p class="text-xs text-gray-500">No document on file.</p>
                                    @endif
                                </div>

                                @if ($asset->asset_type === 'vehicle')
                                    <div class="rounded-2xl ring-1 ring-brand/20 bg-brand-muted/20 p-4">
                                        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">Comprehensive insurance</p>
                                        @if ($insuranceDoc)
                                            <x-admin.document-preview
                                                :url="asset('storage/'.$insuranceDoc)"
                                                label="Insurance certificate"
                                                variant="thumbnail" />
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

        @if ($showValuerCta)
            <div class="rounded-xl bg-amber-50/80 ring-1 ring-amber-200 px-4 py-4 space-y-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-amber-900">Next step for screening</p>
                    <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Send to valuer</h3>
                    <p class="text-xs text-gray-600 mt-1">
                        This is not the AB product. The group leader (or individual borrower) pays the valuation fee on their loan profile — same payment card as membership / group application fee. A valuer is auto-assigned by region only after that payment clears.
                    </p>
                </div>
                @if (data_get($valuationReport ?? null, 'status') === 'completed')
                    <div class="rounded-xl bg-white ring-1 ring-sky-100 px-4 py-3 text-sm space-y-2">
                        <p class="font-semibold text-sky-950">{{ $valuationReport['valuer_name'] ?? 'Valuer' }} · Forced sale value in</p>
                        <dl class="grid sm:grid-cols-2 gap-2 text-xs">
                            <div><dt class="text-sky-800">Forced sale value</dt><dd class="font-semibold">{{ format_money($valuationReport['forced_sale_value'] ?? 0) }}</dd></div>
                            <div><dt class="text-sky-800">This asset can cover (FSV × LTV {{ (int) ($coverage['ltv_percent'] ?? $valuationReport['ltv_percent'] ?? 0) }}%)</dt><dd class="font-semibold">{{ format_money($coverage['max_loan_amount'] ?? $valuationReport['max_loan_amount'] ?? 0) }}</dd></div>
                            <div><dt class="text-sky-800">Requested</dt><dd class="font-semibold">{{ format_money($coverage['requested_amount'] ?? $record->requested_amount) }}</dd></div>
                        </dl>
                        @if (! empty($coverage['sufficient']))
                            @if (($coverage['next'] ?? '') === 'insurance_update')
                                <p class="text-amber-900 text-xs">LTV covers the requested amount. Insurance on the pledged asset is not sufficient — the asset owner must update cover.</p>
                            @else
                                <p class="text-emerald-800 text-xs">LTV covers the requested amount and insurance is in order.</p>
                            @endif
                        @elseif ($coverage)
                            <p class="text-rose-800 text-xs font-semibold">Collateral is not sufficient (shortfall {{ format_money($coverage['shortfall'] ?? 0) }}).</p>
                            <ul class="text-xs text-gray-700 list-disc pl-4 space-y-1">
                                @foreach ($coverage['scenarios'] ?? [] as $scenario)
                                    <li>{{ $scenario['label'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @elseif ($openValuation)
                    @php
                        $valuerAuto = str_contains(strtolower((string) ($openValuation->notes ?? '')), 'auto-assigned');
                    @endphp
                    <div class="rounded-xl bg-white ring-1 ring-amber-100 px-4 py-3 text-sm space-y-1.5">
                        <p class="text-amber-950 font-semibold">
                            Valuation {{ str_replace('_', ' ', $openValuation->status) }}
                            with {{ $openValuation->vendor?->name ?? 'assigned valuer' }}
                            @if ($valuerAuto)
                                <span class="text-[11px] font-semibold text-amber-800">· Auto-assigned</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-600">
                            Phone {{ $openValuation->vendor?->phone ?: '—' }}
                            · Email {{ $openValuation->vendor?->email ?: '—' }}
                        </p>
                        @if ($valuerAuto)
                            <p class="text-xs text-amber-900">
                                Screening uses these details for communication only. The valuer already has the task (matched on customer region, then least open jobs unless settings change that).
                            </p>
                        @endif
                    </div>
                @elseif ($valuationFeeDue)
                    <p class="text-sm text-amber-900">
                        Waiting for the borrower{{ is_group_loan_product($record->product ?? null) ? ' (group leader)' : '' }} to pay the valuation fee. No valuer is assigned yet.
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.loan-applications.request-valuation', $record) }}">
                        @csrf
                        <input type="text" name="notes" class="w-full rounded-lg border-gray-300 text-sm mb-2" placeholder="Optional internal note">
                        <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                            Send to valuer
                        </button>
                    </form>
                @endif
            </div>
        @endif

        @if ($isCreditManagement && ! $isGuarantor && ! $isMember)
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-4 space-y-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-600">Credit management · post approval</p>
                    <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Ownership / transfer documents</h3>
                    <p class="text-xs text-gray-600 mt-1">
                        Title transfer is done after the loan is approved. Screening does not pass or fail this step.
                    </p>
                </div>
                @php
                    $ownershipRows = $pledgeRows
                        ->map(fn ($row) => $row->customerAsset)
                        ->filter()
                        ->unique('id')
                        ->values();
                @endphp
                @forelse ($ownershipRows as $owned)
                    @php $ownPath = $owned->metadata['ownership_document_path'] ?? null; @endphp
                    <div class="rounded-xl bg-white ring-1 ring-slate-100 px-3 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ $owned->label }}</p>
                            <p class="text-xs text-gray-500">{{ $owned->registration_number ?: 'No registration on file' }}</p>
                        </div>
                        @if ($ownPath)
                            <x-admin.document-preview
                                :url="asset('storage/'.$ownPath)"
                                label="Open ownership document"
                                variant="button" />
                        @else
                            <span class="text-xs font-semibold text-amber-800">No ownership document uploaded yet</span>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-gray-500">No pledged assets on this loan yet.</p>
                @endforelse
            </div>
        @endif

        @if ($canRequestDocs)
            @php
                $csBlocking = $csSvc->isOpen($record) || in_array($csState['status'] ?? '', [
                    'secured', 'awaiting_valuer', 'awaiting_valuation_fee', 'collateral_shortfall', 'awaiting_insurance',
                ], true);
            @endphp
            @unless ($csBlocking)
                <div class="space-y-2">
                    <button type="button" @click="openSecure = !openSecure"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                        {{ __('borrower.collateral_secure.admin_start') }}
                        <svg class="size-4 transition-transform" :class="openSecure && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <form method="POST" action="{{ route('admin.loan-applications.request-collateral-secure', $record) }}"
                          x-show="openSecure" x-cloak x-transition
                          class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/15 px-4 py-4 space-y-2">
                        @csrf
                        <p class="text-xs text-gray-600">{{ __('borrower.collateral_secure.admin_start_hint') }}</p>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Optional internal note"
                                  class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5"></textarea>
                        <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                            {{ __('borrower.collateral_secure.admin_start') }}
                        </button>
                    </form>
                </div>
            @else
                @php
                    $selectedId = data_get($csState, 'customer_asset_id');
                    $selectedAsset = $selectedId ? \App\Models\CustomerAsset::query()->find($selectedId) : null;
                @endphp
                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm text-slate-700 space-y-2">
                    <p>
                        Collateral secure status:
                        <span class="font-semibold">{{ str_replace('_', ' ', $csState['status'] ?? '—') }}</span>
                    </p>
                    @if ($selectedAsset)
                        <div class="flex gap-3 items-center pt-1">
                            @if ($selectedAsset->thumbnailPath())
                                <img src="{{ asset('storage/'.$selectedAsset->thumbnailPath()) }}" alt="" class="size-12 rounded-lg object-cover ring-1 ring-gray-200">
                            @endif
                            <div>
                                <p class="font-semibold text-gray-900">{{ $selectedAsset->label }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ \App\Models\CustomerAsset::typeOptions()[$selectedAsset->asset_type] ?? $selectedAsset->asset_type }}
                                    @if ($selectedAsset->detail('insurance_expires_at'))
                                        · Insurance expires {{ $selectedAsset->detail('insurance_expires_at') }}
                                    @endif
                                    @if (data_get($csState, 'source') === 'guarantor')
                                        · Guarantor collateral
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            @endunless
        @endif

        @if ($canRequestDocs)
            <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}" class="space-y-3 {{ $assets->isNotEmpty() ? 'pt-2 border-t border-brand/10' : '' }}">
                @csrf
                <input type="hidden" name="type" value="document">
                <input type="hidden" name="review_person" value="{{ $person }}">
                @if ($isMember)
                    <input type="hidden" name="review_m" value="{{ request('review_m', data_get($review, 'member_row.id')) }}">
                    <input type="hidden" name="subject_customer_id" value="{{ data_get($review, 'customer.id') }}">
                    <input type="hidden" name="loan_group_member_id" value="{{ request('review_m', data_get($review, 'member_row.id')) }}">
                @endif
                @if ($isGuarantor)
                    <input type="hidden" name="review_g" value="{{ request('review_g') }}">
                    <input type="hidden" name="subject_customer_id" value="{{ data_get($review, 'customer.id') }}">
                @endif
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
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                        Request collateral
                    </button>
                    @if ($isGroupFile && ! $isGuarantor && ! $isMember && $activeMemberCount > 0)
                        <button type="submit" name="ask_members" value="1"
                                class="inline-flex text-sm font-semibold text-slate-800 bg-white ring-1 ring-slate-200 hover:bg-slate-50 px-4 py-2.5 rounded-xl">
                            Ask members
                        </button>
                    @endif
                </div>
                @if ($isGroupFile && ! $isGuarantor && ! $isMember && $activeMemberCount > 0)
                    <p class="text-xs text-gray-500">Start with the group leader. If they do not have collateral, ask members.</p>
                @endif
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
