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
        {{-- Segmented control: action vs receipt --}}
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

        {{-- REQUESTED: do this now --}}
        <div x-show="tab === 'requested'" x-cloak x-transition.opacity.duration.180ms role="tabpanel" class="space-y-3">
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
                            $profileGuided = $customer
                                ? $docSvc->isProfileGuidedForCustomer($customer, $docReq)
                                : $docSvc->isProfileGuidedRequest($docReq);
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
                            <div class="border-b border-gray-100 px-4 py-4 sm:px-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-bold text-gray-900">{{ $docSvc->localizedLabel((string) $docReq->label) }}</h3>
                                            <span @class([
                                                'rounded-full px-2.5 py-0.5 text-[11px] font-bold',
                                                'bg-red-100 text-red-800' => $isRejected,
                                                'bg-amber-100 text-amber-900' => ! $isRejected,
                                            ])>
                                                {{ $isRejected
                                                    ? __('borrower.application.request_status_rejected')
                                                    : __('borrower.loan_profile.documents_status_action') }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs font-semibold text-brand">
                                            {{ $docSvc->localizedSubjectRoleLabel($docReq) }}
                                        </p>
                                    </div>
                                </div>

                                @php
                                    $reqInstructions = $docSvc->localizedInstructions((string) $docReq->label, $docReq->instructions);
                                @endphp
                                @if ($reqInstructions)
                                    <p class="mt-2 text-xs text-gray-600">{{ $reqInstructions }}</p>
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

                            @if ($docReq->needsBorrowerAction())
                                <div class="bg-gray-50/80 px-4 py-4 sm:px-5">
                                    @if ((string) $docReq->label === 'Add collateral asset')
                                        <div class="space-y-3">
                                            @include('site.borrower.loan-profile._collateral_request_picker', [
                                                'assets' => $savedCollateral,
                                                'availabilities' => $collateralAvailabilities,
                                                'application' => $application,
                                            ])
                                        </div>
                                    @elseif ($profileGuided)
                                        <a href="{{ $profileUrl }}"
                                           class="inline-flex w-full items-center justify-center rounded-xl bg-brand-gold px-4 py-3 text-sm font-bold text-brand shadow-sm hover:brightness-95 sm:w-auto">
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
                                            <x-site.multi-page-document-upload
                                                name="files"
                                                :input-host-id="'doc-req-pages-'.$docReq->id"
                                                :max-pages="12"
                                            />
                                            @if ($docReq->type === 'clarification')
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold text-gray-600">{{ __('borrower.document_upload.your_response') }}</label>
                                                    <textarea name="response" rows="3" class="w-full rounded-xl border-gray-200 text-sm" placeholder="{{ __('borrower.document_upload.response_placeholder') }}"></textarea>
                                                </div>
                                            @endif
                                            <button type="submit"
                                                    class="w-full rounded-xl bg-brand-gold px-4 py-3 text-sm font-bold text-brand shadow-sm hover:brightness-95">
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

        {{-- SUBMITTED: quiet receipt --}}
        <div x-show="tab === 'submitted'" x-cloak x-transition.opacity.duration.180ms role="tabpanel">
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
    </div>
@endif
