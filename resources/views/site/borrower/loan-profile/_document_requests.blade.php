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
    @php
        $earliestDue = $actionDocs
            ->filter(fn ($r) => $r->due_at)
            ->sortBy('due_at')
            ->first();
        $headerDueAt = $earliestDue?->due_at;
        $headerDaysLeft = $headerDueAt ? (int) now()->startOfDay()->diffInDays($headerDueAt->copy()->startOfDay(), false) : null;
        $headerDueDate = $headerDueAt?->timezone(config('app.timezone'))->format('d M Y');
        $headerDueExpired = $headerDaysLeft !== null && $headerDaysLeft < 0;
    @endphp
    <div id="documents" class="mb-6 space-y-3">
        @if ($actionDocs->isNotEmpty())
            <div class="glass-card overflow-hidden ring-1 ring-amber-200/80">
                <div class="px-5 py-4 border-b border-amber-100 bg-amber-50/80">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-semibold text-amber-950">{{ __('borrower.loan_profile.documents_collapsed') }}</h2>
                            <p class="text-xs text-amber-900/80 mt-0.5">{{ __('borrower.loan_profile.documents_open_count', ['count' => $openDocCount]) }}</p>
                        </div>
                        @if ($headerDueAt)
                            <x-site.deadline-badge
                                :days-left="$headerDaysLeft"
                                :date="$headerDueDate"
                                :purpose="__('borrower.loan_profile.document_deadline_purpose')"
                                :label="$headerDueExpired ? __('borrower.loan_profile.document_deadline_expired') : null"
                                :urgent="$headerDaysLeft !== null && $headerDaysLeft <= 2"
                                :expired="$headerDueExpired"
                                class="shrink-0"
                            />
                        @endif
                    </div>
                </div>
                <ul class="divide-y divide-amber-100">
                    @foreach ($actionDocs as $docReq)
                        @php
                            $docSvc = app(\App\Services\ApplicationDocumentRequestService::class);
                            $profileGuided = $docSvc->isProfileGuidedRequest($docReq);
                            $profileUrl = $docSvc->borrowerActionUrl($docReq);
                            $reqBadge = $docReq->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700';
                            $reqLabel = $docReq->status === 'rejected'
                                ? __('borrower.application.request_status_rejected')
                                : __('borrower.application.request_status_pending');
                        @endphp
                        <li id="request-{{ $docReq->id }}" class="p-5 bg-white scroll-mt-24">
                            <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                                    @if ($docReq->instructions)
                                        <p class="text-sm text-gray-600 mt-1.5">{{ $docReq->instructions }}</p>
                                    @endif
                                    @if ($docReq->admin_notes && $docReq->status === 'rejected')
                                        <p class="text-xs text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2 mt-2">{{ $docReq->admin_notes }}</p>
                                    @endif
                                </div>
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $reqBadge }}">{{ $reqLabel }}</span>
                            </div>

                            @if ($docReq->uploads->isNotEmpty())
                                <div class="flex flex-wrap gap-2 mt-3 mb-3">
                                    @foreach ($docReq->uploads as $upload)
                                        <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                                    @endforeach
                                </div>
                            @endif

                            @if ($docReq->needsBorrowerAction())
                                <div class="mt-4">
                                    @if ($profileGuided)
                                        <a href="{{ $profileUrl }}"
                                           class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                                            {{ __('borrower.loan_profile.document_go_to_profile') }}
                                        </a>
                                    @else
                                        <form method="POST"
                                              action="{{ route('site.borrower.application.document-requests.store', [$application, $docReq]) }}"
                                              enctype="multipart/form-data"
                                              class="space-y-4">
                                            @csrf
                                            <x-site.multi-page-document-upload
                                                name="files"
                                                :input-host-id="'doc-req-pages-'.$docReq->id"
                                                :max-pages="12"
                                            />
                                            @if ($docReq->type === 'clarification')
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.document_upload.your_response') }}</label>
                                                    <textarea name="response" rows="3" class="w-full rounded-xl border-gray-200 text-sm" placeholder="{{ __('borrower.document_upload.response_placeholder') }}"></textarea>
                                                </div>
                                            @endif
                                            <button type="submit"
                                                    class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-3 rounded-xl text-sm">
                                                {{ __('borrower.document_upload.submit') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($otherDocs->isNotEmpty())
            <div class="glass-card overflow-hidden ring-1 ring-brand/10">
                <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.documents_submitted_eyebrow') }}</p>
                    <h2 class="font-semibold text-gray-900 mt-0.5">{{ __('borrower.loan_profile.documents_submitted_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.loan_profile.documents_submitted_hint') }}</p>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($otherDocs as $docReq)
                        <li class="px-5 py-4 flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $docReq->status === 'satisfied' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-800' }}">
                                        {{ $docReq->status === 'satisfied' ? __('borrower.application.request_status_completed') : __('borrower.application.request_status_uploaded') }}
                                    </span>
                                </div>
                                @if ($docReq->uploads->isNotEmpty())
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        @foreach ($docReq->uploads as $upload)
                                            <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
