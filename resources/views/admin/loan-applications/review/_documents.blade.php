@php
    $requirements = collect($review['requirements'] ?? []);
    $uploads = collect($review['uploads'] ?? []);
    $histories = collect($review['upload_histories'] ?? []);
    $guidanceMap = collect($review['requirement_guidance'] ?? []);
    $isMemberSubject = (bool) ($review['is_member_subject'] ?? false);
    $isGuarantorSubject = (bool) ($review['is_guarantor_subject'] ?? false);
    $isSubjectPanel = $isMemberSubject || $isGuarantorSubject;
    $subjectName = ($review['customer']->full_name ?? null)
        ?: ($review['member_row']['name'] ?? null)
        ?: ($review['guarantor_row']['name'] ?? null)
        ?: 'this subject';
    // Same chrome for leader / member / guarantor — person switcher already names who is on screen.
    $evidenceTitle = 'Application evidence';
    $profileDocs = collect($review['profile_documents'] ?? []);
    $docReviewService = app(\App\Services\ApplicationDocumentReviewService::class);
    $appReviews = $docReviewService->reviewsForApplication($record);
    $docRequestService = app(\App\Services\ApplicationDocumentRequestService::class);

    $documentRequestsForPanel = collect($documentRequests ?? []);
    $subjectCustomerId = (int) ($review['customer']->id ?? 0);
    $memberId = (int) ($review['member_row']['id'] ?? 0);
    $panelPerson = $isMemberSubject ? 'member' : ($isGuarantorSubject ? 'guarantor' : 'borrower');
    $documentRequestsForPanel = $documentRequestsForPanel->filter(
        fn ($req) => $docRequestService->targetsReviewSubject(
            $req,
            $panelPerson,
            $subjectCustomerId,
            $memberId,
            (int) ($record->customer_id ?? 0),
        )
    )->values();

    $subjectHasIncomeRequest = $documentRequestsForPanel->contains(
        fn ($req) => $docRequestService->borrowerActionKind($req) === 'income'
    );

    // Duplicates of Face / Income verification (passport-size photo, source-of-income
    // proof, 3-month statements when 6-month Income verification is already required).
    $requirements = $requirements->reject(function ($req) {
        $name = $req->name ?? '';

        return \App\Models\LoanProductRequirement::nameLooksLikeProfileDuplicate($name, $req->description ?? null)
            || \App\Models\LoanProductRequirement::nameIsDigitalGroupRoster($name)
            || \App\Models\LoanProductRequirement::nameIsGroupConstitution($name);
    })->values();

    $categoryFor = function ($req): array {
        $hay = strtolower(trim(($req->name ?? '').' '.($req->description ?? '')));
        $rules = [
            ['key' => 'identity', 'label' => 'Identity & KYC', 'order' => 1, 'needles' => ['national id', 'passport', 'face', 'identity', 'nida', 'photo of id']],
            ['key' => 'income', 'label' => 'Income verification', 'order' => 2, 'needles' => ['income', 'income verification', 'bank statement', 'mobile money', 'mobile statement', 'payslip', 'salary', 'statement', 'revenue', 'source of income']],
            ['key' => 'business', 'label' => 'Business & activity', 'order' => 3, 'needles' => ['business', 'licence', 'license', 'shop', 'farm', 'workshop', 'invoice', 'supplier', 'buyer', 'off-taker', 'fundi', 'employer']],
            ['key' => 'collateral', 'label' => 'Collateral & assets', 'order' => 4, 'needles' => ['collateral', 'vehicle', 'logbook', 'insurance', 'valuation', 'ownership', 'title deed', 'asset']],
            ['key' => 'group', 'label' => 'Group', 'order' => 5, 'needles' => ['group constitution', 'member roster', 'group member']],
            ['key' => 'supporting', 'label' => 'Supporting', 'order' => 6, 'needles' => ['reason', 'supporting', 'fee letter', 'hospital']],
        ];
        foreach ($rules as $rule) {
            foreach ($rule['needles'] as $needle) {
                if ($needle !== '' && str_contains($hay, $needle)) {
                    return ['key' => $rule['key'], 'label' => $rule['label'], 'order' => $rule['order']];
                }
            }
        }

        return ['key' => 'other', 'label' => 'Other documents', 'order' => 9];
    };

    // Members/guarantors: personal KYC only. Income is a loan-file item unless this
    // person was specifically asked to update bank / mobile-money / income proof.
    if ($isSubjectPanel) {
        $requirements = $requirements->filter(function ($req) use ($categoryFor, $subjectHasIncomeRequest) {
            $category = $categoryFor($req);
            if (in_array($category['key'], ['group', 'business', 'supporting', 'collateral'], true)) {
                return false;
            }

            $hay = strtolower(trim(($req->name ?? '').' '.($req->description ?? '')));
            $isIdentity = str_contains($hay, 'national id')
                || (str_contains($hay, 'passport') && ! str_contains($hay, 'photo'))
                || str_contains($hay, 'face')
                || str_contains($hay, 'nida')
                || str_contains($hay, 'photo of id');
            $isIncome = str_contains($hay, 'income')
                || str_contains($hay, 'bank statement')
                || str_contains($hay, 'mobile money')
                || str_contains($hay, 'payslip')
                || str_contains($hay, 'salary');

            if ($isIncome) {
                return $subjectHasIncomeRequest;
            }

            return $isIdentity;
        })->values();
    }

    $profileCodesForRequirement = function ($req): array {
        $hay = strtolower(trim((string) ($req->name ?? '')));

        return match (true) {
            str_contains($hay, 'national id') && str_contains($hay, 'front') => ['national_id_front'],
            str_contains($hay, 'national id') && str_contains($hay, 'back') => ['national_id_back'],
            str_contains($hay, 'national id') || str_contains($hay, 'nida') => ['national_id_front', 'national_id_back'],
            str_contains($hay, 'passport') => ['passport_photo', 'passport'],
            str_contains($hay, 'income') || str_contains($hay, 'bank statement') || str_contains($hay, 'mobile money') => [
                'bank_statement', 'mobile_money_statement', 'mpesa_statement', 'salary_slip',
            ],
            str_contains($hay, 'face') || str_contains($hay, 'selfie') => ['face_front', 'face_id'],
            default => [],
        };
    };

    $subjectProfileDoc = function (array $codes) use ($profileDocs) {
        if ($codes === []) {
            return null;
        }

        return $profileDocs->first(fn ($doc) => in_array($doc->documentType?->code, $codes, true));
    };

    $rows = $requirements->map(function ($req) use (
        $uploads,
        $histories,
        $guidanceMap,
        $categoryFor,
        $profileCodesForRequirement,
        $subjectProfileDoc,
        $docReviewService,
        $appReviews,
        $record,
        $isSubjectPanel
    ) {
        $category = $categoryFor($req);
        // Loan-file requirement uploads belong to the application (leader / borrower).
        // Member and guarantor desks only show that person's profile files.
        $upload = $isSubjectPanel ? null : $uploads->get($req->id);
        $history = $histories->get($req->id, collect());
        $fromProfile = false;
        $bucket = 'optional';
        $statusLabel = 'Optional';

        if (! $upload) {
            $profileDoc = $subjectProfileDoc($profileCodesForRequirement($req));
            if ($profileDoc) {
                $upload = $profileDoc;
                $fromProfile = true;
            }
        }

        if ($fromProfile) {
            $status = $appReviews->get($upload->id)?->status
                ?? $docReviewService->statusFor($record, $upload);
            $bucket = match (true) {
                in_array($status, ['verified', 'approved'], true) => 'verified',
                $status === 'rejected' => 'rejected',
                default => 'uploaded',
            };
            $statusLabel = match ($bucket) {
                'verified' => 'Verified',
                'rejected' => 'Rejected',
                default => 'On profile',
            };
        } elseif ($upload) {
            $isApproved = in_array($upload->status, ['verified', 'approved'], true);
            $bucket = match (true) {
                $isApproved => 'verified',
                $upload->status === 'rejected' => 'rejected',
                default => 'uploaded',
            };
            $statusLabel = match ($bucket) {
                'verified' => display_label($upload->status, 'document_status') ?: 'Verified',
                'rejected' => 'Rejected',
                default => display_label($upload->status, 'document_status') ?: 'Uploaded',
            };
        } elseif ($req->is_required) {
            $bucket = 'missing';
            $statusLabel = 'Missing';
        }

        return [
            'req' => $req,
            'upload' => $upload,
            'history' => $history,
            'guidance' => $guidanceMap->get($req->id, ['title' => 'What to verify', 'items' => []]),
            'bucket' => $bucket,
            'category' => $category,
            'isApproved' => $bucket === 'verified',
            'badgeMap' => match ($bucket) {
                'verified' => 'bg-emerald-100 text-emerald-800',
                'rejected' => 'bg-red-100 text-red-800',
                'uploaded' => 'bg-amber-100 text-amber-800',
                'missing' => 'bg-rose-50 text-rose-800',
                default => 'bg-gray-100 text-gray-600',
            },
            'statusLabel' => $statusLabel,
            'from_profile' => $fromProfile,
        ];
    })->values();

    $isIncomeAlternative = function ($req): bool {
        $hay = strtolower(trim(($req->name ?? '').' '.($req->description ?? '')));
        if (str_contains($hay, 'salary') || str_contains($hay, 'payslip') || str_contains($hay, 'employment contract')) {
            return false;
        }

        return str_contains($hay, 'bank statement')
            || str_contains($hay, 'mobile money')
            || str_contains($hay, 'mpesa')
            || str_contains($hay, 'income verification')
            || str_contains($hay, 'income proof')
            || str_contains($hay, 'source of income')
            || str_contains($hay, 'proof of income');
    };
    $incomeAltRows = $rows->filter(fn ($row) => $isIncomeAlternative($row['req']));
    if ($incomeAltRows->count() > 1) {
        $priority = ['verified' => 0, 'uploaded' => 1, 'rejected' => 2, 'missing' => 3, 'optional' => 4];
        $best = $incomeAltRows->sortBy(fn ($row) => $priority[$row['bucket']] ?? 9)->first();
        $keepId = $best['req']->id;
        $rows = $rows
            ->reject(fn ($row) => $isIncomeAlternative($row['req']) && (int) $row['req']->id !== (int) $keepId)
            ->map(function ($row) use ($keepId) {
                if ((int) $row['req']->id !== (int) $keepId) {
                    return $row;
                }
                $row['display_name'] = 'Income proof (bank or mobile money, 6 months)';

                return $row;
            })
            ->values();
    }

    $missingRows = $rows->where('bucket', 'missing')->values();
    $verifyRows = $rows->where('bucket', 'uploaded')->values();
    $rejectedRows = $rows->where('bucket', 'rejected')->values();
    $counts = [
        'all' => $rows->count(),
        'action' => $rows->whereIn('bucket', ['uploaded', 'rejected', 'missing'])->count(),
        'uploaded' => $verifyRows->count(),
        'missing' => $missingRows->count(),
        'verified' => $rows->where('bucket', 'verified')->count(),
        'rejected' => $rejectedRows->count(),
    ];
    $requiredDocsCount = $rows->filter(fn ($row) => (bool) ($row['req']->is_required ?? false))->count();
    $satisfiedDocsCount = $rows->where('bucket', 'verified')->count();
    $documentProgress = $requiredDocsCount > 0
        ? (int) round(($satisfiedDocsCount / $requiredDocsCount) * 100)
        : (int) ($review['document_progress'] ?? 0);
    $defaultFilter = match (true) {
        $counts['missing'] > 0 => 'missing',
        $counts['uploaded'] > 0 || $counts['rejected'] > 0 => 'action',
        default => 'all',
    };

    $groupedRows = $rows
        ->groupBy(fn ($row) => $row['category']['key'])
        ->sortBy(fn ($group) => $group->first()['category']['order'] ?? 99);

    $libraryTitle = 'Document library';
    $libraryByCategory = $profileDocs
        ->groupBy(fn ($doc) => strtolower((string) ($doc->documentType?->category ?: 'kyc')))
        ->sortKeys();

    $isLoanFileRequest = fn ($req) => ! $docRequestService->isProfileGuidedRequest($req);
    $loanRequestsForPanel = $documentRequestsForPanel->filter($isLoanFileRequest)->values();
    $profileRequestsForPanel = $documentRequestsForPanel->reject($isLoanFileRequest)->values();
    $loanReadyForPanel = $loanRequestsForPanel->where('status', 'uploaded')->values();
    $loanAwaitingForPanel = $loanRequestsForPanel
        ->filter(fn ($r) => in_array($r->status, ['pending', 'rejected'], true))
        ->values();
    $profileOpenForPanel = $profileRequestsForPanel
        ->filter(fn ($r) => in_array($r->status, ['pending', 'uploaded', 'rejected'], true))
        ->values();
    $openRequestCount = $loanReadyForPanel->count()
        + $loanAwaitingForPanel->count()
        + $profileOpenForPanel->filter(fn ($r) => $r->needsBorrowerAction() || $r->status === 'uploaded')->count();

    $openRequestPreview = $loanAwaitingForPanel
        ->merge($loanReadyForPanel)
        ->merge($profileOpenForPanel->filter(fn ($r) => $r->needsBorrowerAction() || $r->status === 'uploaded'))
        ->take(4)
        ->values();

    // Same landing tab for every person so screening does not swap layouts.
    $defaultPanel = (string) request('docs_panel', 'checklist');
    if (! in_array($defaultPanel, ['checklist', 'requests', 'library'], true)) {
        $defaultPanel = 'checklist';
    }
