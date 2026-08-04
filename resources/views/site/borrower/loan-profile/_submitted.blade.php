@php
    $requirements = $profile['product_requirements'] ?? collect();
    $uploads = $profile['requirement_uploads'] ?? collect();
    $documentRequests = $profile['document_requests'] ?? collect();
    $guarantorInvitations = $profile['guarantor_invitations'] ?? collect();
    $offer = $profile['offer'] ?? null;
    $loan = $profile['loan'] ?? null;
    $nextDue = $profile['next_due'] ?? null;
    $requiredCount = $requirements->where('is_required', true)->count();
    $satisfiedCount = $requirements->where('is_required', true)
        ->filter(fn ($r) => ($uploads[$r->id] ?? collect())->contains(fn ($u) => in_array($u->status, ['verified', 'approved', 'pending_review', 'pending'], true)))
        ->count();
    $docProgress = $requiredCount > 0 ? round(($satisfiedCount / $requiredCount) * 100) : 100;
@endphp

@if ($loan && in_array((string) $loan->status, ['active', 'disbursed', 'arrears'], true))
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-6">
        <h2 class="font-semibold text-emerald-900 mb-2">{{ __('borrower.loan_profile.disbursed_title') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_profile.loan_number') }}</p>
                <p class="font-mono font-semibold text-emerald-900">{{ $loan->loan_number }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_profile.outstanding') }}</p>
                <p class="font-semibold text-emerald-900">{{ format_money($loan->outstanding_balance ?? $loan->principal_amount) }}</p>
            </div>
            @php $repayment = $profile['repayment_summary'] ?? null; @endphp
            @if (! empty($repayment['disbursed_at']))
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_progress.disbursed_on') }}</p>
                    <p class="font-semibold text-emerald-900">{{ optional($repayment['disbursed_at'])->format('d M Y') }}</p>
                </div>
            @endif
            @if (! empty($repayment['first_repayment_at']))
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_progress.first_repayment') }}</p>
                    <p class="font-semibold text-emerald-900">{{ optional($repayment['first_repayment_at'])->format('d M Y') }}</p>
                </div>
            @endif
            @if (! empty($repayment['frequency']))
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_progress.repayment_frequency') }}</p>
                    <p class="font-semibold text-emerald-900">{{ __('borrower.loan_progress.frequencies.'.$repayment['frequency']) }}</p>
                </div>
            @endif
            @if ($nextDue)
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loans_page.next_payment') }}</p>
                    <p class="font-semibold text-emerald-900">{{ optional($nextDue->due_date)->format('d M Y') }} · {{ format_money($nextDue->total_due) }}</p>
                </div>
            @endif
        </div>
        @if ($loan)
            <div class="mt-4">
                <a href="{{ route('site.borrower.loans.show', $loan->id) }}"
                   class="inline-flex items-center text-sm font-semibold text-emerald-800 hover:text-emerald-900">
                    {{ __('borrower.loan_profile.actions.view_active_loan') }} &rarr;
                </a>
            </div>
        @endif
    </div>
@endif

@if ($offer)
    @php
        $offerDeclined = ($application->offer_status ?? '') === 'declined' || $offer->isCancelled();
        $offerSigned = $offer->isSigned();
    @endphp
    <div @class([
        'mb-6 rounded-2xl p-4 flex items-center justify-between gap-3 flex-wrap border',
        'bg-amber-50 border-amber-200' => ! $offerDeclined,
        'bg-gray-50 border-gray-200' => $offerDeclined,
    ])>
        <div>
            <p @class([
                'text-sm font-semibold',
                'text-amber-900' => ! $offerDeclined,
                'text-gray-700' => $offerDeclined,
            ])>
                @if ($offerDeclined)
                    {{ __('borrower.applications_list.statuses.offer_declined') }}
                @elseif ($offerSigned)
                    {{ __('borrower.application.offer_signed') }}
                @else
                    {{ __('borrower.application.offer_ready') }}
                @endif
            </p>
            <p class="text-xs text-gray-600 mt-0.5">Reference: <span class="font-mono">{{ $offer->reference }}</span></p>
        </div>
        @unless ($offerDeclined)
            <a href="{{ route('site.borrower.application.agreement', $application) }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">
                {{ __('borrower.application.view_offer') }} →
            </a>
        @endunless
    </div>
@endif

@if ($showSchedule ?? true)
    @include('site.borrower.loan-profile._schedule_preview', ['profile' => $profile])
@endif

@php
    $documentGroups = $profile['document_request_groups'] ?? [
        'pending' => collect(),
        'uploaded' => collect(),
        'completed' => collect(),
        'rejected' => collect(),
    ];
    $actionDocs = collect($documentGroups['pending'] ?? [])->concat($documentGroups['rejected'] ?? [])->values();
    $otherDocs = collect($documentGroups['uploaded'] ?? [])->concat($documentGroups['completed'] ?? [])->values();
    $openDocCount = $actionDocs->count();
@endphp

@if ($actionDocs->isNotEmpty() || $otherDocs->isNotEmpty())
    <div id="documents" class="mb-6 space-y-3">
        @if ($actionDocs->isNotEmpty())
            <div class="glass-card overflow-hidden ring-1 ring-amber-200/80">
                <div class="px-5 py-4 border-b border-amber-100 bg-amber-50/80">
                    <h2 class="font-semibold text-amber-950">{{ __('borrower.loan_profile.documents_collapsed') }}</h2>
                    <p class="text-xs text-amber-900/80 mt-0.5">{{ __('borrower.loan_profile.documents_open_count', ['count' => $openDocCount]) }}</p>
                </div>
                <ul class="divide-y divide-amber-100">
                    @foreach ($actionDocs as $docReq)
                        @php
                            $reqBadge = $docReq->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700';
                            $reqLabel = $docReq->status === 'rejected'
                                ? __('borrower.application.request_status_pending')
                                : __('borrower.application.request_status_pending');
                        @endphp
                        <li id="request-{{ $docReq->id }}" class="p-5 bg-white">
                            <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                                    @if ($docReq->instructions)
                                        <p class="text-sm text-gray-600 mt-2">{{ $docReq->instructions }}</p>
                                    @endif
                                    @if ($docReq->admin_notes && $docReq->status === 'rejected')
                                        <p class="text-xs text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2 mt-2">{{ $docReq->admin_notes }}</p>
                                    @endif
                                </div>
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $reqBadge }}">{{ $reqLabel }}</span>
                            </div>

                            @if ($docReq->uploads->isNotEmpty())
                                <div class="flex flex-wrap gap-2 mb-3">
                                    @foreach ($docReq->uploads as $upload)
                                        <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                                    @endforeach
                                </div>
                            @endif

                            @if ($docReq->needsBorrowerAction())
                                @php
                                    $docSvc = app(\App\Services\ApplicationDocumentRequestService::class);
                                    $profileGuided = $docSvc->isProfileGuidedRequest($docReq);
                                    $profileUrl = $docSvc->borrowerActionUrl($docReq);
                                @endphp
                                @if ($profileGuided)
                                    <a href="{{ $profileUrl }}"
                                       class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                                        {{ __('borrower.notifications.profile_revision_cta') }}
                                    </a>
                                @else
                                    <x-site.document-upload
                                        :action="route('site.borrower.application.document-requests.store', [$application, $docReq])"
                                        :show-clarification="$docReq->type === 'clarification'"
                                        :multiple="true"
                                    />
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($otherDocs->isNotEmpty())
            <details class="glass-card overflow-hidden group">
                <summary class="cursor-pointer list-none px-5 py-4 flex items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.application.doc_group_completed') }} / {{ __('borrower.application.doc_group_uploaded') }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $otherDocs->count() }} {{ __('borrower.loan_profile.documents_collapsed') }}</p>
                    </div>
                    <svg class="size-4 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </summary>
                <ul class="divide-y divide-gray-100 border-t border-gray-100">
                    @foreach ($otherDocs as $docReq)
                        <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-gray-800">{{ $docReq->label }}</span>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $docReq->status === 'satisfied' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $docReq->status === 'satisfied' ? __('borrower.application.request_status_completed') : __('borrower.application.request_status_uploaded') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
@endif

{{-- Product document checklist: only while post-approval / draft gaps — hide after plain submit --}}
@if (($isPostApproval ?? false) && $requirements->isNotEmpty())
    <details class="glass-card overflow-hidden mb-6 group">
        <summary class="cursor-pointer list-none px-5 py-4 flex items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
            <div>
                <h2 class="font-semibold">{{ __('borrower.application.required_documents') }}</h2>
                <p class="text-xs text-gray-500">{{ __('borrower.application.required_progress', ['satisfied' => $satisfiedCount, 'total' => $requiredCount]) }}</p>
            </div>
            <svg class="size-4 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </summary>
        <ul class="divide-y divide-gray-100 border-t border-gray-100">
            @foreach ($requirements as $req)
                @php
                    $myUploads = $uploads[$req->id] ?? collect();
                    $latest = $myUploads->first();
                    $isApproved = $latest && in_array($latest->status, ['verified','approved']);
                    $isRejected = $latest && $latest->status === 'rejected';
                    $badge = match (true) {
                        $isApproved => 'bg-emerald-100 text-emerald-700',
                        $isRejected => 'bg-red-100 text-red-700',
                        $latest      => 'bg-amber-100 text-amber-700',
                        ! $req->is_required => 'bg-gray-100 text-gray-500',
                        default => 'bg-gray-100 text-gray-600',
                    };
                    $label = $latest
                        ? (__('borrower.application.upload_statuses.'.$latest->status) !== 'borrower.application.upload_statuses.'.$latest->status
                            ? __('borrower.application.upload_statuses.'.$latest->status)
                            : display_label($latest->status, 'record_status'))
                        : ($req->is_required ? __('borrower.application.status_required') : __('borrower.application.status_optional'));
                @endphp
                <li id="requirement-{{ $req->id }}" class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $req->name }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $label }}</span>
                    </div>
                    @if ($latest && $latest->file_path)
                        <div class="mb-3">
                            <x-site.document-thumb :url="asset('storage/'.$latest->file_path)" />
                        </div>
                    @endif
                    @if (! $isApproved)
                        <x-site.document-upload
                            :action="route('site.borrower.application.documents.store', $application->id)"
                            :multiple="false"
                        >
                            <input type="hidden" name="loan_product_requirement_id" value="{{ $req->id }}">
                        </x-site.document-upload>
                    @endif
                </li>
            @endforeach
        </ul>
    </details>
@endif
