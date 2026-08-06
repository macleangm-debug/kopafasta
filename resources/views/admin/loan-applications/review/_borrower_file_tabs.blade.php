@php
    $guarantorRows = collect($review['guarantors'] ?? []);
    $person = request('person', 'borrower');
    if (! in_array($person, ['borrower', 'guarantor'], true)) {
        $person = 'borrower';
    }

    $selectedGuarantor = null;
    if ($person === 'guarantor') {
        $gId = (int) request('g', 0);
        $selectedGuarantor = $guarantorRows->first(fn ($row) => (int) ($row['link_id'] ?? 0) === $gId)
            ?? $guarantorRows->first(fn ($row) => ($row['profile_complete'] ?? false) && ($row['status'] ?? '') !== 'rejected')
            ?? $guarantorRows->first(fn ($row) => ($row['status'] ?? '') !== 'rejected')
            ?? $guarantorRows->first();
        if (! $selectedGuarantor) {
            $person = 'borrower';
        }
    }

    $defaultTab = request('tab', 'affordability');
    if ($defaultTab === 'overview') {
        $defaultTab = 'affordability';
    }
    $borrowerTabs = [
        ['affordability', 'Affordability'],
        ['crb', 'CRB'],
        ['personal', 'Personal'],
        ['face', 'Face'],
        ['residence', 'Residence'],
        ['activity', 'Activity'],
        ['documents', 'Documents'],
        ['collateral', 'Collateral'],
    ];
    if ($groupReview ?? null) {
        $borrowerTabs[] = ['group', 'Group'];
    }
    $guarantorTabs = [
        ['affordability', 'Affordability'],
        ['crb', 'CRB'],
        ['personal', 'Personal'],
        ['face', 'Face'],
        ['residence', 'Residence'],
        ['activity', 'Activity'],
        ['documents', 'Documents'],
        ['collateral', 'Collateral'],
    ];
    $profileTabs = $person === 'guarantor' ? $guarantorTabs : $borrowerTabs;
    $allowedTabs = array_column($profileTabs, 0);
    if (! in_array($defaultTab, $allowedTabs, true)) {
        $defaultTab = 'affordability';
    }

    $openDocRequestCount = 0;
    if (isset($groupedDocumentRequests) && is_array($groupedDocumentRequests)) {
        $openDocRequestCount = collect($groupedDocumentRequests['pending'] ?? [])->count()
            + collect($groupedDocumentRequests['uploaded'] ?? [])->count();
    }

    $tabUrl = function (string $key) use ($record, $person, $selectedGuarantor) {
        $params = [
            'loan_application' => $record,
            'tab' => $key,
            'person' => $person,
        ];
        if ($person === 'guarantor' && $selectedGuarantor) {
            $params['g'] = $selectedGuarantor['link_id'];
        }

        return route('admin.loan-applications.show', $params).'#borrower-file';
    };

    $personUrl = function (string $who, ?int $linkId = null) use ($record) {
        $params = [
            'loan_application' => $record,
            'person' => $who,
            'tab' => 'affordability',
        ];
        if ($who === 'guarantor' && $linkId) {
            $params['g'] = $linkId;
        }

        return route('admin.loan-applications.show', $params).'#borrower-file';
    };

    // Subject review context for shared profile partials
    $subjectReview = $review;
    $subjectRecord = $record;
    if ($person === 'guarantor' && $selectedGuarantor && ! empty($selectedGuarantor['file'])) {
        $subjectReview = array_merge($review, $selectedGuarantor['file']);
        $subjectReview['is_guarantor_subject'] = true;
        $subjectReview['guarantor_row'] = $selectedGuarantor;
    }
@endphp

