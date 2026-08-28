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
    $onLoanIds = $assetService->onLoanAssetIds($record);
    $onLoanCount = count($onLoanIds);
    $onLoanAssetId = $onLoanIds[0] ?? null;
    $assets = collect($isGuarantor || $isMember
            ? $profileAssets
            : $pledgeRows->map(fn ($row) => $row->customerAsset)->filter()
        )
        ->unique(fn ($asset) => (int) $asset->id)
        ->filter(fn ($asset) => in_array((int) $asset->id, $onLoanIds, true))
        ->values()
        ->each(fn ($asset) => $asset->loadMissing('customer'));
    $canRequestDocs = auth()->user()?->hasPermission('applications.request_documents');
    $collateralPresets = \App\Services\ApplicationDocumentRequestService::COLLATERAL_PRESET_LABELS;
    $typeOptions = \App\Models\CustomerAsset::typeOptions();
    $typeIcons = \App\Models\CustomerAsset::typeIcons();
    $isAb = app(\App\Services\AssetBackedLoanService::class)->isAssetBackedApplication($record);
    $openValuation = collect($record->valuationAssignments ?? [])
        ->first(fn ($a) => in_array($a->status, ['assigned', 'in_progress'], true));
    $completedValuation = collect($record->valuationAssignments ?? [])
        ->first(fn ($a) => $a->status === \App\Models\ValuationAssignment::STATUS_COMPLETED);
    $pledgedForValuation = $pledgeRows->first(fn ($row) => ($row->uw_status ?? '') !== \App\Models\LoanApplicationAsset::UW_DECLINED);
    $showValuerCta = ! $isGuarantor && ! $isMember && $pledgedForValuation && ! $isAb;
    $csSvc = app(\App\Services\CollateralSecureService::class);
    $csState = $csSvc->state($record);
    $coverage = app(\App\Services\CollateralCoverageService::class)->forApplication($record);
    $valuationFeeDue = $csSvc->needsValuationFeePayment($record)
        || ($csState['status'] ?? '') === \App\Services\CollateralSecureService::STATUS_AWAITING_VALUATION_FEE;
    $csStatus = (string) ($csState['status'] ?? '');
    $valuationAlreadyOpened = in_array($csStatus, [
        \App\Services\CollateralSecureService::STATUS_AWAITING_VALUATION_FEE,
        \App\Services\CollateralSecureService::STATUS_AWAITING_VALUER,
        \App\Services\CollateralSecureService::STATUS_SHORTFALL,
        \App\Services\CollateralSecureService::STATUS_AWAITING_INSURANCE,
        \App\Services\CollateralSecureService::STATUS_SECURED,
    ], true);
    $needsManualValuer = $pledgedForValuation && ! $openValuation && ! $valuationFeeDue
        && $csStatus === \App\Services\CollateralSecureService::STATUS_AWAITING_VALUER;
    $valuationComplete = data_get($valuationReport ?? null, 'status') === 'completed'
        || $csStatus === \App\Services\CollateralSecureService::STATUS_SECURED
        || (bool) $completedValuation;
    $showValuationResult = $showValuerCta && $valuationComplete;
    $showValuationProgress = $showValuerCta && ! $valuationComplete && $openValuation;
    $showRequestValuation = $showValuerCta && ! $valuationComplete && ! $openValuation;
    $assignableValuers = ($valuers ?? collect())->isNotEmpty()
        ? ($valuers ?? collect())
        : ($allValuers ?? collect());
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
    $leaderCollateralUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'profiles',
        'tab' => 'collateral',
        'person' => 'borrower',
    ]).'#borrower-file';
    $subjectOnLoan = $profileAssets->filter(fn ($asset) => in_array((int) $asset->id, $onLoanIds, true));
    $referToLeader = ($isGuarantor || $isMember) && $subjectOnLoan->isEmpty();
    $docService = app(\App\Services\ApplicationDocumentRequestService::class);
    $subjectCustomerId = (int) (data_get($review, 'customer.id') ?? $record->customer_id ?? 0);
    $requestFromName = trim((string) (data_get($review, 'customer.first_name')
        ?: data_get($review, 'customer.full_name')
        ?: ($isGroupFile ? 'the group leader' : 'the borrower')));
    $waitingOn = match ($person) {
        'guarantor' => 'Waiting for guarantor',
        'member' => 'Waiting for member',
        default => $isGroupFile ? 'Waiting for group leader' : 'Waiting for borrower',
    };
    $memberReviewId = $person === 'member'
        ? (int) request('review_m', data_get($review, 'member_row.id'))
        : null;
    $openCollateralBatches = collect($documentRequests ?? [])
        ->filter(fn ($req) => $req->needsBorrowerAction() && $docService->borrowerActionKind($req) === 'collateral')
        ->filter(fn ($req) => $docService->targetsReviewSubject(
            $req,
            $person,
            $subjectCustomerId,
            $memberReviewId,
            (int) ($record->customer_id ?? 0),
        ))
        ->groupBy(fn ($req) => implode('|', [
            $req->created_at?->format('Y-m-d H:i'),
            (string) ($req->subject_kind ?? ''),
            (string) ($req->subject_customer_id ?? ''),
            (string) ($req->requested_by ?? ''),
        ]))
        ->values()
        ->map(function ($rows) {
            $first = $rows->first();

            return [
                'ids' => $rows->pluck('id')->all(),
                'labels' => $rows->pluck('label')->unique()->values()->all(),
                'note' => (string) ($first->instructions ?? ''),
                'at' => $first->created_at,
                'can_cancel' => $rows->every(fn ($r) => $r->status === 'pending'),
            ];
        });
    $assetPreviewLimit = 8;
    $assetCardRows = $assets->map(function ($asset) use ($pledgeByAssetId, $onLoanIds, $ownerRoleFor) {
        $pledge = $pledgeByAssetId->get((int) $asset->id);
        $pledgeStatus = (string) ($pledge->uw_status ?? '');
        $isOnThisLoan = in_array((int) $asset->id, $onLoanIds, true);
        $sourceLabel = match (true) {
            $pledgeStatus === 'declined' => 'Declined',
            $isOnThisLoan => 'On this loan',
            default => 'Saved',
        };
        $card = $asset->toCollateralCard([
            'belongs_to' => trim(($asset->customer?->full_name ?? '').' · '.$ownerRoleFor($asset), ' ·'),
        ]);
        if ($asset->isVehicleLike()) {
            $card['registration_number'] = $card['registration_number'] ?: '—';
            $card['make'] = $card['make'] ?: '—';
            $card['year'] = $card['year'] ?: '—';
            $card['chassis'] = $card['chassis'] ?: '—';
        }

        return [
            'id' => (int) $asset->id,
            'label' => (string) $asset->label,
            'asset_type' => (string) $asset->asset_type,
            'type_label' => (string) ($card['type_label'] ?? $asset->asset_type),
            'registration' => (string) ($asset->registration_number ?: ''),
            'owner' => (string) ($asset->customer?->full_name ?? ''),
            'status' => $sourceLabel,
            'card' => $card,
            'source_label' => $sourceLabel,
        ];
    })->values();
    $assetStatusFilters = $assetCardRows->pluck('status')->unique()->values()->all();
