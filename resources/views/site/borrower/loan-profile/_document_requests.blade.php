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
        ->concat($documentGroups['completed'] ?? [])
        ->unique('id')
        ->values();
    $openDocCount = collect($documentGroups['pending'] ?? [])
        ->concat($documentGroups['rejected'] ?? [])
        ->count();
    $customer = $profile['customer'] ?? $application->customer ?? auth()->user()?->customer;
    $docSvc = app(\App\Services\ApplicationDocumentRequestService::class);
    $focusRequestId = (int) request('doc');
    $focusedOnly = false;
    $otherOpenCount = 0;
    if ($focusRequestId > 0) {
        $focused = $actionDocs->first(fn ($r) => (int) $r->id === $focusRequestId);
        if ($focused) {
            $otherOpenCount = $actionDocs->count() - 1;
            $actionDocs = collect([$focused]);
            $focusedOnly = true;
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

@if ($actionDocs->isNotEmpty())
    @php
        $earliestDue = $actionDocs
            ->filter(fn ($r) => $r->due_at && in_array($r->status, ['pending', 'rejected'], true))
            ->sortBy('due_at')
            ->first();
        $headerDueAt = $earliestDue?->due_at;
        $headerDaysLeft = $headerDueAt ? (int) now()->startOfDay()->diffInDays($headerDueAt->copy()->startOfDay(), false) : null;
        $headerDueDate = $headerDueAt?->timezone(config('app.timezone'))->format('d M Y');
        $headerDueExpired = $headerDaysLeft !== null && $headerDaysLeft < 0;
    @endphp

    <div id="documents" class="mb-6">
        @unless ($focusedOnly)
        <div class="mb-3 rounded-2xl bg-gradient-to-br from-brand-muted/40 via-white to-white px-4 py-3.5 ring-1 ring-brand/12 sm:px-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">
                        {{ __('borrower.loan_profile.documents_action_eyebrow') }}
                    </p>
                    <h2 class="mt-0.5 text-base font-bold text-gray-900">
                        {{ __('borrower.loan_profile.documents_collapsed') }}
                    </h2>
                    @if ($openDocCount > 0)
                        <p class="mt-0.5 text-xs text-gray-600">
                            {{ __('borrower.loan_profile.documents_open_count', ['count' => $openDocCount]) }}
                        </p>
                    @endif
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
            <p class="mb-3 text-xs text-gray-500">
                <a href="{{ route('site.borrower.application', $application) }}#documents" class="font-semibold text-brand hover:underline">
                    {{ __('borrower.loan_profile.documents_view_all_requested') }}
                </a>
            </p>
        @endif

        <ul class="space-y-2">
            @foreach ($actionDocs as $docReq)
                @php
                    $profileGuided = $docSvc->isProfileGuidedRequest($docReq);
                    $profileUrl = $docSvc->borrowerActionUrl($docReq, $customer);
                    $isRejected = $docReq->status === 'rejected';
                    $isUploaded = $docReq->status === 'uploaded';
                    $isAccepted = in_array($docReq->status, ['satisfied', 'accepted', 'approved'], true);
                    $displayDocs = $customer
                        ? $docSvc->displayDocumentsForRequest($docReq, $customer)
                        : $docReq->uploads;
                    $thumbDocs = collect($displayDocs)->filter(fn ($u) => filled($u->file_path ?? null))->values();
                    $subjectName = $docReq->subjectCustomer?->full_name
                        ?? $docReq->groupMember?->customer?->full_name
                        ?? $application->customer?->full_name
                        ?? $customer?->full_name;
                    $canFulfill = $customer && $docSvc->customerCanFulfillRequest($customer, $docReq);
                    $identityKind = $docSvc->borrowerActionKind($docReq) === 'identity';
                    $actionKind = $docSvc->borrowerActionKind($docReq);
                    $statusLabel = match (true) {
                        $isRejected => __('borrower.loan_profile.documents_status_needs_correction'),
                        $isAccepted => __('borrower.loan_profile.documents_status_accepted'),
                        $isUploaded => __('borrower.loan_profile.documents_status_uploaded'),
                        default => __('borrower.loan_profile.documents_status_action'),
                    };
                    $statusTone = match (true) {
                        $isRejected => 'rose',
                        $isAccepted, $isUploaded => 'emerald',
                        default => 'amber',
                    };
                    $reqInstructions = $isRejected && filled($docReq->admin_notes)
                        ? $docReq->admin_notes
                        : $docSvc->localizedInstructions((string) $docReq->label, $docReq->instructions);
                    $uploadOnApplication = $customer && $docSvc->assistantUploadsOnApplication($customer, $docReq);
                    $goToProfile = $profileGuided && ! $uploadOnApplication;
                    $assistingProfile = $profileGuided
                        && $customer
                        && $docSvc->borrowerIsAssisting($customer, $docReq)
                        && ! $uploadOnApplication;
                    $addLabel = $isRejected
                        ? __('borrower.loan_profile.documents_resubmit')
                        : ($identityKind && $uploadOnApplication
                            ? __('borrower.document_upload.nida_start')
                            : __('borrower.document_upload.add'));
                    $needsAction = $docReq->needsBorrowerAction() && $canFulfill && ! $assistingProfile && ! $isAccepted;
                @endphp
                <li id="request-{{ $docReq->id }}" class="scroll-mt-24">
                    <x-site.request-card
                        :icon="$actionKind"
                        :title="$docSvc->localizedLabel((string) $docReq->label)"
                        :subtitle="$subjectName"
                        :meta="$reqInstructions"
                        :status="$statusLabel"
                        :status-tone="$statusTone"
                    >
                        @if ($needsAction)
                            <x-slot:action>
                                @if ($goToProfile)
                                    <x-site.request-add-button :href="$profileUrl" :label="$addLabel" />
                                @elseif ($identityKind)
                                    <x-site.request-add-button
                                        :label="$addLabel"
                                        x-bind:class="adding ? 'is-open' : ''"
                                        x-bind:aria-expanded="adding"
                                        @click="adding = ! adding; if (adding) $nextTick(() => $el.closest('li')?.querySelector('[data-kf-cam-start]')?.click())"
                                    />
                                @else
                                    <x-site.request-add-button
                                        :label="$addLabel"
                                        x-bind:class="adding ? 'is-open' : ''"
                                        x-bind:aria-expanded="adding"
                                        @click="adding = ! adding"
                                    />
                                @endif
                            </x-slot:action>
                        @endif
                    </x-site.request-card>

                    @if (($isRejected || $isUploaded || $isAccepted) && $thumbDocs->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2 px-1">
                            @foreach ($thumbDocs as $upload)
                                <x-site.document-thumb :url="asset('storage/'.$upload->file_path)" />
                            @endforeach
                        </div>
                    @endif

                    @if ($needsAction && ! $goToProfile)
                        <div x-show="adding" x-cloak class="mt-2 rounded-2xl bg-white p-3 ring-1 ring-brand/10">
                            @if ((string) $docReq->label === 'Add collateral asset')
                                @include('site.borrower.loan-profile._collateral_request_picker', [
                                    'assets' => $savedCollateral,
                                    'availabilities' => $collateralAvailabilities,
                                    'application' => $application,
                                ])
                            @else
                                <form method="POST"
                                      action="{{ route('site.borrower.application.document-requests.store', [$application, $docReq]) }}"
                                      enctype="multipart/form-data"
                                      class="space-y-3"
                                      data-inline-document-progress data-saving-message="{{ __('borrower.profile.uploading_documents') }}"
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
                                            :compact="true"
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
                                        <div x-data="{
                                            hostId: @js('doc-req-pages-'.$docReq->id),
                                            openCapture(source) {
                                                this.$nextTick(() => {
                                                    if (source === 'camera') {
                                                        this.$dispatch('document-open-camera', { hostId: this.hostId });
                                                    } else {
                                                        this.$dispatch('document-open-upload', { hostId: this.hostId });
                                                    }
                                                });
                                            },
                                        }"
                                             @document-source.window="
                                                if ($event.detail?.hostId && $event.detail.hostId !== hostId) return;
                                                openCapture($event.detail?.source);
                                             "
                                             class="space-y-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="text-sm font-semibold text-gray-800">{{ $addLabel }}</p>
                                                <x-site.document-source-picker :host-id="'doc-req-pages-'.$docReq->id" />
                                            </div>
                                            <x-site.multi-page-document-upload
                                                name="files"
                                                :input-host-id="'doc-req-pages-'.$docReq->id"
                                                :max-pages="12"
                                                :camera-first="true"
                                                :source-driven="true"
                                            />
                                            <p class="text-xs text-gray-500">{{ __('borrower.document_upload.guide_document_compact') }}</p>
                                        </div>
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
                    @elseif ($assistingProfile)
                        <p class="mt-2 px-1 text-xs text-gray-600">
                            {{ __('borrower.loan_profile.ask_subject_profile', [
                                'name' => $docSvc->localizedSubjectRoleLabel($docReq),
                            ]) }}
                        </p>
                    @elseif ($docReq->needsBorrowerAction() && ! $canFulfill)
                        <p class="mt-2 px-1 text-xs font-semibold text-amber-950">{{ $docSvc->waitingOnLabel($docReq) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
