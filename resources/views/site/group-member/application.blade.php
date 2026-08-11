<x-site.borrower-layout :title="brand_title(__('borrower.apply.group.application_title'))" active="dashboard" content-width="wide">
    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('warning'))
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
        @endif

        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.apply.group.onboarding_label') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold">{{ __('borrower.apply.group.application_title') }}</h1>
            <p class="text-sm text-gray-500 mt-2">
                {{ __('borrower.apply.group.application_intro', ['leader' => $invitation->leader?->full_name ?? brand_name()]) }}
            </p>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <section class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.apply.group.readonly_quote') }}</h2>
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('borrower.apply.group.reference') }}</dt>
                            <dd class="font-mono font-semibold mt-1">{{ $draft_reference ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.name') }}</dt>
                            <dd class="font-semibold mt-1">{{ $group_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.purpose') }}</dt>
                            <dd class="font-semibold mt-1">{{ $group_purpose ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.amount_per_member') }}</dt>
                            <dd class="font-semibold mt-1">{{ $amount_per_member > 0 ? format_money($amount_per_member) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.tenure') }}</dt>
                            <dd class="font-semibold mt-1">{{ $tenure_months > 0 ? $tenure_months.' '.__('borrower.apply.quote.months') : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('borrower.apply.group_setup.repayment_cadence') }}</dt>
                            <dd class="font-semibold mt-1">{{ $cadence_label }}</dd>
                        </div>
                    </dl>
                    @if ($invitation_reason)
                        <div class="mt-4 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">{{ __('borrower.apply.group.invitation_reason') }}</p>
                            <p class="text-gray-800">{{ $invitation_reason }}</p>
                        </div>
                    @endif
                    @if (! empty($installment_preview))
                        <div class="mt-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ __('borrower.apply.group.your_repayment_preview') }}</p>
                            <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                        <tr>
                                            <th class="text-left px-3 py-2">#</th>
                                            <th class="text-left px-3 py-2">{{ __('borrower.apply.review_step.col_due_date') }}</th>
                                            <th class="text-right px-3 py-2">{{ __('borrower.apply.review_step.col_total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach (array_slice($installment_preview, 0, 4) as $row)
                                            <tr>
                                                <td class="px-3 py-2">{{ $row['installment_number'] ?? '' }}</td>
                                                <td class="px-3 py-2">{{ $row['due_date'] ?? '' }}</td>
                                                <td class="px-3 py-2 text-right font-semibold">{{ format_money($row['total_due'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">{{ __('borrower.apply.group.per_member_schedule_note') }}</p>
                        </div>
                    @endif
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">{{ $group_name ?: __('borrower.apply.group_members.title') }}</h2>
                    <div class="space-y-2 mb-4">
                        @foreach ($progress['summary'] ?? [] as $line)
                            <p class="text-sm text-gray-600">{{ $line }}</p>
                        @endforeach
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="text-left px-3 py-2">{{ __('borrower.apply.group_members.member') }}</th>
                                    <th class="text-left px-3 py-2">{{ __('borrower.apply.group_members.status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($members as $member)
                                    <tr>
                                        <td class="px-3 py-2 font-medium">{{ $member['name'] ?? '—' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                                {{ $member['status_label'] ?? '' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-3 py-4 text-gray-500">{{ __('borrower.apply.group.no_members_listed') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                @if (! empty($group_payout ?? null))
                    @include('site.borrower.loan-profile._group_payout_queue', ['groupPayout' => $group_payout])
                @endif
            </div>

            <aside class="space-y-4">
                <section class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="font-semibold text-gray-900 mb-2">{{ __('borrower.apply.group.your_progress') }}</h2>
                    <p class="text-3xl font-bold text-amber-600">{{ $profile['percent'] ?? 0 }}%</p>
                    <p class="text-sm text-gray-500 mt-1">{{ __('borrower.apply.group.profile_completion') }}</p>
                    @if (! $profile_complete)
                        <a href="{{ $profile_url }}" class="mt-4 inline-flex w-full items-center justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-3 rounded-full text-sm">
                            {{ __('borrower.apply.group.complete_profile_cta') }}
                        </a>
                    @elseif ($can_finalize)
                        <a href="{{ $onboarding_url }}" class="mt-4 inline-flex w-full items-center justify-center bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-3 rounded-full text-sm">
                            {{ __('borrower.apply.group.sign_and_submit_cta') }}
                        </a>
                    @else
                        <p class="mt-4 text-sm text-emerald-700 font-medium">{{ __('borrower.apply.group.waiting_for_group') }}</p>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-site.borrower-layout>