@endphp

<section
    id="collateral-desk"
    class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
    x-data="{
        openAsset: null,
        lightbox: null,
        openSecure: false,
        requestOpen: false,
        requestStep: 'pick',
        presets: [],
        note: '',
        askMembers: false,
        showAllAssets: false,
        assetQuery: '',
        assetType: '',
        assetStatus: '',
        assetPage: 1,
        previewLimit: {{ (int) $assetPreviewLimit }},
        pageSize: 6,
        assetRows: {{ \Illuminate\Support\Js::from($assetCardRows) }},
        filteredAssets() {
            const q = this.assetQuery.trim().toLowerCase();
            return this.assetRows.filter((row) => {
                if (this.assetType && row.asset_type !== this.assetType) return false;
                if (this.assetStatus && row.status !== this.assetStatus) return false;
                if (! q) return true;
                return (row.label + ' ' + row.registration + ' ' + row.owner + ' ' + row.type_label + ' ' + row.status).toLowerCase().includes(q);
            });
        },
        visibleAssets() {
            const rows = this.filteredAssets();
            if (rows.length <= this.previewLimit && ! this.showAllAssets) return rows;
            if (! this.showAllAssets) return rows.slice(0, this.previewLimit);
            const start = (this.assetPage - 1) * this.pageSize;
            return rows.slice(start, start + this.pageSize);
        },
        assetVisible(id) {
            return this.visibleAssets().some((row) => Number(row.id) === Number(id));
        },
        assetPageCount() {
            return Math.max(1, Math.ceil(this.filteredAssets().length / this.pageSize));
        },
        openRequest(preselected = []) {
            this.presets = [...preselected];
            this.note = '';
            this.askMembers = false;
            this.requestStep = 'pick';
            this.requestOpen = true;
        },
        togglePreset(label) {
            if (this.presets.includes(label)) {
                this.presets = this.presets.filter((item) => item !== label);
            } else {
                this.presets = [...this.presets, label];
            }
        },
        closeRequest() {
            this.requestOpen = false;
            this.requestStep = 'pick';
        }
    }"
    x-effect="if (! requestOpen) { requestStep = 'pick' }"
    @keydown.escape.window="if (lightbox) { lightbox = null } else if (openAsset) { openAsset = null } else if (requestOpen) { closeRequest() }"
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
                Assets pledged on this loan. Profile assets that are not tied to this file stay on the {{ $who }}’s profile.
            @endif
        </p>
    </div>

    <div class="p-5 sm:p-6 space-y-5">
        @if ($referToLeader)
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-4 space-y-2">
                <p class="text-sm font-semibold text-slate-950">
                    Collateral for this loan is on the {{ $isGroupFile ? 'group leader' : 'borrower' }} file
                </p>
                <p class="text-xs text-slate-700">
                    @if ($isGuarantor)
                        The guarantor does not need a separate collateral section unless they themselves pledge an extra asset.
                    @else
                        Members do not repeat the leader’s pledged assets here. Open the leader desk to review loan collateral.
                    @endif
                    Ask this person only when coverage needs an extra asset they own — they must consent by picking it on their profile.
                </p>
                <a href="{{ $leaderCollateralUrl }}" class="inline-flex text-sm font-semibold text-brand hover:underline">
                    Open {{ $isGroupFile ? 'leader' : 'borrower' }} collateral →
                </a>
            </div>
        @elseif ($assets->isEmpty())
            <div class="rounded-xl bg-amber-50/60 ring-1 ring-amber-100 px-4 py-4">
                <p class="text-sm font-semibold text-amber-950">No collateral on this loan</p>
                <p class="text-xs text-amber-900/80 mt-1">
                    Only assets marked On this loan appear here. Request collateral if the {{ $who }} still needs to pledge one.
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
                <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">
                        {{ $assets->count() }} on this loan
                        <span class="normal-case tracking-normal text-gray-400 font-medium" x-show="filteredAssets().length !== assetRows.length" x-cloak>
                            · <span x-text="filteredAssets().length"></span> match
                        </span>
                    </p>
                    @if ($assets->count() > $assetPreviewLimit)
                        <button type="button"
                                class="text-sm font-semibold text-brand hover:underline"
                                @click="showAllAssets = ! showAllAssets; assetPage = 1">
                            <span x-show="! showAllAssets">Show all collaterals ({{ $assets->count() }})</span>
                            <span x-show="showAllAssets" x-cloak>Show fewer</span>
                        </button>
                    @endif
                </div>
                @if ($assets->count() > $assetPreviewLimit)
                    <div class="grid sm:grid-cols-4 gap-2 mb-3" x-show="showAllAssets || assetRows.length > previewLimit">
                        <input type="search" x-model="assetQuery" @input="assetPage = 1"
                               placeholder="Search type, registration, owner, status"
                               class="sm:col-span-2 w-full rounded-lg border-gray-300 text-sm">
                        <select x-model="assetType" @change="assetPage = 1" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">All types</option>
                            @foreach ($assetCardRows->unique('asset_type') as $row)
                                <option value="{{ $row['asset_type'] }}">{{ $row['type_label'] }}</option>
                            @endforeach
                        </select>
                        <select x-model="assetStatus" @change="assetPage = 1" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">All statuses</option>
                            @foreach ($assetStatusFilters as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($assetCardRows as $row)
                        @php
                            $card = $row['card'];
                            $sourceLabel = $row['source_label'];
                        @endphp
                        <div @if ($assets->count() > $assetPreviewLimit) x-show="assetVisible({{ (int) $row['id'] }})" x-cloak @endif>
                            <x-site.collateral-card
                                :selected="$card"
                                :type-icons="$typeIcons"
                                :source-label="$sourceLabel"
                            >
                                <button type="button" @click="openAsset = {{ (int) $row['id'] }}"
                                        class="mt-3 inline-flex items-center justify-center w-full bg-gray-900 hover:bg-black text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                    {{ __('site.partner_portal.view') }}
                                </button>
                            </x-site.collateral-card>
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center justify-between gap-3 mt-3" x-show="showAllAssets && filteredAssets().length > pageSize" x-cloak>
                    <button type="button" class="text-sm font-semibold text-brand disabled:text-gray-400"
                            :disabled="assetPage <= 1" @click="assetPage = Math.max(1, assetPage - 1)">Previous</button>
                    <p class="text-xs text-gray-500">Page <span x-text="assetPage"></span> of <span x-text="assetPageCount()"></span></p>
                    <button type="button" class="text-sm font-semibold text-brand disabled:text-gray-400"
                            :disabled="assetPage >= assetPageCount()" @click="assetPage = Math.min(assetPageCount(), assetPage + 1)">Next</button>
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

        @if ($isAb && ! $isGuarantor && ! $isMember && $pledgedForValuation)
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 space-y-2">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-sky-900">Asset-backed valuation</p>
                <h3 class="text-sm font-semibold text-gray-900">Valuer is assigned after the apply-flow fee</h3>
                <p class="text-xs text-gray-600">
                    On this product the borrower pays the valuation fee in the apply flow (1,000 TZS per pledged asset + markup). After that payment clears, a valuer is auto-assigned by region. Screening only waits — the valuer then inspects each pledged asset on the partner portal.
                </p>
                @if ($openValuation)
                    <p class="text-sm font-semibold text-sky-950">
                        Valuation {{ str_replace('_', ' ', $openValuation->status) }}
                        with {{ $openValuation->vendor?->name ?? 'assigned valuer' }}
                    </p>
                @elseif ($valuationFeeDue)
                    <p class="text-sm text-amber-900">Waiting for the borrower to pay the valuation fee. No valuer is assigned yet.</p>
                @elseif (! $openValuation)
                    <p class="text-sm text-amber-900">
                        Fee is settled but no valuer covers {{ $record->customer?->region ?: 'this region' }}. Use Assign valuation partner below, or add the region on a valuer (Nationwide also matches).
                    </p>
                @endif
            </div>
        @endif

        @if ($showValuationResult)
            @php
                $resultReport = $valuationReport ?? [];
                $resultValuer = $resultReport['valuer_name']
                    ?? $completedValuation?->vendor?->name
                    ?? 'Valuer';
            @endphp
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 space-y-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-sky-900">Valuation</p>
                    <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Forced sale value on file</h3>
                    <p class="text-xs text-gray-600 mt-1">
                        {{ $resultValuer }} completed the inspection. Match photos on the checklist — this card is the number, not a request.
                    </p>
                </div>
                <div class="rounded-xl bg-white ring-1 ring-sky-100 px-4 py-3 text-sm space-y-2">
                    <p class="font-semibold text-sky-950">{{ $resultValuer }}</p>
                    <dl class="grid sm:grid-cols-2 gap-2 text-xs">
                        <div><dt class="text-sky-800">Forced sale value</dt><dd class="font-semibold">{{ format_money($resultReport['forced_sale_value'] ?? $completedValuation?->forced_sale_value ?? 0) }}</dd></div>
                        <div><dt class="text-sky-800">This asset can cover (FSV × LTV {{ (int) ($coverage['ltv_percent'] ?? $resultReport['ltv_percent'] ?? 0) }}%)</dt><dd class="font-semibold">{{ format_money($coverage['max_loan_amount'] ?? $resultReport['max_loan_amount'] ?? 0) }}</dd></div>
                        <div><dt class="text-sky-800">Requested</dt><dd class="font-semibold">{{ format_money($coverage['requested_amount'] ?? $record->requested_amount) }}</dd></div>
                    </dl>
                    @if (! empty($coverage['sufficient']))
                        @if (($coverage['next'] ?? '') === 'insurance_update')
                            <p class="text-amber-900 text-xs">LTV covers the requested amount. Insurance on the pledged asset is not sufficient — the asset owner must update cover.</p>
                        @else
                            <p class="text-emerald-800 text-xs">LTV covers the requested amount and insurance is in order.</p>
                        @endif
                    @elseif ($coverage)
                        <p class="text-rose-800 text-xs font-semibold">Collateral is not sufficient (shortfall {{ format_money($coverage['shortfall'] ?? 0) }}). Combined FSV × LTV across pledged assets must cover the requested amount.</p>
                        <ul class="text-xs text-gray-700 list-disc pl-4 space-y-1">
                            @foreach ($coverage['scenarios'] ?? [] as $scenario)
                                <li>{{ $scenario['label'] }}</li>
                            @endforeach
                        </ul>
                        @if ($canRequestDocs)
                            <button type="button"
                                    @click="openRequest(['Add collateral asset'])"
                                    class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-xl">
                                Request another asset from {{ $who }}
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        @elseif ($showValuationProgress)
            @php
                $valuerAuto = str_contains(strtolower((string) ($openValuation->notes ?? '')), 'auto-assigned');
            @endphp
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 space-y-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-sky-900">Valuation</p>
                    <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Inspection in progress</h3>
                    <p class="text-xs text-gray-600 mt-1">
                        The valuer already has this job. Screening does not request valuation again.
                    </p>
                </div>
                <div class="rounded-xl bg-white ring-1 ring-sky-100 px-4 py-3 text-sm space-y-1.5">
                    <p class="text-sky-950 font-semibold">
                        Valuation {{ str_replace('_', ' ', $openValuation->status) }}
                        with {{ $openValuation->vendor?->name ?? 'assigned valuer' }}
                        @if ($valuerAuto)
                            <span class="text-[11px] font-semibold text-sky-800">· Auto-assigned</span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-600">
                        Phone {{ $openValuation->vendor?->phone ?: '—' }}
                        · Email {{ $openValuation->vendor?->email ?: '—' }}
                    </p>
                    @if ($valuerAuto)
                        <p class="text-xs text-sky-900">
                            Screening uses these details for communication only. The valuer already has the task (matched on customer region, then least open jobs unless settings change that).
                        </p>
                    @endif
                </div>
            </div>
        @elseif ($showRequestValuation)
            <div class="rounded-xl bg-amber-50/80 ring-1 ring-amber-200 px-4 py-4 space-y-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-amber-900">Next step for screening</p>
                    <h3 class="text-sm font-semibold text-gray-900 mt-0.5">Request valuation</h3>
                    <p class="text-xs text-gray-600 mt-1">
                        This is not the AB product. Clicking below asks the group leader (or individual borrower) to pay the valuation fee on their loan profile — same payment card as membership / group application fee. A valuer is auto-assigned by region only after that payment clears. Do not expect a valuer to appear the moment you click.
                    </p>
                </div>
                @if ($valuationFeeDue)
                    <p class="text-sm text-amber-900">
                        Waiting for the borrower{{ is_group_loan_product($record->product ?? null) ? ' (group leader)' : '' }} to pay the valuation fee on their loan profile. Do not press Request valuation again — that already happened (or opened automatically when the asset was pledged).
                    </p>
                @elseif ($needsManualValuer)
                    <div class="rounded-xl bg-white ring-1 ring-amber-100 px-4 py-3 space-y-3">
                        <p class="text-sm font-semibold text-amber-950">Fee is paid. No valuer covers {{ $record->customer?->region ?: 'this borrower region' }}.</p>
                        <p class="text-xs text-gray-600">
                            Auto-assign only picks an active valuer who covers that region (or Nationwide). Add the region on a valuer, or assign someone else below — they still complete the same partner-portal task.
                        </p>
                        @if ($assignableValuers->isEmpty())
                            <p class="text-sm text-rose-800">No active valuers in the system. Partner support will enroll one, then set coverage (Nationwide or {{ $record->customer?->region ?: 'this region' }}) and activate the portal PIN. Waiting files auto-match after that.</p>
                            @include('admin.loan-applications.review._request_partner_coverage', [
                                'coverageApplication' => $record,
                                'coverageCategory' => 'valuer',
                                'coverageRegion' => $record->customer?->region,
                            ])
                        @else
                            <form method="POST" action="{{ route('admin.loan-applications.assign-valuer', $record) }}" class="space-y-2">
                                @csrf
                                <label class="block text-xs font-medium text-gray-600">Assign a valuer</label>
                                <select name="vendor_id" required class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="">Select valuer…</option>
                                    @foreach ($assignableValuers as $valuer)
                                        @php
                                            $covers = app(\App\Services\PartnerRegionCoverage::class)->covers($valuer, $record->customer?->region);
                                            $coverLabel = app(\App\Services\PartnerRegionCoverage::class)->label($valuer);
                                        @endphp
                                        <option value="{{ $valuer->id }}">
                                            {{ $valuer->name }} · {{ $coverLabel }}{{ $covers ? '' : ' · outside region' }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="text" name="notes" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Optional note (e.g. travelling from Dar)">
                                <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                                    Assign valuer
                                </button>
                            </form>
                            <div class="pt-2 border-t border-amber-100 space-y-1">
                                <p class="text-xs text-gray-600">Need a valuer based in {{ $record->customer?->region ?: 'this region' }} instead of assigning someone from outside?</p>
                                @include('admin.loan-applications.review._request_partner_coverage', [
                                    'coverageApplication' => $record,
                                    'coverageCategory' => 'valuer',
                                    'coverageRegion' => $record->customer?->region,
                                    'enrollClass' => 'inline-flex text-sm font-semibold text-brand hover:underline',
                                ])
                            </div>
                        @endif
                    </div>
                @elseif ($valuationAlreadyOpened)
                    <p class="text-sm text-gray-700">Valuation is already in progress on this file. Screening does not need to request it again.</p>
                @else
                    <form method="POST" action="{{ route('admin.loan-applications.request-valuation', $record) }}">
                        @csrf
                        <p class="text-xs text-gray-600 mb-2">
                            Pledging on IL/GL opens the leader/borrower pay card automatically (payments.show). Use this only if that card still has not appeared.
                        </p>
                        <input type="text" name="notes" class="w-full rounded-lg border-gray-300 text-sm mb-2" placeholder="Optional internal note">
                        <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                            Request valuation (borrower pays first)
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
            @endunless
        @endif

        @if ($canRequestDocs)
            <div id="collateral-requests" class="space-y-3 {{ $assets->isNotEmpty() || $referToLeader ? 'pt-2 border-t border-brand/10' : '' }}">
                @foreach ($openCollateralBatches as $batch)
                    @php
                        $batchNote = $batch['note'];
                        $isDefaultNote = in_array($batchNote, array_values(\App\Services\ApplicationDocumentRequestService::presetInstructions()), true);
                    @endphp
                    <div class="rounded-xl bg-amber-50/80 ring-1 ring-amber-200 px-4 py-3 space-y-2" x-data="{ open: false, withdrawing: false }">
                        <p class="text-sm font-semibold text-amber-950">
                            Requested
                            @if ($batch['at'])
                                · {{ $batch['at']->timezone(config('app.timezone'))->format('d M Y') }}
                            @endif
                            · {{ $waitingOn }}
                        </p>
                        <ul class="text-xs text-amber-900 list-disc pl-4 space-y-0.5">
                            @foreach ($batch['labels'] as $label)
                                <li>{{ $label }}</li>
                            @endforeach
                        </ul>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="open = ! open" class="inline-flex text-sm font-semibold text-brand hover:underline">
                                View request
                            </button>
                            @if ($batch['can_cancel'])
                                <button type="button" @click="withdrawing = true; open = true" class="inline-flex text-sm font-semibold text-slate-700 hover:underline">
                                    Cancel request
                                </button>
                            @endif
                        </div>
                        <div x-show="open" x-cloak class="rounded-lg bg-white ring-1 ring-amber-100 px-3 py-3 space-y-2">
                            <p class="text-xs text-gray-600">
                                Sent to {{ $requestFromName }} ({{ $who }}).
                                @if (filled($batchNote) && ! $isDefaultNote)
                                    Note: “{{ $batchNote }}”
                                @elseif (filled($batchNote))
                                    {{ $batchNote }}
                                @endif
                            </p>
                            <div x-show="withdrawing" x-cloak class="space-y-2">
                                <p class="text-sm font-semibold text-gray-900">Withdraw this request from {{ $requestFromName }}?</p>
                                <p class="text-xs text-gray-600">They will no longer see it. Nothing else on this file changes.</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="withdrawing = false" class="inline-flex text-sm font-semibold text-slate-800 bg-white ring-1 ring-slate-200 px-3 py-1.5 rounded-lg">Keep request</button>
                                    <form method="POST" action="{{ route('admin.loan-applications.document-requests.cancel', $record) }}">
                                        @csrf
                                        <input type="hidden" name="confirmed" value="1">
                                        <input type="hidden" name="return_workspace" value="profiles">
                                        <input type="hidden" name="return_tab" value="collateral">
                                        <input type="hidden" name="review_person" value="{{ $person }}">
                                        @foreach ($batch['ids'] as $id)
                                            <input type="hidden" name="ids[]" value="{{ $id }}">
                                        @endforeach
                                        @if ($isMember)
                                            <input type="hidden" name="review_m" value="{{ request('review_m', data_get($review, 'member_row.id')) }}">
                                        @endif
                                        @if ($isGuarantor)
                                            <input type="hidden" name="review_g" value="{{ request('review_g') }}">
                                        @endif
                                        <button type="submit" class="inline-flex text-sm font-semibold text-white bg-rose-700 px-3 py-1.5 rounded-lg">Withdraw request</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="button"
                        @click="openRequest([])"
                        class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                    Request collateral
                </button>

                <x-site.action-panel title="Request collateral" open="requestOpen">
                    <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}" class="space-y-4" data-no-draft>
                        @csrf
                        <input type="hidden" name="type" value="document">
                        <input type="hidden" name="intent" value="collateral">
                        <input type="hidden" name="return_workspace" value="profiles">
                        <input type="hidden" name="return_tab" value="collateral">
                        <input type="hidden" name="review_person" value="{{ $person }}">
                        <input type="hidden" name="confirmed" value="1" x-bind:disabled="requestStep !== 'review'">
                        <template x-for="preset in presets" :key="preset">
                            <input type="hidden" name="presets[]" :value="preset">
                        </template>
                        @if ($isMember)
                            <input type="hidden" name="review_m" value="{{ request('review_m', data_get($review, 'member_row.id')) }}">
                            <input type="hidden" name="subject_customer_id" value="{{ data_get($review, 'customer.id') }}">
                            <input type="hidden" name="loan_group_member_id" value="{{ request('review_m', data_get($review, 'member_row.id')) }}">
                        @endif
                        @if ($isGuarantor)
                            <input type="hidden" name="review_g" value="{{ request('review_g') }}">
                            <input type="hidden" name="subject_customer_id" value="{{ data_get($review, 'customer.id') }}">
                        @endif

                        <div x-show="requestStep === 'pick'" class="space-y-3">
                            <p class="text-xs text-gray-600">Select what {{ $requestFromName }} should send. Nothing is sent until you review and confirm.</p>
                            <div class="grid gap-2">
                                @foreach ($collateralPresets as $preset)
                                    <label class="flex items-start gap-2 text-sm text-gray-700 bg-emerald-50/80 rounded-xl px-3 py-2 ring-1 ring-brand/10">
                                        <input type="checkbox" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand"
                                               :checked="presets.includes(@js($preset))"
                                               @change="togglePreset(@js($preset))">
                                        <span>{{ $preset }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <textarea x-model="note" name="instructions" rows="2" maxlength="2000"
                                      placeholder="Optional note shown to {{ $requestFromName }}"
                                      class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5"></textarea>
                            @if ($isGroupFile && ! $isGuarantor && ! $isMember && $activeMemberCount > 0)
                                <label class="flex items-start gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="ask_members" value="1" x-model="askMembers" class="mt-0.5 rounded border-gray-300 text-brand">
                                    <span>Send to group members instead of the leader</span>
                                </label>
                            @endif
                            <div class="flex flex-wrap gap-2 pt-1">
                                <button type="button" @click="closeRequest()" class="inline-flex text-sm font-semibold text-slate-800 bg-white ring-1 ring-slate-200 px-4 py-2.5 rounded-xl">Cancel</button>
                                <button type="button" @click="requestStep = 'review'" :disabled="presets.length === 0"
                                        class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl disabled:opacity-40">
                                    Review request
                                </button>
                            </div>
                        </div>

                        <div x-show="requestStep === 'review'" x-cloak class="space-y-3">
                            <p class="text-sm font-semibold text-gray-900">
                                You are requesting from
                                <span x-text="askMembers ? {{ \Illuminate\Support\Js::from($activeMemberCount.' group members') }} : {{ \Illuminate\Support\Js::from($requestFromName) }}"></span>:
                            </p>
                            <ul class="text-sm text-gray-800 list-disc pl-4 space-y-0.5">
                                <template x-for="preset in presets" :key="preset">
                                    <li x-text="preset"></li>
                                </template>
                            </ul>
                            <p class="text-sm text-gray-700" x-show="note.trim()" x-cloak>
                                <span class="font-semibold">Note:</span>
                                “<span x-text="note.trim()"></span>”
                            </p>
                            <p class="text-xs text-gray-500">This notifies them on their loan profile. It does not pledge an asset for you.</p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <button type="button" @click="requestStep = 'pick'" class="inline-flex text-sm font-semibold text-slate-800 bg-white ring-1 ring-slate-200 px-4 py-2.5 rounded-xl">Cancel</button>
                                <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                                    Send request
                                </button>
                            </div>
                        </div>
                    </form>
                </x-site.action-panel>
            </div>
        @endif
    </div>

    <div x-show="lightbox" x-cloak x-transition
         class="fixed inset-0 z-[90] bg-black/85 flex items-center justify-center p-4"
         @click.self="lightbox = null">
        <button type="button" @click="lightbox = null" class="absolute top-4 right-4 size-10 rounded-full bg-white/10 text-white text-2xl grid place-items-center" aria-label="Close">×</button>
        <img :src="lightbox" alt="" class="max-h-[90vh] max-w-full rounded-xl object-contain">
    </div>
</section>
