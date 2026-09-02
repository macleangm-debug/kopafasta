@php
    $documentGroups = $profile['document_request_groups'] ?? [
        'pending' => collect(),
        'uploaded' => collect(),
        'completed' => collect(),
        'rejected' => collect(),
    ];
    $actionDocs = collect($documentGroups['pending'] ?? [])
        ->concat($documentGroups['rejected'] ?? [])
        ->concat($documentGroups['uploaded'] ?? [])
        ->values();
    $submittedDocs = collect();
    $openDocCount = $actionDocs->count();
    $submittedCount = $submittedDocs->count();
    $customer = $profile['customer'] ?? $application->customer ?? auth()->user()?->customer;
    $docSvc = app(\App\Services\ApplicationDocumentRequestService::class);
    $defaultTab = $openDocCount > 0 ? 'requested' : 'submitted';
    $focusRequestId = (int) request('doc');
    $focusedOnly = false;
    $otherOpenCount = 0;
    if ($focusRequestId > 0) {
        $focused = $actionDocs->first(fn ($r) => (int) $r->id === $focusRequestId);
        if ($focused) {
            $otherOpenCount = $actionDocs->count() - 1;
            $actionDocs = collect([$focused]);
            $openDocCount = 1;
            $focusedOnly = true;
            $defaultTab = 'requested';
        }
    }
    $savedCollateral = collect();
    $collateralAvailabilities = collect();
    if ($customer) {
        $savedCollateral = $customer->assets()->where('is_active', true)->latest()->get();
        $assetService = app(\App\Services\CustomerAssetService::class);
        $collateralAvailabilities = $savedCollateral->mapWithKeys(
            fn ($asset) => [$asset->id => $assetService->availabilityForApplication($asset, $application)]
        );
    }
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
         x-data="{ tab: @js($defaultTab) }">
        {{-- History tab stays available when submitted items exist; outstanding workspace has no extra chrome. --}}
        @unless ($focusedOnly)
        @if ($submittedCount > 0)
        <div class="mb-4 rounded-2xl bg-white p-1.5 shadow-sm ring-1 ring-brand/10"
             role="tablist"
             aria-label="{{ __('borrower.loan_profile.documents_tabs_nav') }}">
            <div class="grid grid-cols-2 gap-1">
                <button type="button"
                        role="tab"
                        @click="tab = 'requested'"
                        :aria-selected="tab === 'requested'"
                        :class="tab === 'requested'
                            ? 'bg-brand text-white shadow-sm'
                            : 'bg-transparent text-gray-600 hover:bg-brand-muted/40 hover:text-brand'"
                        class="relative flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold transition">
                    <span>{{ __('borrower.loan_profile.documents_tab_requested') }}</span>
                    @if ($openDocCount > 0)
                        <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold tabular-nums"
                              :class="tab === 'requested' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-900'">
                            {{ $openDocCount }}
                        </span>
                    @endif
                </button>
                <button type="button"
                        role="tab"
                        @click="tab = 'submitted'"
                        :aria-selected="tab === 'submitted'"
                        :class="tab === 'submitted'
                            ? 'bg-brand text-white shadow-sm'
                            : 'bg-transparent text-gray-600 hover:bg-brand-muted/40 hover:text-brand'"
                        class="relative flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold transition">
                    <span>{{ __('borrower.loan_profile.documents_tab_submitted') }}</span>
                    @if ($submittedCount > 0)
                        <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-bold tabular-nums"
                              :class="tab === 'submitted' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800'">
                            {{ $submittedCount }}
                        </span>
                    @endif
                </button>
            </div>
        </div>
        @endif
        @endunless

        {{-- REQUESTED: do this now --}}
        <div x-show="tab === 'requested'" x-cloak role="tabpanel" class="space-y-3">
            @unless ($focusedOnly)
            <div class="rounded-2xl bg-gradient-to-br from-amber-50 via-white to-white px-4 py-3.5 ring-1 ring-amber-200/80 sm:px-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-amber-800">
                            {{ __('borrower.loan_profile.documents_action_eyebrow') }}
                        </p>
                        <h2 class="mt-0.5 text-base font-bold text-amber-950">
                            {{ __('borrower.loan_profile.documents_collapsed') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-amber-900/75">
                            @if ($openDocCount > 0)
                                {{ __('borrower.loan_profile.documents_open_count', ['count' => $openDocCount]) }}
                            @else
                                {{ __('borrower.loan_profile.documents_requested_empty') }}
                            @endif
                        </p>
                    </div>
                    @if ($headerDueAt && $openDocCount > 0)
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
            @endunless

            @if ($focusedOnly && $otherOpenCount > 0)
                <p class="text-xs text-gray-500">
                    <a href="{{ route('site.borrower.application', $application) }}#documents" class="font-semibold text-brand hover:underline">
                        {{ __('borrower.loan_profile.documents_view_all_requested') }}
                    </a>
                </p>
            @endif

            @if ($actionDocs->isEmpty())
                <div class="rounded-2xl bg-white px-5 py-8 text-center ring-1 ring-brand/10">
                    <p class="text-sm font-semibold text-gray-900">{{ __('borrower.loan_profile.documents_requested_empty') }}</p>
                    @if ($submittedCount > 0)
                        <button type="button"
                                @click="tab = 'submitted'"
                                class="mt-3 text-sm font-semibold text-brand hover:underline">
                            {{ __('borrower.loan_profile.documents_view_submitted') }}
                        </button>
                    @endif
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($actionDocs as $docReq)
                        @php
                            $profileGuided = $docSvc->isProfileGuidedRequest($docReq);
                            $profileUrl = $docSvc->borrowerActionUrl($docReq, $customer);
                            $isRejected = $docReq->status === 'rejected';
                            $displayDocs = $customer
                                ? $docSvc->displayDocumentsForRequest($docReq, $customer)
                                : $docReq->uploads;
                            $thumbDocs = collect($displayDocs)->filter(fn ($u) => filled($u->file_path ?? null))->values();
                        @endphp
                        <li id="request-{{ $docReq->id }}"
                            @class([
                                'scroll-mt-24 overflow-hidden rounded-2xl bg-white shadow-sm ring-1',
                                'ring-red-200' => $isRejected,
                                'ring-brand/10' => ! $isRejected,
                            ])>
                            <div class="px-4 py-4 sm:px-5">
                                @php
                                    $subjectName = $docReq->subjectCustomer?->full_name
                                        ?? $docReq->groupMember?->customer?->full_name
                                        ?? $application->customer?->full_name
                                        ?? $customer?->full_name;
                                    $canFulfill = $customer && $docSvc->customerCanFulfillRequest($customer, $docReq);
                                @endphp
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h3 class="text-base font-bold text-gray-900 leading-snug">{{ $docSvc->localizedLabel((string) $docReq->label) }}</h3>
                                    <span @class([
                                        'rounded-full px-2.5 py-0.5 text-[11px] font-bold shrink-0',
                                        'bg-red-100 text-red-800' => $isRejected,
                                        'bg-emerald-100 text-emerald-800' => $docReq->status === 'uploaded',
                                        'bg-amber-100 text-amber-900' => ! $isRejected && $docReq->status !== 'uploaded',
                                    ])>
                                        {{ $isRejected
                                            ? __('borrower.application.request_status_rejected')
                                            : ($docReq->status === 'uploaded'
                                                ? __('borrower.document_upload.submitted_short')
                                                : __('borrower.loan_profile.documents_status_action')) }}
                                    </span>
                                </div>
                                @if ($subjectName)
                                    <p class="mt-2 text-sm font-extrabold text-gray-900 tracking-tight">{{ $subjectName }}</p>
                                @endif
                                @if (! $canFulfill && $docReq->needsBorrowerAction())
                                    <p class="mt-1.5 text-xs font-semibold text-amber-950">
                                        {{ $docSvc->waitingOnLabel($docReq) }}
                                    </p>
                                @endif
                                @if ($reqInstructions = $docSvc->localizedInstructions((string) $docReq->label, $docReq->instructions))
                                    <p class="mt-2 text-sm text-gray-600 leading-snug">{{ $reqInstructions }}</p>
                                @endif
                                @if ($docReq->due_at)
                                    <p class="mt-1.5 text-xs text-gray-500">
                                        {{ __('borrower.document_upload.due_line', ['date' => $docReq->due_at->timezone(config('app.timezone'))->format('d M Y')]) }}
                                    </p>
                                @endif

                                @if ($docReq->admin_notes && $isRejected)
                                    <p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs leading-relaxed text-red-800 ring-1 ring-red-200">
                                        {{ $docReq->admin_notes }}
                                    </p>
                                @endif

                                @if ($thumbDocs->isNotEmpty())
                                    <div class="mt-3">
                                        <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                                            {{ __('borrower.loan_profile.documents_previous_files') }}
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($thumbDocs as $upload)
                                                <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if ($docReq->status === 'uploaded')
                                <div class="bg-emerald-50/80 px-4 py-4 sm:px-5">
                                    <p class="text-base font-bold text-emerald-950">{{ __('borrower.document_upload.submitted') }}</p>
                                    <p class="mt-1 text-sm text-emerald-900">{{ __('borrower.document_upload.submitted_body') }}</p>
                                </div>
                            @elseif ($docReq->needsBorrowerAction())
                                <div class="border-t border-gray-100 px-4 py-4 sm:px-5">
                                    @php
                                        $assistingProfile = $profileGuided
                                            && $customer
                                            && $docSvc->borrowerIsAssisting($customer, $docReq)
                                            && ! $docSvc->assistantUploadsOnApplication($customer, $docReq);
                                        $identityKind = $docSvc->borrowerActionKind($docReq) === 'identity';
                                    @endphp
                                    @if ((string) $docReq->label === 'Add collateral asset')
                                        <div class="space-y-3">
                                            @include('site.borrower.loan-profile._collateral_request_picker', [
                                                'assets' => $savedCollateral,
                                                'availabilities' => $collateralAvailabilities,
                                                'application' => $application,
                                            ])
                                        </div>
                                    @elseif ($assistingProfile)
                                        <p class="text-sm text-gray-700">
                                            {{ __('borrower.loan_profile.ask_subject_profile', [
                                                'name' => $docSvc->localizedSubjectRoleLabel($docReq),
                                            ]) }}
                                        </p>
                                    @elseif ($profileGuided && ! $identityKind)
                                        <a href="{{ $profileUrl }}"
                                           class="inline-flex w-full items-center justify-center rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-light sm:w-auto">
                                            {{ __('borrower.loan_profile.document_go_to_profile') }}
                                        </a>
                                    @else
                                        <form method="POST"
                                              action="{{ route('site.borrower.application.document-requests.store', [$application, $docReq]) }}"
                                              enctype="multipart/form-data"
                                              class="space-y-4"
                                              data-saving-message="{{ __('borrower.profile.uploading_documents') }}"
                                              @submit.prevent="window.confirmForm($el, {
                                                  title: @js(__('borrower.document_upload.submit_confirm_title')),
                                                  message: @js(__('borrower.document_upload.submit_confirm_body')),
                                                  confirmLabel: @js(__('borrower.document_upload.submit')),
                                              })">
                                            @csrf
                                            @if ($identityKind)
                                                <x-site.nida-card-camera
                                                    front-name="front"
                                                    back-name="back"
                                                    :front-host-id="'doc-req-front-'.$docReq->id"
                                                    :back-host-id="'doc-req-back-'.$docReq->id"
                                                    :db-name="'kf-nida-doc-'.$docReq->id"
                                                    :subject-name="$subjectName"
                                                >
                                                    <button type="submit"
                                                            x-show="requiredDone() >= requiredTotal()"
                                                            x-cloak
                                                            class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-light">
                                                        {{ __('borrower.document_upload.submit') }}
                                                    </button>
                                                </x-site.nida-card-camera>
                                                @error('front')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                                @error('back')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                            @else
                                                <x-site.multi-page-document-upload
                                                    name="files"
                                                    :input-host-id="'doc-req-pages-'.$docReq->id"
                                                    :max-pages="12"
                                                />
                                                <button type="submit"
                                                        class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-light">
                                                    {{ __('borrower.document_upload.submit') }}
                                                </button>
                                            @endif
                                            @if ($docReq->type === 'clarification')
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold text-gray-600">{{ __('borrower.document_upload.your_response') }}</label>
                                                    <textarea name="response" rows="3" class="w-full rounded-xl border-gray-200 text-sm" placeholder="{{ __('borrower.document_upload.response_placeholder') }}"></textarea>
                                                </div>
                                            @endif
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @unless ($focusedOnly)
        {{-- SUBMITTED: quiet receipt --}}
        <div x-show="tab === 'submitted'" x-cloak role="tabpanel">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-brand/10">
                <div class="border-b border-gray-100 bg-gradient-to-r from-brand-muted/35 to-white px-4 py-4 sm:px-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">
                        {{ __('borrower.loan_profile.documents_submitted_eyebrow') }}
                    </p>
                    <h2 class="mt-0.5 text-base font-bold text-gray-900">
                        {{ __('borrower.loan_profile.documents_submitted_title') }}
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ __('borrower.loan_profile.documents_submitted_hint') }}
                    </p>
                </div>

                @if ($submittedDocs->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-gray-600">
                        {{ __('borrower.loan_profile.documents_submitted_empty') }}
                    </p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($submittedDocs as $docReq)
                            @php
                                $displayDocs = $customer
                                    ? $docSvc->displayDocumentsForRequest($docReq, $customer)
                                    : $docReq->uploads;
                                $thumbDocs = collect($displayDocs)->filter(fn ($u) => filled($u->file_path ?? null))->values();
                                $isAccepted = $docReq->status === 'satisfied';
                                $statusLabel = $isAccepted
                                    ? __('borrower.loan_profile.documents_status_accepted')
                                    : __('borrower.loan_profile.documents_status_received');
                            @endphp
                            <li class="px-4 py-3.5 sm:px-5">
                                <div class="flex items-start gap-3">
                                    <span @class([
                                        'mt-0.5 grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold',
                                        'bg-emerald-100 text-emerald-700' => $isAccepted,
                                        'bg-sky-100 text-sky-700' => ! $isAccepted,
                                    ]) aria-hidden="true">✓</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <p class="font-semibold text-gray-900">{{ $docSvc->localizedLabel((string) $docReq->label) }}</p>
                                            <span @class([
                                                'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                                'bg-emerald-50 text-emerald-800' => $isAccepted,
                                                'bg-sky-50 text-sky-800' => ! $isAccepted,
                                            ])>
                                                {{ $statusLabel }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs font-semibold text-brand">{{ $docSvc->localizedSubjectRoleLabel($docReq) }}</p>
                                        @if ($thumbDocs->isNotEmpty())
                                            <div class="mt-2.5 flex flex-wrap gap-2">
                                                @foreach ($thumbDocs as $upload)
                                                    <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        @endunless
    </div>
@endif
