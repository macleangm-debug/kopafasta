@if ($groupPayout ?? null)
    <div class="glass-card overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">{{ __('borrower.apply.group.payout.title') }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ __('borrower.apply.group.payout.subtitle', ['installments' => $groupPayout['installments_between'] ?? 2]) }}</p>
        </div>
        @if ($groupPayout['current_recipient'] ?? null)
            <div class="px-5 py-3 bg-amber-50 border-b border-amber-100 text-sm">
                <span class="font-semibold text-amber-900">{{ __('borrower.apply.group.payout.current') }}:</span>
                {{ $groupPayout['current_recipient']['name'] }} · {{ $groupPayout['current_recipient']['status'] }}
            </div>
        @endif
        @if ($groupPayout['next_recipient'] ?? null)
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 text-sm text-gray-700">
                <span class="font-semibold">{{ __('borrower.apply.group.payout.next') }}:</span>
                {{ $groupPayout['next_recipient']['name'] }}
                @if (($groupPayout['next_recipient']['repayments_remaining'] ?? 0) > 0)
                    · {{ __('borrower.apply.group.payout.repayments_remaining', ['count' => $groupPayout['next_recipient']['repayments_remaining']]) }}
                @endif
            </div>
        @endif
        <div class="px-5 py-4 grid sm:grid-cols-3 gap-3 text-sm border-b border-gray-100">
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.payout.disbursed_count') }}</p>
                <p class="font-semibold">{{ $groupPayout['group_repayment']['members_disbursed'] ?? 0 }} / {{ $groupPayout['group_repayment']['members_total'] ?? 0 }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.payout.total_outstanding') }}</p>
                <p class="font-semibold">{{ format_money($groupPayout['group_repayment']['total_outstanding'] ?? 0) }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.payout.total_repaid') }}</p>
                <p class="font-semibold">{{ format_money($groupPayout['group_repayment']['total_repaid'] ?? 0) }}</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 bg-gray-50">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">{{ __('borrower.apply.group.dashboard.member_name') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.apply.group.payout.queue_status') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.apply.group.payout.repayments') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($groupPayout['members'] ?? [] as $member)
                        <tr class="{{ ! empty($member['is_current_recipient']) ? 'bg-amber-50/50' : '' }}">
                            <td class="px-4 py-3 text-gray-500">{{ $member['sort_order'] }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $member['name'] }}</p>
                                @if ($member['role'] === 'leader')
                                    <p class="text-[10px] uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.apply.group_members.leader_badge') }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    @class([
                                        'bg-emerald-100 text-emerald-800' => ($member['disbursement_status'] ?? '') === 'disbursed',
                                        'bg-amber-100 text-amber-800' => ($member['disbursement_status'] ?? '') === 'unlocked',
                                        'bg-gray-100 text-gray-700' => ($member['disbursement_status'] ?? '') === 'locked',
                                    ])">
                                    {{ $member['disbursement_label'] ?? $member['disbursement_status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                {{ $member['repayments_made'] ?? 0 }} / {{ $member['repayments_required'] ?? 0 }}
                                @if (($member['repayments_remaining'] ?? 0) > 0 && ($member['disbursement_status'] ?? '') !== 'locked')
                                    <span class="text-gray-400">· {{ __('borrower.apply.group.payout.until_next', ['count' => $member['repayments_remaining']]) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
