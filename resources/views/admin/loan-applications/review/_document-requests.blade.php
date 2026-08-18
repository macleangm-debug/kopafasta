@php
    $person = ($person ?? null)
        ?? ($deskPerson ?? null)
        ?? ($panelPerson ?? null)
        ?? request('review_person', request('person', 'borrower'));
    if (! in_array($person, ['borrower', 'guarantor', 'member'], true)) {
        $person = 'borrower';
    }

    $docService = app(\App\Services\ApplicationDocumentRequestService::class);
    $presets = $docService::PRESET_LABELS;
    $assetPresets = $docService::ASSET_BACKED_PRESET_LABELS;
    $collateralPresets = $docService::COLLATERAL_PRESET_LABELS;
    $identityPresets = ['Updated National ID', 'New National ID photo', 'New face verification photo', 'Identity verification photo', 'Image Not Clear'];
    $record->loadMissing('product');
    $isAssetProduct = app(\App\Services\AssetBackedLoanService::class)->isAssetBackedApplication($record)
        || app(\App\Services\AssetLendingService::class)->isAssetLendingApplication($record);
    // Keep every PRESET_LABELS option available (former workflow return-for-docs list).
    $generalPresets = $isAssetProduct
        ? array_values(array_diff($presets, $assetPresets, $collateralPresets, $identityPresets))
        : array_values(array_diff($presets, $collateralPresets, $identityPresets));
    $documentRequests = collect($documentRequests ?? []);
    $lockRequestSubject = (bool) ($lockRequestSubject ?? false);
    $requestMemberId = (int) ($requestMemberId ?? 0);
    $requestSubjectCustomerId = (int) ($requestSubjectCustomerId ?? 0);
    if ($lockRequestSubject) {
        $documentRequests = $documentRequests->filter(
            fn ($req) => $docService->targetsReviewSubject(
                $req,
                $person,
                $requestSubjectCustomerId > 0
                    ? $requestSubjectCustomerId
                    : (int) ($record->customer_id ?? 0),
                $requestMemberId > 0 ? $requestMemberId : null,
                (int) ($record->customer_id ?? 0),
            )
        )->values();
    }
    $canRequestDocs = auth()->user()?->hasPermission('applications.request_documents');

    $isLoanFileRequest = fn ($req) => ! $docService->isProfileGuidedRequest($req);

    $loanRequests = $documentRequests->filter($isLoanFileRequest)->values();
    $profileRequests = $documentRequests->reject($isLoanFileRequest)->values();

    $loanReady = $loanRequests->where('status', 'uploaded')->values();
    $loanCompleted = $loanRequests->where('status', 'satisfied')->values();
    $loanAwaiting = $loanRequests->filter(fn ($r) => in_array($r->status, ['pending', 'rejected'], true))->values();
    $profileOpen = $profileRequests->filter(fn ($r) => in_array($r->status, ['pending', 'uploaded', 'rejected'], true))->values();

    $openRequestCount = $loanReady->count()
        + $loanAwaiting->count()
        + $profileOpen->filter(fn ($r) => $r->needsBorrowerAction() || $r->status === 'uploaded')->count();
@endphp

