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
        $showGuarantorBlock = $isDraft
            || ($application && app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($application));
        $showDisbursementChecklist = ! $isDraft && $isPostApproval && ! $isDisbursed && ! empty($profile['disbursement_checklist']);
        $showSchedule = false; // Repayment schedule lives on the active loan page only.
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications']) }}" class="text-sm font-semibold text-brand hover:underline">
            {{ __('borrower.loan_profile.back') }}
        </a>
    </div>

    <x-site.borrower-page-header
        :eyebrow="__('borrower.loan_profile.label')"
        :title="$summary['product_name']"
        :subtitle="$summary['application_number']"
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
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.amount') }}</p>
                    <p class="font-semibold mt-1">{{ $summary['requested_amount'] ? format_money($summary['requested_amount']) : '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.tenure') }}</p>
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
        <details class="glass-card mb-6 group overflow-hidden ring-1 ring-brand/10" @if ($isPostApproval || $isDisbursed) open @endif>
            <summary class="cursor-pointer list-none px-5 py-4 flex items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.summary_collapsed') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">
                        {{ $summary['requested_amount'] ? format_money($summary['requested_amount']) : '—' }}
                        @if (! empty($summary['requested_tenure']))
                            · {{ __('borrower.applications_list.tenure_months', ['count' => $summary['requested_tenure']]) }}
                        @endif
                    </p>
                </div>
                <svg class="size-4 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </summary>
            <div class="px-5 pb-5 border-t border-gray-100 pt-4 grid sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.loan_type') }}</p>
                    <p class="font-semibold mt-1">{{ $summary['loan_type'] }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loan_profile.interest_rate') }}</p>
                    <p class="font-semibold mt-1">{{ $summary['interest_rate_label'] ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.created') }}</p>
                    <p class="font-semibold mt-1">{{ optional($summary['created_at'])->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.last_updated') }}</p>
                    <p class="font-semibold mt-1">{{ optional($summary['updated_at'])->format('d M Y') ?? '—' }}</p>
                </div>
            </div>
        </details>
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
        <div id="requested-actions" class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="bg-gradient-to-r from-brand-muted/50 to-white px-5 py-4 border-b border-brand/10">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.missing_requirements_title') }}</p>
            </div>
            <div class="px-5 py-4 flex flex-wrap gap-2">
                @foreach ($missingRequirements as $item)
                    <a href="{{ $item['upload_url'] ?? $completeProfileUrl }}"
                       class="inline-flex items-center gap-1.5 rounded-full bg-brand-muted/50 ring-1 ring-brand/15 px-3 py-1.5 text-xs font-semibold text-brand hover:bg-brand-muted">
                        <span>{{ $item['label'] ?? '—' }}</span>
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

</x-site.borrower-layout>
