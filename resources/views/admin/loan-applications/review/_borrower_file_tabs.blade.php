@php
    $guarantorRows = collect($review['guarantors'] ?? []);
    $memberRows = collect($groupReview['members'] ?? []);
    $isGroupLoan = $memberRows->isNotEmpty();
    $person = request('person', 'borrower');
    if (! in_array($person, ['borrower', 'guarantor', 'member'], true)) {
        $person = 'borrower';
    }
    if ($person === 'member' && ! $isGroupLoan) {
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

    $selectedMember = null;
    if ($person === 'member') {
        $mId = (int) request('m', 0);
        $selectedMember = $memberRows->first(fn ($row) => (int) ($row['id'] ?? 0) === $mId)
            ?? $memberRows->first(fn ($row) => ($row['role'] ?? '') !== 'leader')
            ?? $memberRows->first();
        if (! $selectedMember) {
            $person = 'borrower';
        }
    }

    // Profiles = dossier only. Affordability, CRB, partners, group, and document
    // verification live on Review checklist to avoid duplicate review surfaces.
    $defaultTab = request('tab', 'personal');
    if (in_array($defaultTab, ['overview', 'affordability', 'crb', 'partners-available', 'partners-unavailable', 'group'], true)) {
        $defaultTab = 'personal';
    }
    $profileTabs = [
        ['personal', 'Personal'],
        ['face', 'Face'],
        ['residence', 'Residence'],
        ['activity', 'Activity'],
        ['documents', 'Documents'],
        ['collateral', 'Collateral'],
    ];
    $allowedTabs = array_column($profileTabs, 0);
    if (! in_array($defaultTab, $allowedTabs, true)) {
        $defaultTab = 'personal';
    }

    $openDocRequestCount = 0;
    if (isset($groupedDocumentRequests) && is_array($groupedDocumentRequests)) {
        $openDocRequestCount = collect($groupedDocumentRequests['pending'] ?? [])->count()
            + collect($groupedDocumentRequests['uploaded'] ?? [])->count();
    }

    $tabUrl = function (string $key) use ($record, $person, $selectedGuarantor, $selectedMember) {
        $params = [
            'loan_application' => $record,
            'workspace' => 'profiles',
            'tab' => $key,
            'person' => $person,
        ];
        if ($person === 'guarantor' && $selectedGuarantor) {
            $params['g'] = $selectedGuarantor['link_id'];
        }
        if ($person === 'member' && $selectedMember) {
            $params['m'] = $selectedMember['id'];
        }

        return route('admin.loan-applications.show', $params).'#borrower-file';
    };

    $personUrl = function (string $who, ?int $linkId = null) use ($record) {
        $params = [
            'loan_application' => $record,
            'workspace' => 'profiles',
            'person' => $who,
            'tab' => 'personal',
        ];
        if ($who === 'guarantor' && $linkId) {
            $params['g'] = $linkId;
        }
        if ($who === 'member' && $linkId) {
            $params['m'] = $linkId;
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
    if ($person === 'member' && $selectedMember) {
        if (! empty($selectedMember['file'])) {
            $subjectReview = array_merge($review, $selectedMember['file']);
        } elseif (! empty($selectedMember['customer_id'])) {
            $memberCustomer = \App\Models\Customer::query()->find($selectedMember['customer_id']);
            if ($memberCustomer) {
                $subjectReview = array_merge(
                    $review,
                    app(\App\Services\LoanApplicationReviewService::class)->subjectFileForCustomer($memberCustomer)
                );
            }
        }
        $subjectReview['is_member_subject'] = true;
        $subjectReview['member_row'] = $selectedMember;
        $subjectReview['crb'] = [
            'score' => $selectedMember['crb_score'] ?? null,
            'recommendation' => $selectedMember['crb_recommendation'] ?? null,
            'existing_loans' => $selectedMember['crb_existing_loans'] ?? null,
            'outstanding_balance' => $selectedMember['crb_outstanding'] ?? null,
            'delinquencies' => $selectedMember['crb_delinquencies'] ?? null,
            'summary' => $selectedMember['crb_summary'] ?? null,
            'checked_at' => $selectedMember['crb_checked_at'] ?? null,
        ];
    }

    $leaderCustomerId = (int) ($review['customer']->id ?? $record->customer_id ?? 0);
@endphp

{{-- Server-rendered: person switcher + section tabs --}}
<section id="borrower-file" class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden scroll-mt-24">
    <div class="px-5 pt-5 pb-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Credit file</p>
        <h3 class="text-base font-bold text-gray-900 mt-0.5">Profile sections</h3>
        <p class="text-xs text-gray-500 mt-0.5">
            Profile data only (identity, face, residence, activity, documents library, collateral).
            Pass/Fail review, affordability, CRB, partners, and group scoring live on <span class="font-semibold text-brand">Review checklist</span>.
        </p>

        <div class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="File subject">
            <a href="{{ $personUrl('borrower') }}"
               @class([
                   'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold transition ring-1',
                   'bg-brand text-white ring-brand shadow-sm' => $person === 'borrower',
                   'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40' => $person !== 'borrower',
               ])>
                {{ $isGroupLoan ? 'Leader' : 'Borrower' }}
                <span class="opacity-80 font-normal truncate max-w-[10rem]">{{ $review['customer']->full_name ?? '' }}</span>
            </a>

            @if ($isGroupLoan)
                @foreach ($memberRows->filter(fn ($row) => (int) ($row['customer_id'] ?? 0) !== $leaderCustomerId) as $mRow)
                    <a href="{{ $personUrl('member', (int) $mRow['id']) }}"
                       @class([
                           'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold transition ring-1',
                           'bg-brand text-white ring-brand shadow-sm' => $person === 'member' && (int) ($selectedMember['id'] ?? 0) === (int) $mRow['id'],
                           'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40' => ! ($person === 'member' && (int) ($selectedMember['id'] ?? 0) === (int) $mRow['id']),
                       ])>
                        Member
                        <span class="opacity-80 font-normal truncate max-w-[10rem]">{{ $mRow['name'] ?? '—' }}</span>
                        @if ($mRow['profile_complete'] ?? $mRow['kyc_complete'] ?? false)
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                'bg-white/20' => $person === 'member' && (int) ($selectedMember['id'] ?? 0) === (int) $mRow['id'],
                                'bg-emerald-100 text-emerald-800' => ! ($person === 'member' && (int) ($selectedMember['id'] ?? 0) === (int) $mRow['id']),
                            ])>Ready</span>
                        @endif
                    </a>
                @endforeach
            @endif

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
        @if ($person === 'guarantor' && $selectedGuarantor && empty($selectedGuarantor['file']))
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                <p class="text-sm font-semibold text-amber-950">Guarantor profile not complete yet</p>
                <p class="text-xs text-amber-900/90 mt-1">
                    Personal, face, residence, activity, documents and collateral unlock after onboarding finishes.
                    Use Review checklist for affordability / CRB while waiting.
                </p>
            </div>
        @elseif ($person === 'member' && $selectedMember && empty($selectedMember['file']) && empty($subjectReview['customer'] ?? null))
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                <p class="text-sm font-semibold text-amber-950">Member profile not complete yet</p>
                <p class="text-xs text-amber-900/90 mt-1">
                    Personal, face, residence, activity, documents and collateral unlock after the member finishes onboarding.
                </p>
            </div>
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
                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3 text-xs text-sky-950">
                    Profile library view. Verify application uploads and follow-up requests on
                    <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'checklist']).'#checklist-documents' }}"
                       class="font-semibold text-brand underline">Review checklist → Capacity and evidence</a>.
                </div>
                @include('admin.loan-applications.review._documents', ['review' => $subjectReview])
            </div>
        @elseif ($defaultTab === 'collateral')
            <div class="space-y-5">
                @if ($person === 'borrower')
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
        @endif
    </div>
</section>