{{-- Server-rendered: person switcher + section tabs --}}
<section id="borrower-file" class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden scroll-mt-24">
    <div class="px-5 pt-5 pb-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Credit file</p>
        <h3 class="text-base font-bold text-gray-900 mt-0.5">Profile sections</h3>
        <p class="text-xs text-gray-500 mt-0.5">
            Review the borrower and guarantor the same way — the guarantor must be able to carry the loan if the borrower fails.
        </p>

        <div class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="File subject">
            <a href="{{ $personUrl('borrower') }}"
               @class([
                   'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold transition ring-1',
                   'bg-brand text-white ring-brand shadow-sm' => $person === 'borrower',
                   'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40' => $person !== 'borrower',
               ])>
                Borrower
                <span class="opacity-80 font-normal truncate max-w-[10rem]">{{ $review['customer']->full_name ?? '' }}</span>
            </a>
            @forelse ($guarantorRows->filter(fn ($row) => ($row['status'] ?? '') !== 'rejected' || ($row['profile_complete'] ?? false)) as $gRow)
                <a href="{{ $personUrl('guarantor', (int) $gRow['link_id']) }}"
                   @class([
                       'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold transition ring-1',
                       'bg-brand text-white ring-brand shadow-sm' => $person === 'guarantor' && (int) ($selectedGuarantor['link_id'] ?? 0) === (int) $gRow['link_id'],
                       'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40' => ! ($person === 'guarantor' && (int) ($selectedGuarantor['link_id'] ?? 0) === (int) $gRow['link_id']),
                   ])>
                    Guarantor
                    <span class="opacity-80 font-normal truncate max-w-[10rem]">{{ $gRow['name'] ?? '—' }}</span>
                    @if ($gRow['profile_complete'] ?? false)
                        <span @class([
                            'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                            'bg-white/20' => $person === 'guarantor' && (int) ($selectedGuarantor['link_id'] ?? 0) === (int) $gRow['link_id'],
                            'bg-emerald-100 text-emerald-800' => ! ($person === 'guarantor' && (int) ($selectedGuarantor['link_id'] ?? 0) === (int) $gRow['link_id']),
                        ])>Ready</span>
                    @endif
                </a>
            @empty
                @if ($review['product']?->requires_guarantor)
                    <span class="inline-flex items-center rounded-xl px-4 py-2.5 text-xs font-semibold bg-amber-50 text-amber-950 ring-1 ring-amber-200">
                        Guarantor pending
                    </span>
                @endif
            @endforelse
        </div>
    </div>

    <div class="px-3 pt-3 flex gap-1.5 overflow-x-auto border-b border-gray-100" role="tablist" aria-label="Profile sections">
        @foreach ($profileTabs as [$key, $label])
            <a href="{{ $tabUrl($key) }}"
               role="tab"
               aria-selected="{{ $defaultTab === $key ? 'true' : 'false' }}"
               @class([
                   'shrink-0 rounded-xl px-4 py-2.5 text-xs font-semibold transition inline-flex items-center gap-1.5',
                   'bg-brand text-white shadow-sm' => $defaultTab === $key,
                   'bg-transparent text-gray-600 hover:bg-brand-muted/50' => $defaultTab !== $key,
               ])>
                {{ $label }}
                @if ($person === 'borrower' && $key === 'documents' && $openDocRequestCount > 0)
                    <span @class([
                        'inline-flex min-w-[1.25rem] justify-center rounded-full text-[10px] font-bold px-1.5 py-0.5',
                        'bg-white/20 text-white' => $defaultTab === $key,
                        'bg-amber-100 text-amber-900' => $defaultTab !== $key,
                    ])>{{ $openDocRequestCount }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="p-5">
        @if ($person === 'guarantor' && $selectedGuarantor && empty($selectedGuarantor['file']) && ! in_array($defaultTab, ['affordability', 'crb'], true))
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                <p class="text-sm font-semibold text-amber-950">Guarantor profile not complete yet</p>
                <p class="text-xs text-amber-900/90 mt-1">
                    Personal, face, residence, activity, documents and collateral unlock after onboarding finishes.
                    Use Affordability / CRB for status while waiting.
                </p>
            </div>
        @elseif ($defaultTab === 'affordability')
            @include('admin.loan-applications.review._subject_affordability', [
                'review' => $subjectReview,
                'affordability' => $affordability ?? ($review['affordability'] ?? null),
                'counterOffer' => $counterOffer ?? ($review['counter_offer'] ?? null),
            ])
            @if ($person === 'guarantor' && $selectedGuarantor)
                <div class="mt-5">
                    @include('admin.loan-applications.review._guarantor_overview', [
                        'guarantor' => $selectedGuarantor,
                        'single' => true,
                    ])
                </div>
            @endif
        @elseif ($defaultTab === 'crb')
            @include('admin.loan-applications.review._subject_crb', ['review' => $subjectReview])
        @elseif ($defaultTab === 'personal')
            <div class="space-y-5">
                @include('admin.loan-applications.review._profile_personal', ['review' => $subjectReview])
            </div>
        @elseif ($defaultTab === 'face')
            @include('admin.loan-applications.review._verification', ['review' => $subjectReview])
        @elseif ($defaultTab === 'residence')
            @include('admin.loan-applications.review._profile_residence', ['review' => $subjectReview])
        @elseif ($defaultTab === 'activity')
            @include('admin.loan-applications.review._profile_activity', ['review' => $subjectReview])
        @elseif ($defaultTab === 'documents')
            <div class="space-y-5">
                @if ($person === 'guarantor')
                    @include('admin.loan-applications.review._subject_documents', ['review' => $subjectReview])
                @else
                    @include('admin.loan-applications.review._documents')
                @endif
                @include('admin.loan-applications.review._document-requests')
            </div>
        @elseif ($defaultTab === 'collateral')
            <div class="space-y-5">
                @if ($person === 'borrower')
                    {{-- Application-level blocks: only render for asset-backed / asset-lending products (partials self-gate). --}}
                    @include('admin.loan-applications._asset-backed')
                    @include('admin.loan-applications._asset-lending')
                    @if (! empty($review['asset']) || app(\App\Services\AssetLendingService::class)->isAssetLendingApplication($record))
                        @include('admin.loan-applications.review._asset')
                    @endif
                @endif
                @include('admin.loan-applications.review._collateral_tab', [
                    'review' => $subjectReview,
                    'person' => $person,
                    'selectedGuarantor' => $selectedGuarantor ?? null,
                ])
            </div>
        @elseif ($defaultTab === 'group' && ($groupReview ?? null))
            @include('admin.loan-applications.review._group')
        @endif
    </div>
</section>
