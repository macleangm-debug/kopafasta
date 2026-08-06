@php
    $documentGroups = $profile['document_request_groups'] ?? [
        'pending' => collect(),
        'uploaded' => collect(),
        'completed' => collect(),
        'rejected' => collect(),
    ];
    $actionDocs = collect($documentGroups['pending'] ?? [])->concat($documentGroups['rejected'] ?? [])->values();
    $submittedDocs = collect($documentGroups['uploaded'] ?? [])->concat($documentGroups['completed'] ?? [])->values();
    $openDocCount = $actionDocs->count();
    $submittedCount = $submittedDocs->count();
    $customer = $profile['customer'] ?? $application->customer ?? auth()->user()?->customer;
    $docSvc = app(\App\Services\ApplicationDocumentRequestService::class);
    $defaultTab = $openDocCount > 0 ? 'requested' : 'submitted';
@endphp

@if ($actionDocs->isNotEmpty() || $submittedDocs->isNotEmpty())
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

    <div id="documents"
         class="mb-6"
         x-data="{
             tab: @js($defaultTab),
             requestedOpen: true,
             submittedOpen: true,
             setTab(name) {
                 this.tab = name;
                 if (name === 'requested') this.requestedOpen = true;
                 if (name === 'submitted') this.submittedOpen = true;
             }
         }">
        {{-- Tab rail (same idea as apply review pages) --}}
        <nav class="mb-3 rounded-2xl bg-white/95 ring-1 ring-brand/10 px-2 py-3 shadow-sm"
             aria-label="{{ __('borrower.loan_profile.documents_tabs_nav') }}">
            <ol class="grid grid-cols-2 gap-2 items-stretch">
                <li class="min-w-0">
                    <button type="button"
                            @click="setTab('requested')"
                            class="group flex flex-col items-center gap-1.5 w-full focus:outline-none">
                        <span class="size-8 rounded-full grid place-items-center text-xs font-bold transition ring-2"
                              :class="tab === 'requested'
                                  ? 'bg-brand text-white ring-brand shadow-sm'
                                  : 'bg-white text-gray-400 ring-gray-200 group-hover:ring-brand/40 group-hover:text-brand'">
                            <span x-text="'{{ $openDocCount }}'"></span>
                        </span>
                        <span class="text-[10px] uppercase tracking-widest font-semibold transition text-center"
                              :class="tab === 'requested' ? 'text-brand' : 'text-gray-400'">
                            {{ __('borrower.loan_profile.documents_tab_requested') }}
                            @if ($openDocCount > 0)
                                <span class="tabular-nums">({{ $openDocCount }})</span>
                            @endif
                        </span>
                    </button>
                </li>
                <li class="min-w-0">
                    <button type="button"
                            @click="setTab('submitted')"
                            class="group flex flex-col items-center gap-1.5 w-full focus:outline-none">
                        <span class="size-8 rounded-full grid place-items-center text-xs font-bold transition ring-2"
                              :class="tab === 'submitted'
                                  ? 'bg-brand text-white ring-brand shadow-sm'
                                  : ({{ $submittedCount }} > 0
                                      ? 'bg-emerald-500 text-white ring-emerald-500'
                                      : 'bg-white text-gray-400 ring-gray-200 group-hover:ring-brand/40 group-hover:text-brand')">
                            @if ($submittedCount > 0 && $openDocCount === 0)
                                ✓
                            @else
                                {{ $submittedCount }}
                            @endif
                        </span>
                        <span class="text-[10px] uppercase tracking-widest font-semibold transition text-center"
                              :class="tab === 'submitted' ? 'text-brand' : 'text-gray-400'">
                            {{ __('borrower.loan_profile.documents_tab_submitted') }}
                            @if ($submittedCount > 0)
                                <span class="tabular-nums">({{ $submittedCount }})</span>
                            @endif
                        </span>
                    </button>
                </li>
            </ol>
        </nav>

        {{-- Requested (collapsible) --}}
        <div x-show="tab === 'requested'" x-cloak class="glass-card overflow-hidden ring-1 ring-amber-200/80">
            <button type="button"
                    @click="requestedOpen = !requestedOpen"
                    class="w-full text-left px-4 sm:px-5 py-4 border-b border-amber-100 bg-amber-50/80 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-semibold text-amber-950">{{ __('borrower.loan_profile.documents_collapsed') }}</h2>
                    <p class="text-xs text-amber-900/80 mt-0.5">
                        @if ($openDocCount > 0)
                            {{ __('borrower.loan_profile.documents_open_count', ['count' => $openDocCount]) }}
                        @else
                            {{ __('borrower.loan_profile.documents_requested_empty') }}
                        @endif
                    </p>
                </div>
                <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 w-full sm:w-auto">
                    @if ($headerDueAt && $openDocCount > 0)
                        <x-site.deadline-badge
                            :days-left="$headerDaysLeft"
                            :date="$headerDueDate"
                            :purpose="__('borrower.loan_profile.document_deadline_purpose')"
                            :label="$headerDueExpired ? __('borrower.loan_profile.document_deadline_expired') : null"
                            :urgent="$headerDaysLeft !== null && $headerDaysLeft <= 2"
                            :expired="$headerDueExpired"
                            class="flex-1 sm:flex-none"
                        />
                    @endif
                    <svg class="size-5 text-amber-800 transition shrink-0" :class="requestedOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
                </div>
            </button>

            <div x-show="requestedOpen" x-collapse>
                @if ($actionDocs->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-600">{{ __('borrower.loan_profile.documents_requested_empty') }}</p>
                @else
                    <ul class="divide-y divide-amber-100">
                        @foreach ($actionDocs as $docReq)
                            @php
                                $profileGuided = $docSvc->isProfileGuidedRequest($docReq);
                                $profileUrl = $docSvc->borrowerActionUrl($docReq);
                                $reqBadge = $docReq->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700';
                                $reqLabel = $docReq->status === 'rejected'
                                    ? __('borrower.application.request_status_rejected')
                                    : __('borrower.application.request_status_pending');
                                $displayDocs = $customer
                                    ? $docSvc->displayDocumentsForRequest($docReq, $customer)
                                    : $docReq->uploads;
                            @endphp
                            <li id="request-{{ $docReq->id }}" class="p-5 bg-white scroll-mt-24">
                                <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                                            <span class="text-[10px] font-bold uppercase tracking-widest rounded-full px-2 py-0.5 {{ $profileGuided ? 'bg-brand-muted text-brand' : 'bg-gray-100 text-gray-600' }}">
                                                {{ $profileGuided ? __('borrower.loan_profile.document_source_profile') : __('borrower.loan_profile.document_source_loan') }}
                                            </span>
                                        </div>
                                        @if ($docReq->instructions)
                                            <p class="text-sm text-gray-600 mt-1.5">{{ $docReq->instructions }}</p>
                                        @endif
                                        @if ($docReq->admin_notes && $docReq->status === 'rejected')
                                            <p class="text-xs text-red-700 bg-red-50 ring-1 ring-red-200 rounded-lg px-3 py-2 mt-2">{{ $docReq->admin_notes }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $reqBadge }}">{{ $reqLabel }}</span>
                                </div>

                                @if ($displayDocs->isNotEmpty())
                                    <div class="flex flex-wrap gap-2 mt-3 mb-3">
                                        @foreach ($displayDocs as $upload)
                                            @if ($upload->file_path)
                                                <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                                            @endif
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
                @endif
            </div>
        </div>

        {{-- Submitted (collapsible) — profile-linked + loan-linked --}}
        <div x-show="tab === 'submitted'" x-cloak class="glass-card overflow-hidden ring-1 ring-brand/10">
            <button type="button"
                    @click="submittedOpen = !submittedOpen"
                    class="w-full text-left px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.documents_submitted_eyebrow') }}</p>
                    <h2 class="font-semibold text-gray-900 mt-0.5">{{ __('borrower.loan_profile.documents_submitted_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.loan_profile.documents_submitted_hint') }}</p>
                </div>
                <svg class="size-5 text-gray-500 transition shrink-0 mt-1" :class="submittedOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
            </button>

            <div x-show="submittedOpen" x-collapse>
                @if ($submittedDocs->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-600">{{ __('borrower.loan_profile.documents_submitted_empty') }}</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($submittedDocs as $docReq)
                            @php
                                $profileGuided = $docSvc->isProfileGuidedRequest($docReq);
                                $displayDocs = $customer
                                    ? $docSvc->displayDocumentsForRequest($docReq, $customer)
                                    : $docReq->uploads;
                                $statusBadge = $docReq->status === 'satisfied'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-sky-100 text-sky-800';
                                $statusLabel = $docReq->status === 'satisfied'
                                    ? __('borrower.application.request_status_completed')
                                    : __('borrower.application.request_status_uploaded');
                            @endphp
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                                            <span class="text-[10px] font-bold uppercase tracking-widest rounded-full px-2 py-0.5 {{ $profileGuided ? 'bg-brand-muted text-brand' : 'bg-gray-100 text-gray-600' }}">
                                                {{ $profileGuided ? __('borrower.loan_profile.document_source_profile') : __('borrower.loan_profile.document_source_loan') }}
                                            </span>
                                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusBadge }}">{{ $statusLabel }}</span>
                                        </div>
                                        @if ($docReq->instructions)
                                            <p class="text-sm text-gray-500 mt-1">{{ $docReq->instructions }}</p>
                                        @endif
                                        @if ($displayDocs->isNotEmpty())
                                            <div class="flex flex-wrap gap-2 mt-3">
                                                @foreach ($displayDocs as $upload)
                                                    @if ($upload->file_path)
                                                        <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-xs text-gray-500 mt-2">{{ __('borrower.loan_profile.document_files_on_profile') }}</p>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endif
