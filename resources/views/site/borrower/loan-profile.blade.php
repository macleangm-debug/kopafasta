<x-site.borrower-layout
    :title="brand_title($profile['summary']['application_number'] ?? __('borrower.loan_profile.title'))"
    active="loans"
    content-width="narrow">

    @php
        $summary = $profile['summary'];
        $status = $profile['status'];
        $progress = $profile['progress'];
        $application = $profile['application'] ?? null;
        $draft = $profile['draft'] ?? null;
        $isDraft = (bool) ($profile['is_draft'] ?? false);
        $statusCode = (string) ($status['code'] ?? '');
        $underwritingActions = collect($profile['underwriting_actions'] ?? []);
        $underwritingActionKeys = $underwritingActions
            ->map(fn ($action) => 'request-'.($action['id'] ?? ''))
            ->filter()
            ->all();
        $missingRequirements = collect($profile['missing_requirements'] ?? [])
            ->filter(fn ($item) => empty($item['complete']))
            ->reject(fn ($item) => in_array($item['key'] ?? '', $underwritingActionKeys, true));
        $completeProfileUrl = route('site.borrower.profile');
        $isRejected = $statusCode === 'rejected';
        $isDisbursed = in_array($statusCode, ['disbursed', 'closed'], true)
            || (! empty($profile['loan']) && in_array((string) $profile['loan']->status, ['active', 'disbursed', 'arrears'], true));
        $isPostApproval = (bool) ($progress['is_loan_progress'] ?? false)
            || in_array($statusCode, [
                'approved', 'awaiting_offer', 'awaiting_signature', 'offer_accepted',
                'awaiting_disbursement_details', 'awaiting_contract', 'ready_for_disbursement',
                'post_approval_fees',
            ], true);
        $showTimeline = ! $isDraft && ! $isRejected && $isPostApproval && ! empty($progress['timeline']);
        $showGuarantorBlock = (
                ($profile['requires_guarantor'] ?? false)
                || ($application?->product?->requires_guarantor ?? false)
                || (($profile['guarantor_invitations'] ?? collect())->isNotEmpty())
                || ($application && app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($application))
            );
        $showDisbursementChecklist = ! $isDraft && $isPostApproval && ! $isDisbursed && ! empty($profile['disbursement_checklist']);
        $showSchedule = false; // Repayment schedule lives on the active loan page only.
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications']) }}" data-kf-motion="pop" class="text-sm font-semibold text-brand hover:underline">
            {{ __('borrower.loan_profile.back') }}
        </a>
    </div>

    <x-site.borrower-page-header
        :eyebrow="__('borrower.loan_profile.label')"
        :title="$summary['product_name']"
        :subtitle="$summary['application_number']"
        :share="($application?->id ?? $draft?->id) ? 'kf-app-'.($application?->id ?? $draft?->id) : null"
    />

    @if (! empty($summary['loan_number']))
        <p class="text-xs text-emerald-700 -mt-4 mb-4 font-mono">{{ __('borrower.loan_profile.loan_number') }}: {{ $summary['loan_number'] }}</p>
    @endif

    @if ($errors->any())
        <div
            x-data
            x-init="
                window.dispatchEvent(new CustomEvent('open-feedback-default', {
                    detail: {
                        title: @js(__('borrower.feedback.form_errors_title')),
                        lines: @js([$errors->first()]),
                        tone: 'error',
                    }
                }));
            "
            class="sr-only"
            aria-hidden="true"
        ></div>
    @endif

    @if ($statusCode === 'offer_declined')
        <div class="mb-4 rounded-xl bg-amber-50 ring-amber-200 text-amber-900 ring-1 px-4 py-4 text-sm">
            <p class="font-semibold text-base">{{ __('borrower.applications_list.statuses.offer_declined') }}</p>
            @if (! empty($status['detail']))
                <p class="mt-1">{{ $status['detail'] }}</p>
            @endif
        </div>
    @endif

    {{-- 1. Status + next action (always first after submit) --}}
    @include('site.borrower.loan-profile._action_panel', ['profile' => $profile])

    @if (! $isDraft && $application)
        @include('site.borrower.loan-profile._collateral_secure', ['profile' => $profile])
    @endif

    {{-- 1b. Requested documents — single card, directly under status when open --}}
    @if (! $isDraft && $application)
        @include('site.borrower.loan-profile._document_requests', [
            'profile' => $profile,
            'application' => $application,
        ])
    @endif

    {{-- 2. Post-approval progress only (fees → contract → disbursement) --}}
    @if ($showTimeline)
        <div class="mb-6">
            <x-site.application-timeline
                :steps="$progress['timeline']"
                :title="$progress['timeline_title'] ?? __('borrower.loan_progress.title')"
                :percent="$progress['application_percent'] ?? $progress['percent'] ?? null"
            />
        </div>
    @endif

    {{-- 3. Compact summary (collapsed after submission when under review) --}}
    @if ($isDraft)
        <div class="glass-card p-5 mb-6">
            <div class="mb-4">
                <h2 class="font-semibold">{{ __('borrower.loan_profile.summary_title') }}</h2>
            </div>
            <div class="grid sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.applications_list.amount') }}</p>
                    <p class="font-semibold mt-1">{{ $summary['requested_amount'] ? format_money($summary['requested_amount']) : '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.applications_list.tenure') }}</p>
                    <p class="font-semibold mt-1">
                        @if (! empty($summary['requested_tenure']))
                            {{ __('borrower.applications_list.tenure_months', ['count' => $summary['requested_tenure']]) }}
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @elseif (! $isRejected)
        <div class="glass-card mb-6 overflow-hidden ring-1 ring-brand/15">
            <div class="relative overflow-hidden bg-gradient-to-br from-brand-muted/60 via-white to-white px-5 py-5">
                <div class="absolute -right-8 -top-8 size-28 rounded-full bg-brand/5 pointer-events-none"></div>
                <div class="relative">
                    <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">{{ __('borrower.loan_profile.summary_collapsed') }}</p>
                    <p class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 tabular-nums">
                        {{ $summary['requested_amount'] ? format_money($summary['requested_amount']) : '—' }}
                    </p>
                    <p class="mt-1.5 text-sm text-gray-600">
                        @if (! empty($summary['requested_tenure']))
                            {{ __('borrower.applications_list.tenure_months', ['count' => $summary['requested_tenure']]) }}
                        @endif
                        @if (! empty($summary['loan_type']))
                            <span class="text-gray-300 mx-1.5">·</span>{{ $summary['loan_type'] }}
                        @endif
                    </p>
                </div>
                <div class="relative mt-4 grid grid-cols-2 gap-2.5">
                    <div class="rounded-xl bg-white/80 ring-1 ring-brand/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.interest_rate') }}</p>
                        <p class="mt-0.5 text-sm font-bold text-gray-900">{{ $summary['interest_rate_label'] ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-white/80 ring-1 ring-brand/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $summary['date_label'] ?? __('borrower.loan_profile.submitted_date') }}</p>
                        <p class="mt-0.5 text-sm font-bold text-gray-900">{{ optional($summary['primary_date'] ?? $summary['created_at'] ?? null)->format('d M Y') ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-white/80 ring-1 ring-brand/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.applications_list.loan_type') }}</p>
                        <p class="mt-0.5 text-sm font-bold text-gray-900">{{ $summary['loan_type'] ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-white/80 ring-1 ring-brand/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.applications_list.last_updated') }}</p>
                        <p class="mt-0.5 text-sm font-bold text-gray-900">{{ optional($summary['updated_at'] ?? null)->format('d M Y') ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($isDraft)
        @include('site.borrower.loan-profile._product_details', ['profile' => $profile])
    @endif

    @if ($showGuarantorBlock)
        @include('site.borrower.loan-profile._guarantor_progress', ['profile' => $profile])
    @endif

    @if ($isDraft)
        @include('site.borrower.loan-profile._group_member_progress', ['groupProgress' => $groupProgress ?? ($profile['product_details']['progress'] ?? null)])
    @endif

    @if ($isDraft && $missingRequirements->isNotEmpty())
        <div id="requested-actions" class="mb-6 overflow-hidden rounded-3xl ring-1 ring-amber-200 bg-gradient-to-br from-amber-50 via-white to-white shadow-sm">
            <div class="px-5 py-4 border-b border-amber-100/80">
                <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">{{ __('borrower.loan_profile.missing_requirements_title') }}</p>
                <p class="mt-1.5 text-sm text-amber-950/90 leading-relaxed">{{ __('borrower.loan_profile.missing_requirements_hint') }}</p>
            </div>
            <div class="px-5 py-4 space-y-2">
                @foreach ($missingRequirements as $item)
                    <a href="{{ $item['upload_url'] ?? $completeProfileUrl }}"
                       class="flex items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-amber-200/80 hover:ring-brand/30 hover:bg-brand-muted/20 px-4 py-3 transition">
                        <span class="text-sm font-semibold text-gray-900">{{ $item['label'] ?? '—' }}</span>
                        <span class="text-xs font-bold text-brand shrink-0">{{ __('borrower.loan_profile.complete_profile') }} →</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($showDisbursementChecklist)
        @include('site.borrower.loan-profile._disbursement_checklist', ['checklist' => $profile['disbursement_checklist']])
    @endif

    @if ($isPostApproval && ! empty($groupContract ?? null) && ! empty($application))
        @include('site.borrower.loan-profile._group_contract_progress', ['groupContract' => $groupContract, 'application' => $application])
    @endif

    @if ($isPostApproval && ! empty($groupPayout ?? null))
        @include('site.borrower.loan-profile._group_payout_queue', ['groupPayout' => $groupPayout])
    @endif

    @if ($isPostApproval || $isDisbursed)
        @include('site.borrower.loan-profile._handover_milestones', ['profile' => $profile])
    @endif

    @if (! empty($groupFeedback ?? null))
        <div class="glass-card p-5 mb-6 ring-2 ring-brand-gold/20">
            <h2 class="font-semibold mb-2">{{ __('borrower.apply.group.leader_feedback_title') }}</h2>
            <p class="text-xs text-gray-500 mb-4">{{ __('borrower.apply.group.leader_feedback_hint') }}</p>
            @if (filled($groupFeedback['group_feedback'] ?? null))
                <div class="rounded-lg bg-amber-50 p-4 text-sm text-gray-800 mb-4 whitespace-pre-wrap">{{ $groupFeedback['group_feedback'] }}</div>
            @endif
            @foreach ($groupFeedback['members'] ?? [] as $memberFeedback)
                <div class="border-t border-gray-100 pt-3 mt-3 text-sm">
                    <p class="font-semibold">{{ $memberFeedback['name'] }} <span class="text-xs text-gray-500 capitalize">({{ $memberFeedback['role'] }})</span></p>
                    @if (filled($memberFeedback['status'] ?? null) && $memberFeedback['status'] !== 'pending')
                        <p class="text-xs text-gray-500 mt-1 capitalize">{{ str_replace('_', ' ', $memberFeedback['status']) }}</p>
                    @endif
                    <p class="mt-1 text-gray-700 whitespace-pre-wrap">{{ $memberFeedback['feedback'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($isDraft)
        {{-- Draft summary only --}}
    @elseif ($application)
        @include('site.borrower.loan-profile._submitted', [
            'profile' => $profile,
            'application' => $application,
            'customer' => $customer,
            'showSchedule' => $showSchedule,
            'isPostApproval' => $isPostApproval,
            'isDisbursed' => $isDisbursed,
        ])
    @endif

    @php
        $guarantorRemind = null;
        $showGuarantorRemindModal = (bool) session('show_guarantor_remind_modal');
        if ($showGuarantorRemindModal && $application && ($application->status === 'awaiting_guarantor' || ($application->current_stage ?? '') === 'awaiting_guarantor')) {
            $invite = ($profile['guarantor_invitations'] ?? collect())->first()
                ?? \App\Models\GuarantorInvitation::query()->where('loan_application_id', $application->id)->latest('id')->first();
            if ($invite) {
                $guarantorRemind = app(\App\Services\GuarantorInvitationService::class)->sharePayload($invite, $customer);
            }
        }
    @endphp

    @if ($showGuarantorRemindModal && $guarantorRemind)
        <div x-data="{ open: true }"
             x-show="open"
             x-cloak
             class="fixed inset-0 z-[90] flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-brand/70 backdrop-blur-sm" @click="open = false"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-brand/15">
                <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.submit_step.guarantor_hold_title') }}</p>
                    <h3 class="text-lg font-bold mt-1">{{ __('borrower.apply.submit_step.guarantor_modal_title') }}</h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        {{ __('borrower.apply.submit_step.guarantor_modal_body_generic') }}
                    </p>
                    <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                        <button type="button"
                                @click="open = false"
                                class="inline-flex justify-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.apply.submit_step.guarantor_modal_dismiss') }}
                        </button>
                        @if (! empty($guarantorRemind['whatsapp_url']))
                            <a href="{{ $guarantorRemind['whatsapp_url'] }}" target="_blank" rel="noopener"
                               class="inline-flex justify-center bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-5 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.apply.submit_step.guarantor_modal_cta') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

</x-site.borrower-layout>