@endphp

<div
    class="space-y-4"
    x-data="{
        panel: @js($defaultPanel),
        filter: @js($defaultFilter),
        openId: null,
        match(bucket) {
            if (this.filter === 'all') return true;
            if (this.filter === 'action') return ['uploaded', 'rejected', 'missing'].includes(bucket);
            return this.filter === bucket;
        },
        showMissing() {
            this.panel = 'checklist';
            this.filter = 'missing';
            this.openId = null;
        },
        showRequests(openComposer = false) {
            this.panel = 'requests';
            this.openId = null;
            this.$nextTick(() => {
                if (openComposer) {
                    window.dispatchEvent(new CustomEvent('kf-open-doc-composer'));
                    document.getElementById('request-more-documents')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    return;
                }
                document.getElementById('review-document-pipeline')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    }">
    <div class="rounded-2xl ring-1 ring-brand/10 bg-white shadow-sm overflow-hidden">
        <div class="px-4 sm:px-5 py-4 bg-gradient-to-r from-brand via-brand-light to-brand text-white">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Documents</p>
                    <h2 class="text-sm font-semibold text-white mt-0.5">{{ $evidenceTitle }}</h2>
                    <p class="text-xs text-white/75 mt-0.5">
                        @if ($isSubjectPanel && $subjectName)
                            {{ $subjectName }} ·
                        @endif
                        {{ $satisfiedDocsCount }}/{{ $requiredDocsCount }} required verified
                        @if ($counts['uploaded'] > 0)
                            · <span class="text-brand-gold font-semibold">{{ $counts['uploaded'] }} to verify</span>
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if ($counts['missing'] > 0)
                        <button type="button"
                                @click="showMissing()"
                                class="inline-flex items-center gap-1.5 rounded-full bg-white/15 text-white text-xs font-bold px-3 py-1.5 ring-1 ring-white/25 hover:bg-white/25 transition">
                            <span class="size-1.5 rounded-full bg-rose-300"></span>
                            {{ $counts['missing'] }} missing
                        </button>
                    @endif
                    @if ($openRequestCount > 0)
                        <button type="button"
                                @click="showRequests()"
                                class="inline-flex items-center gap-1.5 rounded-full bg-brand-gold text-brand text-xs font-bold px-3 py-1.5 shadow-sm hover:brightness-95 transition">
                            {{ $openRequestCount }} open request{{ $openRequestCount === 1 ? '' : 's' }}
                        </button>
                    @endif
                    <div class="h-2 w-28 bg-white/20 rounded-full overflow-hidden ring-1 ring-white/20">
                        <div class="h-full bg-brand-gold transition-all" style="width: {{ $documentProgress }}%"></div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-1.5" role="tablist" aria-label="Document panels">
                <button type="button"
                        role="tab"
                        @click="panel = 'checklist'"
                        :aria-selected="(panel === 'checklist').toString()"
                        :class="panel === 'checklist'
                            ? 'bg-brand-gold text-brand shadow-sm'
                            : 'bg-white/10 text-white ring-white/20 hover:bg-white/20'"
                        class="rounded-xl px-3.5 py-2 text-xs font-semibold ring-1 ring-transparent transition">
                    Checklist
                </button>
                <button type="button"
                        role="tab"
                        @click="showRequests()"
                        :aria-selected="(panel === 'requests').toString()"
                        :class="panel === 'requests'
                            ? 'bg-brand-gold text-brand shadow-sm'
                            : 'bg-white/10 text-white ring-white/20 hover:bg-white/20'"
                        class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-semibold ring-1 ring-transparent transition">
                    Requested
                    @if ($openRequestCount > 0)
                        <span class="inline-flex min-w-[1.25rem] justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                              :class="panel === 'requests' ? 'bg-brand/15 text-brand' : 'bg-white/20 text-white'">
                            {{ $openRequestCount }}
                        </span>
                    @endif
                </button>
                <button type="button"
                        role="tab"
                        @click="panel = 'library'"
                        :aria-selected="(panel === 'library').toString()"
                        :class="panel === 'library'
                            ? 'bg-brand-gold text-brand shadow-sm'
                            : 'bg-white/10 text-white ring-white/20 hover:bg-white/20'"
                        class="rounded-xl px-3.5 py-2 text-xs font-semibold ring-1 ring-transparent transition">
                    Library
                </button>
            </div>
        </div>

        {{-- Checklist --}}

        {{-- Checklist --}}
        <div x-show="panel === 'checklist'" role="tabpanel" class="p-4 sm:p-5 space-y-4">
            <div class="flex flex-wrap gap-1.5">
                @foreach ([
                    'missing' => 'Missing ('.$counts['missing'].')',
                    'action' => 'Action ('.$counts['action'].')',
                    'uploaded' => 'Verify ('.$counts['uploaded'].')',
                    'verified' => 'Done ('.$counts['verified'].')',
                    'all' => 'All ('.$counts['all'].')',
                ] as $key => $label)
                    <button type="button"
                            @click="filter = @js($key); openId = null"
                            :class="filter === @js($key)
                                ? 'bg-brand text-white ring-brand'
                                : 'bg-white text-gray-700 ring-brand/15 hover:bg-brand-muted/40'"
                            class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ring-1 transition">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($rows->isEmpty())
                <p class="text-sm text-gray-500">No document requirements configured for this loan product.</p>
            @else
                <div class="space-y-4">
                    @foreach ($groupedRows as $categoryKey => $categoryRows)
                        @php
                            $catLabel = $categoryRows->first()['category']['label'] ?? 'Documents';
                            $catAction = $categoryRows->whereIn('bucket', ['uploaded', 'rejected', 'missing'])->count();
                        @endphp
                        <section class="rounded-xl ring-1 ring-brand/10 overflow-hidden bg-white shadow-sm"
                                 x-show="@js($categoryRows->pluck('bucket')->values()->all()).some((bucket) => match(bucket))">
                            <div class="px-3.5 py-2.5 bg-gradient-to-r from-brand-muted/60 to-white border-b border-brand/10 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ $catLabel }}</p>
                                    <p class="text-[11px] text-gray-500 mt-0.5">{{ $categoryRows->count() }} document{{ $categoryRows->count() === 1 ? '' : 's' }}</p>
                                </div>
                                @if ($catAction > 0)
                                    <span class="inline-flex rounded-full bg-amber-100 text-amber-950 text-[10px] font-bold px-2 py-0.5 ring-1 ring-amber-200">
                                        {{ $catAction }} need action
                                    </span>
                                @endif
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach ($categoryRows as $row)
                                    @php
                                        $req = $row['req'];
                                        $upload = $row['upload'];
                                        $history = $row['history'];
                                        $guidance = $row['guidance'];
                                    @endphp
                                    <div x-show="match(@js($row['bucket']))" class="bg-white">
                                        <div class="w-full px-3.5 py-3.5 flex items-center gap-3">
                                            <div class="shrink-0 w-12 h-12 rounded-lg overflow-hidden ring-1 ring-brand/10 bg-gray-50 flex items-center justify-center [&_button]:!w-full [&_button]:!h-full [&_button]:!rounded-lg">
                                                @if ($upload?->file_path)
                                                    <x-admin.document-preview
                                                        :url="asset('storage/'.$upload->file_path)"
                                                        label="View"
                                                        variant="thumbnail" />
                                                @else
                                                    <span class="text-[10px] font-bold uppercase tracking-wide text-rose-500">Missing</span>
                                                @endif
                                            </div>
                                            <button type="button"
                                                    class="min-w-0 flex-1 flex items-center gap-3 text-left hover:bg-brand-muted/20 rounded-lg px-1 py-0.5 -mx-1 transition"
                                                    @click="openId = openId === {{ (int) $req->id }} ? null : {{ (int) $req->id }}">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $row['display_name'] ?? $req->name }}</p>
                                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold {{ $row['badgeMap'] }}">{{ $row['statusLabel'] }}</span>
                                                        @if ($req->is_required)
                                                            <span class="text-[10px] uppercase tracking-widest font-semibold text-gray-400">Req</span>
                                                        @endif
                                                    </div>
                                                    @if ($req->description)
                                                        <p class="text-[11px] text-gray-500 mt-0.5 truncate">{{ $req->description }}</p>
                                                    @endif
                                                </div>
                                                <span class="shrink-0 inline-flex items-center gap-1.5 text-[11px] font-semibold text-brand/70">
                                                    <span x-text="openId === {{ (int) $req->id }} ? 'Collapse' : 'Expand'"></span>
                                                    <svg class="size-5 text-brand/60 transition" :class="openId === {{ (int) $req->id }} ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
                                                </span>
                                            </button>
                                        </div>

                                        <div x-show="openId === {{ (int) $req->id }}" x-cloak class="px-3.5 pb-4 space-y-3 border-t border-brand/5 bg-gradient-to-b from-brand-muted/20 to-white">
                                            @if (! empty($guidance['items']))
                                                <div class="pt-3">
                                                    <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-1.5">{{ $guidance['title'] ?? 'What to verify' }}</p>
                                                    <ul class="space-y-1">
                                                        @foreach ($guidance['items'] as $item)
                                                            <li class="text-xs text-gray-800 flex items-start gap-2">
                                                                <span class="text-brand-gold shrink-0">✓</span>
                                                                <span>{{ $item }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                            @if ($upload)
                                                <div class="flex flex-wrap items-start gap-3 pt-1">
                                                    @if ($row['bucket'] !== 'verified')
                                                        <form method="POST" action="{{ route('admin.loan-applications.documents.verify', [$record, $upload]) }}" class="inline">
                                                            @csrf
                                                            <input type="hidden" name="review_person" value="{{ $isMemberSubject ? 'member' : ($isGuarantorSubject ? 'guarantor' : ($deskPerson ?? $panelPerson ?? request('review_person', 'borrower'))) }}">
                                                            @if ($isMemberSubject && $memberId)
                                                                <input type="hidden" name="review_m" value="{{ $memberId }}">
                                                            @elseif ($deskM ?? $panelM ?? request('review_m'))
                                                                <input type="hidden" name="review_m" value="{{ $deskM ?? $panelM ?? request('review_m') }}">
                                                            @endif
                                                            @if ($deskG ?? $panelG ?? request('review_g'))
                                                                <input type="hidden" name="review_g" value="{{ $deskG ?? $panelG ?? request('review_g') }}">
                                                            @endif
                                                            <button type="submit" class="text-xs font-semibold text-emerald-800 bg-emerald-50 ring-1 ring-emerald-200 px-3 py-1.5 rounded-lg hover:bg-emerald-100">
                                                                Verify
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if ($row['bucket'] !== 'rejected')
                                                        <form method="POST"
                                                              action="{{ route('admin.loan-applications.documents.reject', [$record, $upload]) }}"
                                                              class="flex flex-col gap-2 min-w-[16rem] max-w-full"
                                                              x-data="{ requestAgain: false }"
                                                              @submit.prevent="window.confirmForm($el, {
                                                                  title: @js('Reject this document?'),
                                                                  message: @js('Reject this document? The borrower may need to re-upload.'),
                                                                  confirmLabel: @js('Reject'),
                                                                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                                                  tone: 'warning',
                                                              })">
                                                            @csrf
                                                            <input type="hidden" name="fail_reason_code" value="incomplete">
                                                            <input type="hidden" name="review_person" value="{{ $isMemberSubject ? 'member' : ($isGuarantorSubject ? 'guarantor' : ($deskPerson ?? $panelPerson ?? request('review_person', 'borrower'))) }}">
                                                            @if ($isMemberSubject && $memberId)
                                                                <input type="hidden" name="review_m" value="{{ $memberId }}">
                                                            @else
                                                                <input type="hidden" name="review_m" value="{{ $deskM ?? $panelM ?? request('review_m') }}">
                                                            @endif
                                                            <input type="hidden" name="review_g" value="{{ $deskG ?? $panelG ?? request('review_g') }}">
                                                            <button type="submit" class="self-start text-xs font-semibold text-red-700 bg-red-50 ring-1 ring-red-200 px-3 py-1.5 rounded-lg hover:bg-red-100">
                                                                Reject
                                                            </button>
                                                            <label class="block text-[10px] uppercase tracking-widest font-semibold text-gray-500">Reject reason (optional)</label>
                                                            <textarea name="notes"
                                                                      rows="2"
                                                                      maxlength="500"
                                                                      placeholder="Why is this rejected?"
                                                                      class="w-full rounded-lg border-brand/15 text-xs ring-1 ring-brand/10 px-3 py-2 focus:border-brand focus:ring-brand/15"></textarea>
                                                            <label class="inline-flex items-start gap-2 text-xs text-gray-700 cursor-pointer">
                                                                <input type="checkbox"
                                                                       name="request_again"
                                                                       value="1"
                                                                       x-model="requestAgain"
                                                                       class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand/30">
                                                                <span>Request again from borrower</span>
                                                            </label>
                                                            <div x-show="requestAgain" x-cloak class="space-y-1">
                                                                <label class="block text-[10px] uppercase tracking-widest font-semibold text-gray-500">Request label</label>
                                                                <input type="text"
                                                                       name="request_again_label"
                                                                       value="{{ $req->name }}"
                                                                       maxlength="160"
                                                                       :disabled="!requestAgain"
                                                                       placeholder="What should they re-upload?"
                                                                       class="w-full rounded-lg border-brand/15 text-xs ring-1 ring-brand/10 px-3 py-2 focus:border-brand focus:ring-brand/15 disabled:opacity-50">
                                                                <p class="text-[11px] text-gray-500">Uses the reject reason above in the borrower request instructions.</p>
                                                            </div>
                                                        </form>
                                                    @endif
                                                    @if ($upload->file_path)
                                                        <x-admin.document-preview
                                                            :url="asset('storage/'.$upload->file_path)"
                                                            label="Open full"
                                                            variant="link" />
                                                    @endif
                                                </div>
                                                <p class="text-[11px] text-gray-500">
                                                    {{ ! empty($row['from_profile']) ? 'Profile file' : 'Latest upload' }}
                                                    · {{ $upload->created_at?->format('d M Y, H:i') }}
                                                </p>
                                            @else
                                                <p class="text-sm text-gray-500 pt-3">
                                                    @if ($isSubjectPanel)
                                                        No matching profile file yet. Request an update from this person, or review under Library when they upload.
                                                    @else
                                                        No file uploaded for this requirement yet. Ask the borrower to upload, or use Request documents under Requests.
                                                    @endif
                                                </p>
                                            @endif

                                            @if ($history->count() > 1)
                                                <details class="text-xs">
                                                    <summary class="font-semibold text-gray-600 cursor-pointer">{{ $history->count() }} upload version(s)</summary>
                                                    <ul class="mt-2 space-y-1 text-gray-600">
                                                        @foreach ($history as $version)
                                                            <li class="flex flex-wrap items-center gap-2">
                                                                <span>{{ $version->created_at?->format('d M Y, H:i') }}</span>
                                                                <span class="font-medium">{{ display_label($version->status, 'document_status') }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </details>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-500" x-show="filter === 'missing' && {{ $counts['missing'] }} === 0">
                    No missing documents.
                </p>
            @endif
        </div>

        {{-- Requests --}}
        <div x-show="panel === 'requests'" x-cloak role="tabpanel" class="p-4 sm:p-5 space-y-4 bg-gradient-to-b from-white to-brand-muted/10">
            @include('admin.loan-applications.review._document-requests', [
                'documentRequests' => $documentRequestsForPanel,
                'person' => $isMemberSubject ? 'member' : ($isGuarantorSubject ? 'guarantor' : ($deskPerson ?? $panelPerson ?? request('review_person', request('person', 'borrower')))),
                'lockRequestSubject' => true,
                'requestMemberId' => $isMemberSubject ? $memberId : null,
                'requestSubjectCustomerId' => $subjectCustomerId > 0 ? $subjectCustomerId : null,
            ])
        </div>

        {{-- Library --}}
        <div x-show="panel === 'library'" x-cloak role="tabpanel" class="p-4 sm:p-5 space-y-4">
            @php
                $libraryPending = $profileDocs->filter(function ($doc) use ($docReviewService, $record, $appReviews) {
                    $status = $appReviews->get($doc->id)?->status
                        ?? $docReviewService->statusFor($record, $doc);

                    return ! in_array($status, ['verified', 'approved', 'rejected'], true);
                })->count();
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-900">
                    {{ $libraryTitle }}
                    @if ($subjectName)
                        <span class="font-normal text-gray-500">· {{ $subjectName }}</span>
                    @endif
                    @if ($libraryPending > 0)
                        <span class="text-amber-700 font-bold tabular-nums">· {{ $libraryPending }} pending</span>
                    @endif
                </h3>
                @if ($libraryPending > 0 && auth()->user()?->hasPermission('applications.review'))
                    <form method="POST"
                          action="{{ route('admin.loan-applications.documents.verify-all', $record) }}"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js('Verify all pending?'),
                              message: @js('Clears pending profile files for this person on this application.'),
                              confirmLabel: @js('Verify all'),
                              confirmClass: 'bg-emerald-700 hover:bg-emerald-800 text-white',
                              tone: 'confirm',
                          })">
                        @csrf
                        <input type="hidden" name="review_person" value="{{ $isMemberSubject ? 'member' : ($isGuarantorSubject ? 'guarantor' : 'borrower') }}">
                        @if ($isGuarantorSubject)
                            <input type="hidden" name="review_g" value="{{ request('review_g', request('g')) }}">
                        @endif
                        @if ($isMemberSubject && $memberId)
                            <input type="hidden" name="review_m" value="{{ $memberId }}">
                        @endif
                        <button type="submit" class="inline-flex rounded-lg bg-brand text-white text-[11px] font-bold px-3 py-1.5">
                            Verify all ({{ $libraryPending }})
                        </button>
                    </form>
                @endif
            </div>

            @if ($profileDocs->isEmpty())
                <p class="text-sm text-gray-500">No documents.</p>
            @else
                <div class="space-y-4">
                    @foreach ($libraryByCategory as $libCat => $libDocs)
                        <section class="rounded-xl ring-1 ring-brand/10 overflow-hidden bg-white">
                            <div class="px-3.5 py-2 bg-gradient-to-r from-brand-muted/50 to-white border-b border-brand/10">
                                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ $libCat }}</p>
                            </div>
                            <div class="p-3 grid sm:grid-cols-2 gap-3">
                                @foreach ($libDocs as $doc)
                                    @include('admin.loan-applications.review._document_review_card', [
                                        'doc' => $doc,
                                        'appReview' => $appReviews->get($doc->id),
                                        'reviewPerson' => $isMemberSubject ? 'member' : ($isGuarantorSubject ? 'guarantor' : 'borrower'),
                                        'reviewG' => request('review_g', request('g')),
                                        'reviewM' => $isMemberSubject ? $memberId : request('review_m', request('m')),
                                    ])
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
