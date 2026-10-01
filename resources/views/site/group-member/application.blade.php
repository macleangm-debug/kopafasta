<x-site.borrower-layout
    :title="brand_title(__('borrower.apply.group.application_title'))"
    active="loans"
    content-width="narrow">
    @php
        $leaderName = $invitation->leader?->full_name ?? brand_name();
        $displayGroupName = $group_name ?: __('borrower.apply.group.loan_label');
        $quoteReady = (bool) ($quote_ready ?? false);
        $profilePercent = (int) ($profile['percent'] ?? 0);
        $decisionRequired = (bool) ($decision_required ?? false);
        $memberProgress = $member_progress ?? [];
        $steps = $memberProgress['progress_steps'] ?? [];
        $badge = $memberProgress['status_label'] ?? null;
        $badgeTone = $memberProgress['badge_tone'] ?? 'sky';
        $otherMembers = collect($members ?? [])
            ->reject(fn ($row) => (int) ($row['invitation_id'] ?? 0) === (int) $invitation->id
                || (int) ($row['customer_id'] ?? 0) === (int) ($member->id ?? 0))
            ->values();
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans') }}" data-kf-motion="pop" class="text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.nav.loans') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-6">
        <div class="absolute -right-16 -top-16 h-44 w-44 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-5 sm:px-6 py-5 sm:py-6 text-white">
            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.group.onboarding_label') }}</p>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ $displayGroupName }}</h1>
            <p class="mt-2 text-sm text-white/85">{{ $leaderName }}@if ($draft_reference) · {{ $draft_reference }}@endif</p>
            @if ($badge)
                <p class="mt-4 inline-flex text-xs font-semibold rounded-full px-3 py-1.5 bg-white/15 ring-1 ring-white/25">
                    {{ $badge }}
                </p>
            @endif
        </div>
    </section>

    <div class="glass-card p-5 mb-6 ring-1 ring-brand/15">
        <h2 class="font-semibold mb-4">{{ __('borrower.apply.group.request_overview') }}</h2>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.group_setup.amount_per_member') }}</p>
                <p class="font-semibold mt-1">{{ $quoteReady ? format_money($amount_per_member) : '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.group_setup.tenure') }}</p>
                <p class="font-semibold mt-1">{{ $tenure_months ? $tenure_months.' '.__('borrower.apply.quote.months') : '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.group_setup.purpose') }}</p>
                <p class="font-semibold mt-1">{{ $group_purpose ?: '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $installment_label ?? __('borrower.apply.group_setup.weekly_installment_label') }}</p>
                <p class="font-semibold mt-1 text-brand">{{ ($installment_amount ?? 0) > 0 ? format_money($installment_amount) : '—' }}</p>
            </div>
        </div>
    </div>

    <div class="mb-6">
        <x-site.invitee-progress
            :name="''"
            :badge="$badge"
            :badge-tone="$badgeTone"
            :steps="$steps"
        />
    </div>

    @if ($decisionRequired)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15"
             x-data="{ submitting: false, action: null }">
            <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group.your_decision') }}</p>
                <p class="mt-2 text-sm text-gray-700 leading-relaxed">{{ __('borrower.apply.group.decision_short') }}</p>
                <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3 sm:items-center sm:justify-end">
                    <form method="POST" action="{{ route('site.group-member.reject', $invitation->token) }}"
                          @submit="if (submitting) { $event.preventDefault(); return false } submitting = true; action = 'reject'">
                        @csrf
                        <button type="submit"
                                :disabled="submitting"
                                class="w-full sm:w-auto bg-white ring-1 ring-gray-300 hover:bg-gray-50 text-gray-800 font-semibold px-5 py-2.5 rounded-xl text-sm disabled:opacity-60 disabled:cursor-wait">
                            <span x-show="!(submitting && action === 'reject')">{{ __('borrower.apply.group.decline_invite') }}</span>
                            <span x-cloak x-show="submitting && action === 'reject'">{{ __('borrower.apply.group.saving') }}…</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('site.group-member.accept', $invitation->token) }}"
                          @submit="if (submitting) { $event.preventDefault(); return false } submitting = true; action = 'approve'">
                        @csrf
                        <button type="submit"
                                :disabled="submitting"
                                class="w-full sm:w-auto bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-3.5 rounded-xl text-sm shadow-sm disabled:opacity-60 disabled:cursor-wait">
                            <span x-show="!(submitting && action === 'approve')">{{ __('borrower.apply.group.accept_request_cta') }}</span>
                            <span x-cloak x-show="submitting && action === 'approve'">{{ __('borrower.apply.group.saving') }}…</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @elseif (! $profile_complete)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="px-5 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group.your_progress') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-2">
                        {{ __('borrower.apply.group.profile_next_short', ['percent' => $profilePercent]) }}
                    </p>
                    <div class="flex items-center gap-3 mt-3 max-w-md">
                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ max(0, min(100, $profilePercent)) }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-gray-900">{{ $profilePercent }}%</span>
                    </div>
                </div>
                <a href="{{ $profile_url }}"
                   class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand">
                    {{ __('borrower.apply.group.complete_profile_cta') }}
                </a>
            </div>
        </div>
    @elseif ($can_finalize)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15 px-5 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <p class="text-sm text-gray-700">{{ __('borrower.apply.group.waiting_for_group') }}</p>
            <a href="{{ $onboarding_url }}"
               class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm shrink-0 bg-brand hover:bg-brand-light text-white">
                {{ __('borrower.apply.group.sign_and_submit_cta') }}
            </a>
        </div>
    @else
        <div class="mb-6 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
            {{ __('borrower.apply.group.waiting_for_group') }}
        </div>
    @endif

    @if ($otherMembers->isNotEmpty() || collect($members ?? [])->isNotEmpty())
        <div class="glass-card overflow-hidden ring-1 ring-brand/15 mb-6">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100/80 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group.group_members_section') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-1">{{ ($progress['added'] ?? 0) }}/{{ ($progress['target'] ?? 0) }}</p>
                </div>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($members as $row)
                    @php
                        $rowSteps = $row['progress_steps'] ?? [];
                        $rowBadge = $row['status_label'] ?? null;
                        $rowTone = $row['badge_tone'] ?? 'sky';
                    @endphp
                    <li class="px-5 sm:px-6 py-4">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-gray-900 truncate">{{ $row['name'] ?? '—' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ ($row['role'] ?? '') === 'leader'
                                        ? __('borrower.apply.group_members.leader_badge')
                                        : __('borrower.apply.group_members.member_badge') }}
                                </p>
                            </div>
                            @if ($rowBadge)
                                <span class="text-[11px] font-semibold text-gray-600 shrink-0">{{ $rowBadge }}</span>
                            @endif
                        </div>
                        @if (! empty($rowSteps))
                            <x-site.invitee-progress
                                :name="''"
                                :badge="null"
                                :badge-tone="$rowTone"
                                :steps="$rowSteps"
                            />
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($group_payout ?? null))
        @include('site.borrower.loan-profile._group_payout_queue', ['groupPayout' => $group_payout])
    @endif
</x-site.borrower-layout>
