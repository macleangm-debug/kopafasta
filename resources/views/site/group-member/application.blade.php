<x-site.borrower-layout :title="brand_title(__('borrower.apply.group.application_title'))" active="dashboard" content-width="wide">
    @php
        $leaderName = $invitation->leader?->full_name ?? brand_name();
        $displayGroupName = $group_name ?: __('borrower.apply.group.loan_label');
        $quoteReady = (bool) ($quote_ready ?? false);
        $profilePercent = (int) ($profile['percent'] ?? 0);
    @endphp

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
        @endif

        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-6 sm:px-7 sm:py-8 shadow-lg shadow-brand/20">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 12% 20%, #fbbf24 0, transparent 42%), radial-gradient(circle at 90% 0%, #fff 0, transparent 36%);"></div>
            <div class="relative">
                <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-brand-gold">{{ __('borrower.apply.group.onboarding_label') }}</p>
                <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight">{{ $displayGroupName }}</h1>
                <p class="mt-3 text-sm text-white/80 leading-relaxed max-w-2xl">
                    {{ __('borrower.apply.group.application_intro', ['leader' => $leaderName]) }}
                </p>
                @if ($draft_reference)
                    <p class="mt-4 inline-flex items-center rounded-xl bg-white/10 ring-1 ring-white/15 px-3 py-1.5 font-mono text-xs text-white/90">{{ $draft_reference }}</p>
                @endif
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <section class="rounded-3xl overflow-hidden ring-1 ring-brand/12 bg-white shadow-sm">
                    <div class="bg-gradient-to-b from-brand-muted/40 to-white px-5 sm:px-6 py-4 border-b border-brand/10">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group.loan_details_eyebrow') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-gray-900">
                            {{ $quoteReady ? __('borrower.apply.group.readonly_quote') : __('borrower.apply.group.loan_details_pending_title') }}
                        </h2>
                    </div>

                    @if ($quoteReady)
                        <dl class="grid sm:grid-cols-2 gap-0 divide-y sm:divide-y-0 sm:divide-x divide-brand/10 text-sm">
                            <div class="px-5 sm:px-6 py-4">
                                <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.purpose') }}</dt>
                                <dd class="font-semibold mt-1 text-gray-900">{{ $group_purpose ?: '—' }}</dd>
                            </div>
                            <div class="px-5 sm:px-6 py-4">
                                <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.amount_per_member') }}</dt>
                                <dd class="font-extrabold mt-1 tabular-nums text-brand">{{ format_money($amount_per_member) }}</dd>
                            </div>
                            <div class="px-5 sm:px-6 py-4">
                                <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.tenure') }}</dt>
                                <dd class="font-semibold mt-1 text-gray-900">{{ $tenure_months }} {{ __('borrower.apply.quote.months') }}</dd>
                            </div>
                            <div class="px-5 sm:px-6 py-4">
                                <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.repayment_cadence') }}</dt>
                                <dd class="font-semibold mt-1 text-gray-900">{{ $cadence_label }}</dd>
                            </div>
                        </dl>
                        @if ($invitation_reason)
                            <div class="mx-5 sm:mx-6 mb-5 rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3 text-sm">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-brand mb-1">{{ __('borrower.apply.group.invitation_reason') }}</p>
                                <p class="text-gray-800 leading-relaxed">{{ $invitation_reason }}</p>
                            </div>
                        @endif
                        @if (! empty($installment_preview))
                            <div class="px-5 sm:px-6 pb-6">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500 mb-3">{{ __('borrower.apply.group.your_repayment_preview') }}</p>
                                <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-200">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                                            <tr>
                                                <th class="text-left px-3 py-2.5">#</th>
                                                <th class="text-left px-3 py-2.5">{{ __('borrower.apply.review_step.col_due_date') }}</th>
                                                <th class="text-right px-3 py-2.5">{{ __('borrower.apply.review_step.col_total') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach (array_slice($installment_preview, 0, 4) as $row)
                                                <tr>
                                                    <td class="px-3 py-2.5">{{ $row['installment_number'] ?? '' }}</td>
                                                    <td class="px-3 py-2.5">{{ $row['due_date'] ?? '' }}</td>
                                                    <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ format_money($row['total_due'] ?? 0) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">{{ __('borrower.apply.group.per_member_schedule_note') }}</p>
                            </div>
                        @endif
                    @else
                        <div class="px-5 sm:px-6 py-6 space-y-4">
                            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200/80 px-4 py-4">
                                <p class="text-sm font-semibold text-amber-950">{{ __('borrower.apply.group.loan_details_pending_title') }}</p>
                                <p class="mt-1.5 text-sm text-amber-900/90 leading-relaxed">
                                    {{ __('borrower.apply.group.loan_details_pending_body', ['leader' => $leaderName]) }}
                                </p>
                            </div>
                            <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                                    <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.name') }}</dt>
                                    <dd class="font-semibold mt-1 text-gray-900">{{ $group_name ?: '—' }}</dd>
                                </div>
                                <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                                    <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.purpose') }}</dt>
                                    <dd class="font-semibold mt-1 text-gray-900">{{ $group_purpose ?: '—' }}</dd>
                                </div>
                            </dl>
                            @if ($invitation_reason)
                                <div class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3 text-sm">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-brand mb-1">{{ __('borrower.apply.group.invitation_reason') }}</p>
                                    <p class="text-gray-800 leading-relaxed">{{ $invitation_reason }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </section>

                <section class="rounded-3xl overflow-hidden ring-1 ring-brand/12 bg-white shadow-sm">
                    <div class="px-5 sm:px-6 py-4 border-b border-brand/10 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group_members.your_team') }}</p>
                            <h2 class="mt-1 text-lg font-bold text-gray-900">{{ $displayGroupName }}</h2>
                        </div>
                        <p class="text-sm font-semibold text-brand tabular-nums">
                            {{ ($progress['added'] ?? 0) }}/{{ ($progress['target'] ?? 0) }}
                        </p>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($members as $row)
                            @php
                                $percent = (int) ($row['profile_percent'] ?? 0);
                                $avatarUrl = $row['avatar_url'] ?? null;
                                $isLeader = ($row['role'] ?? '') === 'leader';
                            @endphp
                            <li class="px-5 sm:px-6 py-4 flex flex-wrap items-center gap-3">
                                <div class="size-11 rounded-2xl bg-brand text-white grid place-items-center text-sm font-bold shrink-0 overflow-hidden">
                                    @if ($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="" class="size-full object-cover">
                                    @else
                                        {{ strtoupper(mb_substr((string) ($row['name'] ?? '?'), 0, 1)) }}
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-sm text-gray-900 truncate">{{ $row['name'] ?? '—' }}</p>
                                        @if ($isLeader)
                                            <span class="inline-flex items-center rounded-full bg-brand text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5">
                                                {{ __('borrower.apply.group_members.leader_badge') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-brand-muted text-brand ring-1 ring-brand/15 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5">
                                                {{ __('borrower.apply.group_members.member_badge') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $row['phone'] ?? '' }}</p>
                                    <div class="mt-2 max-w-xs">
                                        <div class="flex items-center justify-between gap-2 text-[11px] text-gray-500 mb-1">
                                            <span>{{ __('borrower.apply.group.profile_completion') }}</span>
                                            <span class="font-bold tabular-nums text-brand">{{ $percent }}%</span>
                                        </div>
                                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div @class([
                                                'h-full rounded-full',
                                                'bg-emerald-500' => $percent >= 100,
                                                'bg-brand' => $percent < 100,
                                            ]) style="width: {{ max(0, min(100, $percent)) }}%"></div>
                                        </div>
                                    </div>
                                </div>
                                <span class="text-xs font-semibold text-gray-700 shrink-0">{{ $row['status_label'] ?? '—' }}</span>
                            </li>
                        @empty
                            <li class="px-5 sm:px-6 py-8 text-sm text-gray-500">{{ __('borrower.apply.group.no_members_listed') }}</li>
                        @endforelse
                    </ul>
                </section>

                @if (! empty($group_payout ?? null))
                    @include('site.borrower.loan-profile._group_payout_queue', ['groupPayout' => $group_payout])
                @endif
            </div>

            <aside class="space-y-4">
                <section class="rounded-3xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm">
                    <div class="bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-5">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.group.your_progress') }}</p>
                        <p class="mt-3 text-4xl font-extrabold tabular-nums">{{ $profilePercent }}%</p>
                        <p class="mt-1 text-sm text-white/75">{{ __('borrower.apply.group.profile_completion') }}</p>
                        <div class="mt-4 h-2 rounded-full bg-white/15 overflow-hidden">
                            <div class="h-full rounded-full bg-brand-gold" style="width: {{ max(0, min(100, $profilePercent)) }}%"></div>
                        </div>
                    </div>
                    <div class="px-5 py-5">
                        @if (! $profile_complete)
                            <a href="{{ $profile_url }}" class="inline-flex w-full items-center justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-3 rounded-xl text-sm shadow-sm">
                                {{ __('borrower.apply.group.complete_profile_cta') }}
                            </a>
                        @elseif ($can_finalize)
                            <a href="{{ $onboarding_url }}" class="inline-flex w-full items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-3 rounded-xl text-sm shadow-sm">
                                {{ __('borrower.apply.group.sign_and_submit_cta') }}
                            </a>
                        @else
                            <p class="text-sm text-emerald-800 font-medium leading-relaxed">{{ __('borrower.apply.group.waiting_for_group') }}</p>
                        @endif
                        @unless ($quoteReady)
                            <p class="mt-4 text-xs text-gray-500 leading-relaxed">{{ __('borrower.apply.group.loan_details_pending_hint') }}</p>
                        @endunless
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-site.borrower-layout>
