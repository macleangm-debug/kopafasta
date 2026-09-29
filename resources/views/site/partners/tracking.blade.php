<x-site.layout :title="brand_title(__('site.partner_apply.track_title'))">
    @php
        $showSubmittedNotice = session()->pull('partner_submitted');
        $enrolledPartner = $enrolledPartner ?? null;
        $reviewPeriod = app(\App\Services\AffiliateSettingsService::class)->publicReviewPeriodLabel();
        $application = ($phone !== '' && $applications->isNotEmpty()) ? $applications->first() : null;
        $infoRequests = [];
        $resultPayload = null;

        if ($application) {
            $partner = $application->partner ?: $enrolledPartner;
            $infoRequests = app(\App\Services\PartnerApplicationDecisionService::class)->allRequests($application);
            $resultPayload = [
                'status' => (string) $application->status,
                'name' => $application->business_name ?: $application->full_name,
                'category' => app(\App\Services\PartnerEnrollmentService::class)
                    ->categoryLabel((string) ($application->partner_category ?: $application->type)),
                'phone' => $application->phone,
                'submitted' => optional($application->created_at)->format('d M Y'),
                'notes' => $application->admin_notes,
                'partner_code' => $partner?->vendor_number ?: $partner?->partner_number,
                'activated' => (bool) ($partner?->activated_at && $partner?->user_id),
                'activate_url' => $partner
                    ? app(\App\Services\PartnerActivationService::class)->publicActivateUrl($partner)
                    : route('site.partner.start'),
                'review_period' => $reviewPeriod,
                'application_id' => $application->id,
            ];
        } elseif ($phone !== '' && $enrolledPartner) {
            $resultPayload = [
                'status' => 'approved',
                'name' => $enrolledPartner->name,
                'category' => ucfirst(str_replace('_', ' ', (string) $enrolledPartner->category)),
                'phone' => $enrolledPartner->phone,
                'submitted' => optional($enrolledPartner->created_at)->format('d M Y'),
                'notes' => null,
                'partner_code' => $enrolledPartner->vendor_number ?: $enrolledPartner->partner_number,
                'activated' => (bool) ($enrolledPartner->activated_at && $enrolledPartner->user_id),
                'activate_url' => app(\App\Services\PartnerActivationService::class)->publicActivateUrl($enrolledPartner),
                'review_period' => $reviewPeriod,
                'application_id' => null,
            ];
        } elseif ($phone !== '') {
            $resultPayload = ['empty' => true];
        }

        $hasResult = $resultPayload && empty($resultPayload['empty']);
        $showLookup = ! $hasResult;
        $allSupplied = $application
            && $application->status === 'pending'
            && collect($infoRequests)->isNotEmpty()
            && collect($infoRequests)->every(fn ($r) => ($r['status'] ?? '') === 'submitted');
        $docGuide = __('borrower.document_upload.guide_document_compact');
    @endphp

    <div class="max-w-2xl mx-auto px-4 pt-8">
        <a href="{{ route('site.partners') }}" class="text-sm text-brand hover:underline inline-flex items-center gap-1 mb-4">
            ← {{ __('site.partners.title') }}
        </a>
        <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-6">
            <div class="absolute -right-16 -top-16 h-44 w-44 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-6 py-5 sm:py-6">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white mt-1">{{ __('site.partner_apply.track_title') }}</h1>
                <p class="mt-2 text-sm text-white/80 leading-relaxed">{{ __('site.partner_apply.track_subtitle') }}</p>
            </div>
        </section>
    </div>

    <div class="max-w-2xl mx-auto pb-10 px-4 space-y-5">
        @if ($showLookup)
            <form method="GET" action="{{ route('site.partners.apply.tracking') }}" class="glass-card p-6 space-y-4" data-no-draft>
                <x-site.phone-input
                    name="phone"
                    :label="__('site.partner_apply.track_phone_label')"
                    :value="$phone"
                    variant="rounded"
                    :required="true"
                    :help="__('site.partner_apply.track_phone_help')"
                />
                <button type="submit" class="w-full sm:w-auto bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">
                    {{ __('site.partner_apply.track_submit') }}
                </button>
            </form>
        @endif

        @if ($showSubmittedNotice && $hasResult)
            <div class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
                <p class="font-semibold">{{ __('site.partner_apply.success_modal_title') }}</p>
                <p class="mt-1">{{ __('site.partner_apply.success_modal_body') }}</p>
            </div>
        @endif

        @if (session('status'))
            <div class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ml-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($resultPayload)
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-brand/15 overflow-hidden">
                @if (! empty($resultPayload['empty']))
                    <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                        <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.partner_apply.track_application') }}</p>
                        <h2 class="mt-1 text-xl font-bold">{{ __('site.partner_apply.track_empty_title') }}</h2>
                    </div>
                    <div class="px-5 py-5 text-sm text-gray-700">
                        <p>{{ __('site.partner_apply.track_empty') }}</p>
                    </div>
                @else
                    @php
                        $status = $resultPayload['status'] ?? 'pending';
                        $isPending = in_array($status, ['pending', 'awaiting_fee'], true);
                        $hasOpenRequests = collect($infoRequests)->contains(fn ($r) => ($r['status'] ?? '') === 'requested');
                    @endphp
                    <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                        <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ $resultPayload['name'] ?? __('site.partner_apply.track_application') }}</p>
                        <h2 class="mt-1 text-xl font-bold">
                            @if ($status === 'needs_info' || $hasOpenRequests)
                                {{ __('site.partner_apply.track_needs_info_title') }}
                            @elseif ($allSupplied)
                                {{ __('site.partner_apply.track_info_supplied_title') }}
                            @elseif ($isPending)
                                {{ __('site.partner_apply.track_statuses.pending') }}
                            @else
                                {{ __('site.partner_apply.track_statuses.'.$status) }}
                            @endif
                        </h2>
                        <p class="text-sm text-white/80 mt-1">{{ $resultPayload['category'] ?? '' }}</p>
                        @if (! empty($resultPayload['submitted']))
                            <p class="text-xs text-white/70 mt-2">{{ __('site.partner_apply.track_submitted', ['date' => $resultPayload['submitted']]) }}</p>
                        @endif
                    </div>

                    <div class="px-5 py-5 space-y-4 text-sm text-gray-700">
                        @if ($status === 'approved')
                            <div class="rounded-2xl bg-brand-muted/50 ring-1 ring-brand/15 p-4 space-y-3">
                                <p class="font-semibold text-brand">{{ __('site.partner_apply.track_approved_title') }}</p>
                                @if ($resultPayload['partner_code'])
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-brand/70 font-semibold">{{ __('site.partner_apply.track_partner_code') }}</p>
                                        <p class="mt-1 text-2xl font-extrabold tracking-widest font-mono text-brand">{{ $resultPayload['partner_code'] }}</p>
                                        <p class="mt-2 text-xs text-brand/80">{{ __('site.partner_apply.track_partner_code_hint') }}</p>
                                    </div>
                                @endif
                                @if ($resultPayload['activated'])
                                    <a href="{{ route('site.login.partner') }}"
                                       class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                        {{ __('site.partner_apply.track_login_cta') }}
                                    </a>
                                @else
                                    <a href="{{ $resultPayload['activate_url'] ?? route('site.partner.start') }}"
                                       class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                        {{ __('site.partner_apply.track_activate_cta') }}
                                    </a>
                                @endif
                            </div>
                        @elseif ($status === 'rejected')
                            <div class="rounded-2xl bg-red-50 ring-1 ring-red-200 p-4 text-red-800">
                                <p class="font-semibold">{{ __('site.partner_apply.track_rejected_title') }}</p>
                                <p class="mt-1">{{ $resultPayload['notes'] ?: __('site.partner_apply.track_rejected_body') }}</p>
                            </div>
                        @elseif ($status === 'needs_info' || $hasOpenRequests || $allSupplied || ! empty($infoRequests))
                            @if ($status === 'needs_info' || $hasOpenRequests)
                                <p class="text-sm text-gray-700">{{ __('site.partner_apply.track_needs_info_body') }}</p>
                            @elseif ($allSupplied)
                                <p class="text-sm text-brand font-medium">{{ __('site.partner_apply.track_info_supplied_body') }}</p>
                            @endif

                            @if (! empty($infoRequests) && $application)
                                <ul class="space-y-4">
                                    @foreach ($infoRequests as $req)
                                        @php
                                            $reqId = (string) ($req['id'] ?? '');
                                            $reqStatus = (string) ($req['status'] ?? 'requested');
                                            $isDoc = ($req['kind'] ?? '') === 'document';
                                            $isOpen = $reqStatus === 'requested';
                                            $isReplace = ($req['mode'] ?? '') === 'replace';
                                            $reqType = (string) ($req['type'] ?? '');
                                            $hostId = 'track-req-'.md5($reqId);
                                            $statusBadge = $isOpen
                                                ? ($isReplace
                                                    ? __('site.partner_apply.track_request_update_required')
                                                    : __('site.partner_apply.track_request_required'))
                                                : null;
                                        @endphp
                                        <li class="rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
                                            @if (! $isOpen)
                                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $req['label'] ?? __('site.partner_apply.track_request_item') }}</p>
                                                    <span class="shrink-0 text-xs font-semibold text-emerald-700">✓ {{ __('site.partner_apply.track_request_submitted') }}</span>
                                                </div>
                                            @elseif (! $isDoc)
                                                <form method="POST"
                                                      action="{{ route('site.partners.apply.fulfill', $application) }}"
                                                      class="p-4 space-y-3"
                                                      data-no-draft
                                                      data-no-saving>
                                                    @csrf
                                                    <input type="hidden" name="phone" value="{{ $phone }}">
                                                    <input type="hidden" name="request_id" value="{{ $reqId }}">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold text-gray-900">{{ $req['label'] ?? __('site.partner_apply.track_request_item') }}</p>
                                                            @if (! empty($req['explanation']))
                                                                <p class="text-xs text-gray-500 mt-0.5">{{ $req['explanation'] }}</p>
                                                            @endif
                                                        </div>
                                                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-full px-2 py-0.5">{{ $statusBadge }}</span>
                                                    </div>
                                                    <textarea name="response_text" rows="3" required
                                                              class="w-full rounded-xl border-brand/20 bg-white ring-1 ring-brand/20 text-sm"
                                                              placeholder="{{ __('site.partner_apply.track_request_text_placeholder') }}">{{ old('response_text') }}</textarea>
                                                    <button type="submit"
                                                            class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                                        {{ __('site.partner_apply.track_request_submit') }}
                                                    </button>
                                                </form>
                                            @else
                                                {{-- One Document Holder surface: label + guide + single yellow + → preview → Submit --}}
                                                <form method="POST"
                                                      action="{{ route('site.partners.apply.fulfill', $application) }}"
                                                      enctype="multipart/form-data"
                                                      class="p-4 space-y-3"
                                                      data-no-draft
                                                      data-no-saving
                                                      x-data="{
                                                          hostId: @js($hostId),
                                                          hasFile: false,
                                                          markReady(detail) {
                                                              if (detail?.hostId && detail.hostId !== this.hostId) return;
                                                              this.hasFile = true;
                                                          },
                                                          openFromPicker(detail) {
                                                              if (detail?.hostId && detail.hostId !== this.hostId) return;
                                                              const source = detail?.source === 'camera' ? 'document-open-camera' : 'document-open-upload';
                                                              this.$dispatch(source, { hostId: this.hostId, fresh: true });
                                                          },
                                                      }"
                                                      @document-source.window="openFromPicker($event.detail)"
                                                      @kf-document-pages-ready.window="markReady($event.detail)"
                                                      @kf-document-file.window="markReady($event.detail)"
                                                      @document-pages-changed.window="
                                                          if ($event.detail?.name === 'document' || $event.detail?.name === 'doc_national_id_front' || $event.detail?.name === 'doc_national_id_back') {
                                                              hasFile = ($event.detail.count || 0) > 0;
                                                          }
                                                      ">
                                                    @csrf
                                                    <input type="hidden" name="phone" value="{{ $phone }}">
                                                    <input type="hidden" name="request_id" value="{{ $reqId }}">

                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold text-gray-900">
                                                                <span x-text="hasFile ? @js(($req['label'] ?? __('site.partner_apply.track_request_item')).' ✓') : @js($req['label'] ?? __('site.partner_apply.track_request_item'))"></span>
                                                            </p>
                                                            @if (! empty($req['explanation']))
                                                                <p class="text-xs text-gray-600 mt-0.5">{{ $req['explanation'] }}</p>
                                                            @endif
                                                            <p class="text-xs text-gray-500 mt-1" x-show="!hasFile">{{ $docGuide }}</p>
                                                        </div>
                                                        <div class="flex items-center gap-2 shrink-0">
                                                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-full px-2 py-0.5">{{ $statusBadge }}</span>
                                                            @if ($reqType !== 'national_id')
                                                                <div x-show="!hasFile">
                                                                    <x-site.document-source-picker :host-id="$hostId" />
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if ($reqType === 'national_id')
                                                        <div class="space-y-4">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <p class="text-xs font-semibold text-brand">{{ __('site.partner_apply.nida_front') }}</p>
                                                                <x-site.document-source-picker :host-id="$hostId.'-front'" />
                                                            </div>
                                                            <x-site.single-image-document-upload
                                                                name="doc_national_id_front"
                                                                :input-host-id="$hostId.'-front'"
                                                                facing="environment"
                                                                :required="true"
                                                                source-driven="true"
                                                                guide-frame="id-card"
                                                            />
                                                            <div class="flex items-center justify-between gap-2">
                                                                <p class="text-xs font-semibold text-brand">{{ __('site.partner_apply.nida_back') }}</p>
                                                                <x-site.document-source-picker :host-id="$hostId.'-back'" />
                                                            </div>
                                                            <x-site.single-image-document-upload
                                                                name="doc_national_id_back"
                                                                :input-host-id="$hostId.'-back'"
                                                                facing="environment"
                                                                :required="true"
                                                                source-driven="true"
                                                                guide-frame="id-card"
                                                            />
                                                        </div>
                                                    @elseif (in_array($reqType, ['national_id_front', 'national_id_back'], true))
                                                        <x-site.single-image-document-upload
                                                            :name="$reqType === 'national_id_front' ? 'doc_national_id_front' : 'doc_national_id_back'"
                                                            :input-host-id="$hostId"
                                                            facing="environment"
                                                            :required="true"
                                                            source-driven="true"
                                                            guide-frame="id-card"
                                                        />
                                                    @else
                                                        <x-site.multi-page-document-upload
                                                            name="document"
                                                            :input-host-id="$hostId"
                                                            :max-pages="1"
                                                            :required="true"
                                                            :source-driven="true"
                                                            :auto-finish-upload="true"
                                                            output-mode="images"
                                                        />
                                                    @endif

                                                    <button type="submit"
                                                            class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm disabled:opacity-40"
                                                            :disabled="!hasFile"
                                                            :class="!hasFile ? 'pointer-events-none' : ''">
                                                        {{ __('site.partner_apply.track_request_submit') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @else
                            <div class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 text-brand space-y-2">
                                @if ($isPending && ! empty($resultPayload['review_period']))
                                    <p class="text-sm text-brand/90">{{ __('site.partner_apply.track_review_period', ['period' => $resultPayload['review_period']]) }}</p>
                                @endif
                                <p>{{ __('site.partner_apply.track_pending_body') }}</p>
                            </div>
                        @endif

                        <div class="pt-2 border-t border-gray-100">
                            <a href="{{ route('site.partners.apply.tracking') }}"
                               class="inline-flex text-sm font-semibold text-gray-500 hover:text-brand">
                                {{ __('site.partner_apply.track_check_another') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-site.layout>