@if ($loanReady->isNotEmpty() || $loanCompleted->filter(fn ($r) => $r->uploads->isNotEmpty())->isNotEmpty() || $loanAwaiting->isNotEmpty() || $profileOpen->isNotEmpty())
    <section id="review-document-pipeline" class="scroll-mt-24 space-y-5">
        @if ($loanAwaiting->isNotEmpty() || $profileOpen->isNotEmpty())
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-amber-50/80 to-white flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">1 · Requested</p>
                        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">
                            Waiting on
                            @if ($person === 'guarantor')
                                guarantor / borrower
                            @elseif ($person === 'member')
                                group member / leader
                            @else
                                borrower
                            @endif
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Open requests. Profile-linked items (ID, face, income, collateral) are fulfilled on the profile — loan-file uploads appear under Received when submitted.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($openRequestCount > 0)
                            <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-900 text-xs font-semibold px-3 py-1 ring-1 ring-amber-200">
                                {{ $openRequestCount }} open
                            </span>
                        @endif
                        @if ($canRequestDocs)
                            <button type="button"
                                    @click="window.dispatchEvent(new CustomEvent('kf-open-doc-composer')); $nextTick(() => document.getElementById('request-more-documents')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                                    class="inline-flex items-center rounded-lg bg-brand-gold hover:brightness-95 text-brand text-[11px] font-bold px-2.5 py-1.5">
                                Request more →
                            </button>
                        @endif
                    </div>
                </div>
                <div class="p-5 sm:p-6 space-y-5">
                    @if ($loanAwaiting->isNotEmpty())
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Loan file uploads · {{ $loanAwaiting->count() }}</p>
                            <div class="grid md:grid-cols-2 gap-3">
                                @foreach ($loanAwaiting as $docReq)
                                    <div class="rounded-xl ring-1 ring-gray-200 bg-white px-4 py-3 flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900 text-sm">{{ $docReq->label }}</p>
                                            <p class="text-xs text-brand mt-0.5">{{ $docReq->subjectRoleLabel($groupReview ?? null) }}</p>
                                            @if ($docReq->instructions)
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $docReq->instructions }}</p>
                                            @endif
                                        </div>
                                        <span @class([
                                            'shrink-0 inline-flex px-2 py-0.5 rounded text-xs font-semibold',
                                            'bg-red-100 text-red-800' => $docReq->status === 'rejected',
                                            'bg-amber-100 text-amber-900' => $docReq->status !== 'rejected',
                                        ])>{{ $docReq->status === 'rejected' ? 'Re-upload' : 'Requested' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($profileOpen->isNotEmpty())
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-sky-700 mb-3">Profile updates · {{ $profileOpen->count() }}</p>
                            <div class="grid md:grid-cols-2 gap-3">
                                @foreach ($profileOpen as $docReq)
                                    @php $kind = $docService->borrowerActionKind($docReq); @endphp
                                    <div class="rounded-xl ring-1 ring-sky-100 bg-sky-50/40 px-4 py-3 flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900 text-sm">{{ $docReq->label }}</p>
                                            <p class="text-xs text-brand mt-0.5">{{ $docReq->subjectRoleLabel($groupReview ?? null) }}</p>
                                            <p class="text-xs text-sky-800/80 mt-0.5">
                                                Updated in profile ({{ $kind }}) — review under Personal / Face / Collateral.
                                            </p>
                                        </div>
                                        <span class="shrink-0 inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-sky-100 text-sky-900">
                                            {{ $docReq->status === 'uploaded' ? 'Done in profile' : ($docReq->status === 'rejected' ? 'Retry' : 'Requested') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($loanReady->isNotEmpty())
            <div id="review-loan-request-uploads" class="scroll-mt-24 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-sky-50/80 to-white">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">2 · Received</p>
                    <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Ready for review</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Loan-file uploads stay here for screening and committee until you accept or reject them.
                    </p>
                </div>
                <div class="p-5 sm:p-6">
                    <div class="grid md:grid-cols-2 gap-3">
                        @foreach ($loanReady as $docReq)
                            <div class="rounded-xl ring-1 ring-amber-200 bg-amber-50/40 p-4">
                                <div class="flex flex-wrap items-start gap-4">
                                    @php $latestUpload = $docReq->uploads->sortByDesc('id')->first(); @endphp
                                    @if ($latestUpload?->file_path)
                                        <x-admin.document-preview
                                            :url="asset('storage/'.$latestUpload->file_path)"
                                            label="View"
                                            variant="thumbnail" />
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">Received</span>
                                        </div>
                                        <p class="text-xs text-brand mt-0.5">{{ $docReq->subjectRoleLabel($groupReview ?? null) }}</p>
                                        @if ($docReq->instructions)
                                            <p class="text-sm text-gray-600 mt-1">{{ $docReq->instructions }}</p>
                                        @endif
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($latestUpload?->file_path)
                                                <x-admin.document-preview
                                                    :url="asset('storage/'.$latestUpload->file_path)"
                                                    label="Open full size" />
                                            @endif
                                            @if ($canRequestDocs)
                                                <form method="POST" action="{{ route('admin.loan-application-document-requests.satisfy', $docReq) }}">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 px-3 py-1.5 rounded-lg">
                                                        Accept
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.loan-application-document-requests.reject', $docReq) }}" class="flex items-center gap-2 flex-wrap">
                                                    @csrf
                                                    <input type="text" name="notes" required maxlength="500" placeholder="Reason for rejection"
                                                           class="rounded-lg border-gray-300 text-xs ring-1 ring-gray-200 px-3 py-2 w-40 max-w-full">
                                                    <button type="submit" class="text-xs font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-3 py-1.5 rounded-lg">
                                                        Reject
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
            </div>
        @endif

        @php $closedWithFiles = $loanCompleted->filter(fn ($r) => $r->uploads->isNotEmpty()); @endphp
        @if ($closedWithFiles->isNotEmpty())
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-emerald-50/70 to-white">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">3 · Accepted</p>
                    <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Reviewed for this loan</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Accepted uploads remain visible for committee — they do not leave this file.
                    </p>
                </div>
                <div class="p-5 sm:p-6">
                    <div class="grid md:grid-cols-2 gap-3">
                        @foreach ($closedWithFiles as $docReq)
                            @php
                                $statusClass = match ($docReq->status) {
                                    'satisfied' => 'bg-emerald-100 text-emerald-700',
                                    'rejected'  => 'bg-red-100 text-red-700',
                                    default     => 'bg-gray-100 text-gray-600',
                                };
                                $latestUpload = $docReq->uploads->sortByDesc('id')->first();
                            @endphp
                            <div class="rounded-xl ring-1 ring-gray-200 bg-white px-4 py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0 flex items-center gap-3">
                                    @if ($latestUpload?->file_path)
                                        <x-admin.document-preview
                                            :url="asset('storage/'.$latestUpload->file_path)"
                                            label="View"
                                            variant="thumbnail" />
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $docReq->label }}</p>
                                        @if ($docReq->admin_notes && $docReq->status === 'rejected')
                                            <p class="text-xs text-red-700 mt-0.5">{{ $docReq->admin_notes }}</p>
                                        @endif
                                    </div>
                                </div>
                                <span class="shrink-0 text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusClass }}">
                                    {{ $docReq->status === 'satisfied' ? 'Accepted' : ucfirst($docReq->status) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </section>
@else
    <section id="review-document-pipeline" class="scroll-mt-24 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 bg-gradient-to-r from-brand-muted/40 to-white">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Requested</p>
            <h2 class="text-sm font-semibold text-gray-900 mt-0.5">No open requests for this person</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Use Request documents below to send ID, income, residence, or a custom request. It notifies this person only.
            </p>
        </div>
    </section>
@endif

@if ($canRequestDocs)
    @php
        $composerStartsOpen = $errors->hasAny(['presets', 'label', 'instructions', 'type', 'request_subject']);
    @endphp
    <section id="request-more-documents" class="scroll-mt-24 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden" x-data="{
        open: {{ $composerStartsOpen ? 'true' : 'false' }},
        applyPack(labels) {
            this.open = true;
            this.$nextTick(() => {
                this.$root.querySelectorAll('input[type=checkbox][name^=presets]').forEach((el) => {
                    el.checked = labels.includes(el.value);
                });
            });
        }
    }" @kf-open-doc-composer.window="open = true; if ($event.detail?.labels) applyPack($event.detail.labels)">
        <div class="px-5 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
            <div x-show="open" x-cloak>
                <h2 class="text-sm font-semibold text-gray-900">Request documents</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Send a pack to the person on this screen. They are notified.
                    @if ($openRequestCount > 0)
                        You can send more while other requests are still open.
                    @endif
                </p>
            </div>
            <div x-show="!open">
                <p class="text-sm font-semibold text-gray-900 sr-only">Request documents</p>
            </div>
            <div class="flex flex-wrap gap-1.5 ml-auto">
                <div x-show="open" x-cloak class="flex flex-wrap gap-1.5">
                <button type="button" @click="applyPack(['Updated National ID', 'New National ID photo', 'New face verification photo', 'Image Not Clear'])"
                        class="rounded-lg bg-sky-50 text-sky-900 text-[11px] font-bold px-2.5 py-1.5 ring-1 ring-sky-100">ID pack</button>
                <button type="button" @click="applyPack(['Updated Bank Statement'])"
                        class="rounded-lg bg-amber-50 text-amber-950 text-[11px] font-bold px-2.5 py-1.5 ring-1 ring-amber-100">Bank statement</button>
                <button type="button" @click="applyPack(['Updated Mobile Money Statement'])"
                        class="rounded-lg bg-amber-50 text-amber-950 text-[11px] font-bold px-2.5 py-1.5 ring-1 ring-amber-100">Mobile money</button>
                <button type="button" @click="applyPack(['Guarantor residence letter'])"
                        class="rounded-lg bg-emerald-50 text-emerald-950 text-[11px] font-bold px-2.5 py-1.5 ring-1 ring-emerald-100">Residence pack</button>
                </div>
                <button type="button"
                        @click="open = !open"
                        class="inline-flex items-center gap-1 text-[11px] font-bold text-brand bg-brand-gold hover:brightness-95 px-2.5 py-1.5 rounded-lg">
                    <span x-text="open ? 'Hide' : 'Request documents'"></span>
                </button>
            </div>
        </div>
        <div x-show="open" x-cloak class="border-t border-brand/10">
            <form method="POST"
                  action="{{ route('admin.loan-applications.document-requests.store', $record) }}"
                  class="p-5 sm:p-6 space-y-5"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js('Send document request?'),
                      message: @js('The member will be notified and this moves to Requested on the shared file.'),
                      confirmLabel: @js('Send request'),
                      confirmClass: 'bg-brand-gold hover:brightness-95 text-brand',
                      tone: 'confirm',
                  })">
                @csrf
                @php
                    $groupMembersForRequest = collect($groupReview['members'] ?? [])->values();
                    $guarantorsForRequest = collect($review['guarantors'] ?? [])->values();
                    if ($guarantorsForRequest->isEmpty()) {
                        $record->loadMissing('customerGuarantors.guarantor');
                        $guarantorsForRequest = collect($record->customerGuarantors ?? [])->map(function ($link) {
                            return [
                                'link_id' => $link->id,
                                'customer_id' => $link->guarantor_id,
                                'name' => $link->guarantor?->full_name ?? 'Guarantor',
                            ];
                        })->values();
                    }

                    $panelPerson = $person
                        ?? request('review_person', request('person', 'borrower'));
                    if (! in_array($panelPerson, ['borrower', 'guarantor', 'member'], true)) {
                        $panelPerson = 'borrower';
                    }

                    $defaultRequestSubject = 'borrower';
                    $lockedMemberId = $requestMemberId;
                    $lockedSubjectCustomerId = $requestSubjectCustomerId;
                    $mId = 0;
                    $gLinkId = 0;

                    if ($panelPerson === 'member') {
                        $mId = $lockedMemberId
                            ?: (int) request('review_m', request('m', 0));
                        if ($mId < 1 && $lockedSubjectCustomerId > 0) {
                            $match = $groupMembersForRequest->firstWhere('customer_id', $lockedSubjectCustomerId);
                            $mId = (int) ($match['id'] ?? 0);
                        }
                        if ($mId > 0) {
                            $defaultRequestSubject = 'member:'.$mId;
                        }
                    } elseif ($panelPerson === 'guarantor') {
                        $gLinkId = (int) request('review_g', request('g', 0));
                        $gLink = $guarantorsForRequest->firstWhere('link_id', $gLinkId)
                            ?? ($lockedSubjectCustomerId > 0
                                ? $guarantorsForRequest->firstWhere('customer_id', $lockedSubjectCustomerId)
                                : null)
                            ?? $guarantorsForRequest->first();
                        if ($gLink) {
                            $gLinkId = (int) ($gLink['link_id'] ?? $gLinkId);
                            $lockedSubjectCustomerId = (int) ($gLink['customer_id'] ?? $lockedSubjectCustomerId);
                            $defaultRequestSubject = 'guarantor:'.$lockedSubjectCustomerId;
                        }
                    } elseif (old('request_subject')) {
                        $defaultRequestSubject = (string) old('request_subject');
                    }

                    $lockRequestSubject = (bool) ($lockRequestSubject ?? false);
                    $showSubjectPicker = ! $lockRequestSubject
                        && ($groupMembersForRequest->isNotEmpty() || $guarantorsForRequest->isNotEmpty());
                @endphp
                <input type="hidden" name="review_person" value="{{ $panelPerson }}">
                @if ($panelPerson === 'member' && $mId > 0)
                    <input type="hidden" name="review_m" value="{{ $mId }}">
                @endif
                @if ($panelPerson === 'guarantor')
                    @if ($gLinkId > 0)
                        <input type="hidden" name="review_g" value="{{ $gLinkId }}">
                    @endif
                    @if ($lockedSubjectCustomerId > 0)
                        <input type="hidden" name="subject_customer_id" value="{{ $lockedSubjectCustomerId }}">
                    @endif
                @endif
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Type</label>
                        <select name="type" class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5 focus:border-brand focus:ring-brand/15">
                            <option value="document">Document upload</option>
                            <option value="clarification">Clarification</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Due date (optional)</label>
                        <input type="date" name="due_at"
                               min="{{ now()->toDateString() }}"
                               value="{{ old('due_at') }}"
                               class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5 focus:border-brand focus:ring-brand/15">
                        <p class="mt-1 text-[11px] text-gray-500">Today or a future date only.</p>
                        @error('due_at')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($lockRequestSubject)
                    <input type="hidden" name="request_subject" value="{{ $defaultRequestSubject }}">
                    <p class="text-xs text-gray-600 rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-3 py-2">
                        Requesting for the person you are reviewing
                        @if ($panelPerson === 'member')
                            (group member).
                        @elseif ($panelPerson === 'guarantor')
                            (guarantor).
                        @else
                            (leader / borrower).
                        @endif
                        Switch Leader / Member / Guarantor above to request for someone else.
                    </p>
                @elseif ($showSubjectPicker)
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Request for <span class="text-red-500">*</span></label>
                        <select name="request_subject" required class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5 focus:border-brand focus:ring-brand/15">
                            @if ($groupMembersForRequest->isNotEmpty())
                                @foreach ($groupMembersForRequest as $member)
                                    @php
                                        $isLeader = ($member['role'] ?? '') === 'leader';
                                        $memberValue = $isLeader ? 'borrower' : ('member:'.($member['id'] ?? ''));
                                        $memberLabel = ($isLeader ? 'Leader · ' : 'Member · ').($member['name'] ?? 'Member');
                                        if (! empty($member['customer_number'])) {
                                            $memberLabel .= ' · '.$member['customer_number'];
                                        }
                                    @endphp
                                    <option value="{{ $memberValue }}" @selected($defaultRequestSubject === $memberValue)>
                                        {{ $memberLabel }}
                                    </option>
                                @endforeach
                            @else
                                <option value="borrower" @selected($defaultRequestSubject === 'borrower')>Borrower</option>
                                @foreach ($guarantorsForRequest as $g)
                                    @php
                                        $gValue = 'guarantor:'.(int) ($g['customer_id'] ?? 0);
                                        $gLabel = 'Guarantor · '.($g['name'] ?? 'Guarantor');
                                    @endphp
                                    @if ((int) ($g['customer_id'] ?? 0) > 0)
                                        <option value="{{ $gValue }}" @selected($defaultRequestSubject === $gValue)>
                                            {{ $gLabel }}
                                        </option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                        <p class="mt-1 text-[11px] text-gray-500">Income / profile requests clear and replace that person’s existing profile file.</p>
                        @error('request_subject')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                @if ($isAssetProduct)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Asset / lending</p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach ($assetPresets as $preset)
                                <label class="flex items-start gap-2 text-sm text-gray-700 bg-brand-muted/50 rounded-xl px-3 py-2 ring-1 ring-brand/10">
                                    <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                    <span>{{ $preset }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Collateral</p>
                        <p class="text-xs text-gray-500 mb-2">Prefer the Collateral tab for collateral-only requests. Presets here also deep-link to My Collaterals.</p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach ($collateralPresets as $preset)
                                <label class="flex items-start gap-2 text-sm text-gray-700 bg-emerald-50/80 rounded-xl px-3 py-2 ring-1 ring-brand/10">
                                    <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                    <span>{{ $preset }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Identity / photos <span class="font-normal normal-case text-gray-400">(profile — not a loan upload)</span></p>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach ($identityPresets as $preset)
                            <label class="flex items-start gap-2 text-sm text-gray-700 bg-sky-50 rounded-xl px-3 py-2 ring-1 ring-sky-100">
                                <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                <span>{{ $preset }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Income &amp; other <span class="font-normal normal-case text-gray-400">(shown under Received when uploaded)</span></p>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach ($generalPresets as $preset)
                            <label class="flex items-start gap-2 text-sm text-gray-700 bg-gray-50 rounded-xl px-3 py-2 ring-1 ring-gray-100">
                                <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                <span>{{ $preset }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Custom document label</label>
                    <input type="text" name="label" maxlength="120" placeholder="e.g. Ownership certificate"
                           class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Reason (shown to member)</label>
                    <textarea name="instructions" rows="2" maxlength="2000" placeholder="e.g. Image not clear — please re-upload a sharper photo"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2"></textarea>
                </div>

                <button type="submit"
                        data-loading-label="Sending request…"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                    Send request
                </button>
            </form>
        </div>
    </section>
@elseif ($documentRequests->isEmpty())
    <p class="text-sm text-gray-500">No application-specific document requests on this file.</p>
@endif
