<x-site.borrower-layout
    :title="brand_title($profile['summary']['application_number'] ?? __('borrower.loan_profile.title'))"
    active="loans"
    content-width="wide">

    @php
        $summary = $profile['summary'];
        $status = $profile['status'];
        $progress = $profile['progress'];
        $toneClasses = [
            'gray'    => 'bg-gray-100 text-gray-700',
            'amber'   => 'bg-amber-100 text-amber-700',
            'sky'     => 'bg-sky-100 text-sky-700',
            'emerald' => 'bg-emerald-100 text-emerald-700',
            'red'     => 'bg-red-100 text-red-700',
            'orange'  => 'bg-orange-100 text-orange-700',
        ];
        $statusBadge = $toneClasses[$status['tone']] ?? $toneClasses['sky'];
        $application = $profile['application'] ?? null;
        $draft = $profile['draft'] ?? null;
        $editQuoteUrl = $profile['edit_quote_url'] ?? null;
        $underwritingActionKeys = collect($profile['underwriting_actions'] ?? [])
            ->map(fn ($action) => 'request-'.($action['id'] ?? ''))
            ->filter()
            ->all();
        // UW requests surface as guided CTAs in the action panel — keep this list to product gaps.
        $missingRequirements = collect($profile['missing_requirements'] ?? [])
            ->filter(fn ($item) => empty($item['complete']))
            ->reject(fn ($item) => in_array($item['key'] ?? '', $underwritingActionKeys, true));
        $profilePercent = (int) ($progress['profile_percent'] ?? $progress['percent'] ?? 0);
        $profileComplete = (bool) ($progress['profile_complete'] ?? $profilePercent >= 100);
        $completeProfileUrl = route('site.borrower.profile');
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
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if (($status['code'] ?? '') === 'offer_declined')
        <div class="mb-4 rounded-xl bg-amber-50 ring-amber-200 text-amber-900 ring-1 px-4 py-4 text-sm">
            <p class="font-semibold text-base">{{ __('borrower.applications_list.statuses.offer_declined') }}</p>
            @if (! empty($status['detail']))
                <p class="mt-1">{{ $status['detail'] }}</p>
            @endif
        </div>
    @endif

    @include('site.borrower.loan-profile._action_panel', ['profile' => $profile])

    @if (! empty($progress['timeline']) && ($status['code'] ?? '') !== 'rejected')
        <div class="mb-6">
            <x-site.application-timeline
                :steps="$progress['timeline']"
                :title="$progress['timeline_title'] ?? __('borrower.loan_profile.application_progress')"
                :percent="$progress['application_percent'] ?? $progress['percent'] ?? null"
            />
        </div>
    @endif

    {{-- PDF section order: Profile → Summary → Guarantor → Missing --}}
    @unless ($profile['is_draft'] ?? false)
        @unless ($profileComplete)
            <div class="glass-card p-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1">
                        <h2 class="font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</h2>
                        <div class="flex items-center gap-3 mt-3">
                            <div class="flex-1 max-w-xs h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full bg-brand" style="width: {{ $profilePercent }}%"></div>
                            </div>
                            <span class="text-sm font-bold tabular-nums">{{ $profilePercent }}%</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">{{ __('borrower.loan_profile.profile_completion_hint') }}</p>
                    </div>
                    <a href="{{ $completeProfileUrl }}"
                       class="inline-flex items-center justify-center font-semibold px-6 py-2.5 rounded-xl text-sm shrink-0 bg-brand hover:bg-brand-light text-white">
                        {{ __('borrower.loan_profile.complete_profile') }}
                    </a>
                </div>
            </div>
        @endunless
    @endunless

    <div class="glass-card p-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <h2 class="font-semibold">{{ __('borrower.loan_profile.summary_title') }}</h2>
            @if ($editQuoteUrl)
                <a href="{{ $editQuoteUrl }}"
                   class="inline-flex text-xs font-semibold text-brand bg-brand-muted/40 ring-1 ring-brand/20 hover:bg-brand-muted px-3 py-2 rounded-lg">
                    {{ __('borrower.loan_profile.actions.edit_quote') }}
                </a>
            @endif
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.loan_type') }}</p>
                <p class="font-semibold mt-1">{{ $summary['loan_type'] }}</p>
            </div>
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
    </div>

    @include('site.borrower.loan-profile._guarantor_progress', ['profile' => $profile])

    @if ($missingRequirements->isNotEmpty())
        <div id="requested-actions" class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="bg-gradient-to-r from-brand-muted/50 to-white px-5 py-4 border-b border-brand/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.missing_requirements_title') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.loan_profile.missing_requirements_chips_hint') }}</p>
                </div>
                @unless ($profileComplete)
                    <a href="{{ $completeProfileUrl }}"
                       class="inline-flex justify-center shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('borrower.loan_profile.complete_profile') }}
                    </a>
                @endunless
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

    @if (! ($profile['is_draft'] ?? false) && ! empty($profile['disbursement_checklist']))
        @include('site.borrower.loan-profile._disbursement_checklist', ['checklist' => $profile['disbursement_checklist']])
    @endif

    @if (! empty($groupContract ?? null) && ! empty($application))
        @include('site.borrower.loan-profile._group_contract_progress', ['groupContract' => $groupContract, 'application' => $application])
    @endif

    @if (! empty($groupPayout ?? null))
        @include('site.borrower.loan-profile._group_payout_queue', ['groupPayout' => $groupPayout])
    @endif

    @include('site.borrower.loan-profile._handover_milestones', ['profile' => $profile])

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

    @if ($profile['is_draft'] ?? false)
        {{-- Draft summary only; details live on profile --}}
    @elseif ($application)
        @include('site.borrower.loan-profile._submitted', ['profile' => $profile, 'application' => $application, 'customer' => $customer])
    @endif

</x-site.borrower-layout>
