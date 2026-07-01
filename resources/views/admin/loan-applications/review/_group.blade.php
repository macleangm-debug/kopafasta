@if ($groupReview ?? null)
    <div id="review-group" class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">{{ __('admin.group_review.title') }}</h3>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <p class="text-sm text-gray-500">{{ $groupReview['name'] }} · {{ $groupReview['group_number'] }}</p>
                @if ($groupReview['application_status'] ?? null)
                    @php
                        $appStatus = $groupReview['application_status'];
                        $statusTone = match ($appStatus['tone'] ?? 'gray') {
                            'emerald' => 'bg-emerald-100 text-emerald-800',
                            'amber'   => 'bg-amber-100 text-amber-800',
                            'blue'    => 'bg-blue-100 text-blue-800',
                            'red'     => 'bg-red-100 text-red-800',
                            default   => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusTone }}">
                        {{ $appStatus['label'] }}
                    </span>
                @endif
            </div>
        </div>
        @if ($groupReview['scoring'] ?? null)
            @php $scoring = $groupReview['scoring']; @endphp
            <div class="px-5 py-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm border-b border-gray-100 bg-slate-50">
                <div>
                    <span class="text-gray-500 block text-xs uppercase tracking-widest">{{ __('admin.group_review.scoring.completion') }}</span>
                    <span class="font-semibold text-gray-900">{{ number_format($scoring['member_completion_percent'] ?? 0, 1) }}%</span>
                </div>
                <div>
                    <span class="text-gray-500 block text-xs uppercase tracking-widest">{{ __('admin.group_review.scoring.avg_credit') }}</span>
                    <span class="font-semibold text-gray-900">{{ isset($scoring['average_credit_score']) ? number_format($scoring['average_credit_score'], 0) : '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-500 block text-xs uppercase tracking-widest">{{ __('admin.group_review.scoring.avg_income') }}</span>
                    <span class="font-semibold text-gray-900">{{ isset($scoring['average_income']) ? format_money($scoring['average_income']) : '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-500 block text-xs uppercase tracking-widest">{{ __('admin.group_review.scoring.risk_score') }}</span>
                    <span class="font-semibold text-gray-900">
                        {{ $scoring['group_risk_score'] ?? '—' }}
                        @if (! empty($scoring['risk_band']))
                            <span class="text-xs font-medium text-gray-500">({{ __('admin.group_review.scoring.risk_band.'.$scoring['risk_band']) }})</span>
                        @endif
                    </span>
                </div>
            </div>
        @endif
        <div class="px-5 py-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm border-b border-gray-100">
            <div><span class="text-gray-500 block">{{ __('admin.group_review.leader') }}</span><span class="font-semibold">{{ $groupReview['leader'] ?? '—' }}</span></div>
            <div><span class="text-gray-500 block">{{ __('admin.group_review.members') }}</span><span class="font-semibold">{{ $groupReview['member_count'] }} / {{ $groupReview['target_member_count'] }}</span></div>
            <div><span class="text-gray-500 block">{{ __('admin.group_review.per_member') }}</span><span class="font-semibold">{{ format_money($groupReview['amount_per_member']) }}</span></div>
            <div><span class="text-gray-500 block">{{ __('admin.group_review.total_amount') }}</span><span class="font-semibold">{{ format_money($groupReview['total_amount']) }}</span></div>
        </div>

        @if (filled($groupReview['leader_feedback'] ?? null))
            <div class="px-5 py-4 border-b border-gray-100 bg-amber-50 text-sm">
                <p class="text-xs uppercase tracking-widest text-amber-800 font-semibold mb-1">{{ __('admin.group_review.leader_feedback_heading') }}</p>
                <p class="text-gray-800 whitespace-pre-wrap">{{ $groupReview['leader_feedback'] }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.loan-applications.group-feedback', $record) }}" class="px-5 py-4 border-b border-gray-100 space-y-3">
            @csrf
            <label class="block text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('admin.group_review.leader_feedback_label') }}</label>
            <textarea name="leader_feedback" rows="3" class="w-full rounded-lg border-gray-200 text-sm" placeholder="{{ __('admin.group_review.leader_feedback_placeholder') }}">{{ old('leader_feedback', $groupReview['leader_feedback'] ?? '') }}</textarea>
            <button type="submit" class="inline-flex bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-2 rounded-lg text-sm">{{ __('admin.group_review.save_group_feedback') }}</button>
        </form>

        @if ($groupReview['payout_queue'] ?? null)
            @php $payout = $groupReview['payout_queue']; @endphp
            <div class="px-5 py-4 border-b border-gray-100 bg-slate-50">
                <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-600 mb-3">{{ __('admin.group_review.payout.title') }}</h4>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-4">
                    <div>
                        <span class="text-gray-500 block text-xs">{{ __('admin.group_review.payout.order') }}</span>
                        <span class="font-semibold">{{ __('admin.group_review.payout.orders.'.$payout['payout_order']) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-xs">{{ __('admin.group_review.payout.installments_between') }}</span>
                        <span class="font-semibold">{{ $payout['installments_between'] }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-xs">{{ __('admin.group_review.payout.current') }}</span>
                        <span class="font-semibold">{{ $payout['current_recipient']['name'] ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-xs">{{ __('admin.group_review.payout.next') }}</span>
                        <span class="font-semibold">{{ $payout['next_recipient']['name'] ?? '—' }}</span>
                    </div>
                </div>
                <div class="overflow-x-auto rounded-lg ring-1 ring-gray-200 bg-white">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 text-left uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">{{ __('admin.group_review.col_member') }}</th>
                                <th class="px-3 py-2">{{ __('admin.group_review.payout.queue_status') }}</th>
                                <th class="px-3 py-2">{{ __('admin.group_review.payout.repayments') }}</th>
                                <th class="px-3 py-2">{{ __('admin.group_review.payout.outstanding') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($payout['members'] ?? [] as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['sort_order'] }}</td>
                                    <td class="px-3 py-2">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2">{{ $row['disbursement_label'] }}</td>
                                    <td class="px-3 py-2">{{ $row['repayments_made'] }} / {{ $row['repayments_required'] }}</td>
                                    <td class="px-3 py-2">{{ isset($row['outstanding_balance']) ? format_money($row['outstanding_balance']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('admin.group_review.col_member') }}</th>
                        <th class="px-4 py-3">{{ __('admin.group_review.col_id_phone') }}</th>
                        <th class="px-4 py-3">{{ __('admin.group_review.col_amount') }}</th>
                        <th class="px-4 py-3">{{ __('admin.group_review.col_kyc') }}</th>
                        <th class="px-4 py-3">{{ __('admin.group_review.col_crb') }}</th>
                        <th class="px-4 py-3">{{ __('admin.group_review.col_exposure') }}</th>
                        <th class="px-4 py-3">{{ __('admin.group_review.col_review') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($groupReview['members'] as $member)
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <p class="font-medium">{{ $member['name'] }}</p>
                                <p class="text-xs text-gray-500 capitalize">{{ $member['role'] }}</p>
                                <p class="text-xs mt-1 {{ $member['eligible'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $member['status_label'] }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs align-top">
                                <p>{{ $member['customer_number'] ?? '—' }}</p>
                                <p class="text-gray-500">{{ $member['phone'] ?? '—' }}</p>
                                <p class="text-gray-500">{{ $member['national_id'] ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">{{ format_money($member['requested_amount']) }}</td>
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $member['kyc_complete'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $member['kyc_complete'] ? __('admin.group_review.kyc_complete') : __('admin.group_review.kyc_incomplete') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs align-top">
                                <p>{{ $member['crb_score'] ?? '—' }}</p>
                                <p class="text-gray-500">{{ $member['crb_status'] ?? __('admin.group_review.crb_not_checked') }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">{{ format_money($member['existing_exposure']) }}</td>
                            <td class="px-4 py-3 align-top min-w-[16rem]">
                                <form method="POST" action="{{ route('admin.loan-applications.review-group-member', [$record, $member['id']]) }}" class="space-y-2">
                                    @csrf
                                    <select name="underwriting_status" class="w-full rounded-lg border-gray-200 text-xs">
                                        @foreach ($groupReview['statuses'] as $status)
                                            <option value="{{ $status }}" @selected(($member['underwriting_status'] ?? 'pending') === $status)>
                                                {{ __('admin.group_review.underwriting_status.'.$status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <textarea name="underwriting_notes" rows="2" class="w-full rounded-lg border-gray-200 text-xs" placeholder="{{ __('admin.group_review.internal_notes_placeholder') }}">{{ $member['underwriting_notes'] ?? '' }}</textarea>
                                    <textarea name="leader_feedback" rows="2" class="w-full rounded-lg border-gray-200 text-xs" placeholder="{{ __('admin.group_review.leader_notes_placeholder') }}">{{ $member['leader_feedback'] ?? '' }}</textarea>
                                    <button type="submit" class="text-xs font-semibold text-gray-900 underline">{{ __('admin.group_review.save_member_review') }}</button>
                                </form>
                                @if ($member['can_request_replacement'] ?? false)
                                    <form method="POST" action="{{ route('admin.loan-applications.request-group-member-replacement', [$record, $member['id']]) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <textarea name="reason" rows="2" class="w-full rounded-lg border-amber-200 text-xs" placeholder="{{ __('admin.group_review.replacement_reason_placeholder') }}"></textarea>
                                        <button type="submit" class="inline-flex text-xs font-semibold text-amber-800 underline" onclick="return confirm(@js(__('admin.group_review.replacement_confirm')))">
                                            {{ __('admin.group_review.request_replacement') }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.loan-applications.refresh-group-crb', $record) }}">
                @csrf
                <button type="submit" class="inline-flex bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-2 rounded-lg text-sm">
                    {{ __('admin.group_review.refresh_crb') }}
                </button>
            </form>
        </div>

        @if ($groupReview['contract_signatures'] ?? null)
            <div id="group-contract-signatures" class="px-5 py-4 border-t border-gray-100"
                 x-data="{
                    signatures: @js($groupReview['contract_signatures']),
                    polling: null,
                    async refresh() {
                        try {
                            const res = await fetch(@js(route('admin.loan-applications.group-contract-progress', $record)), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                credentials: 'same-origin',
                            });
                            if (! res.ok) return;
                            const data = await res.json();
                            if (data.ok && data.contract_signatures) this.signatures = data.contract_signatures;
                        } catch (e) {}
                    },
                    startPolling() {
                        this.polling = setInterval(() => this.refresh(), 20000);
                    },
                    statusClass(status) {
                        if (status === 'signed') return 'bg-emerald-100 text-emerald-800';
                        if (status === 'declined') return 'bg-red-100 text-red-800';
                        return 'bg-amber-100 text-amber-800';
                    },
                 }"
                 x-init="startPolling()"
                 @destroy.window="if (polling) clearInterval(polling)">
                <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                    <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('admin.group_review.contract_signatures') }}</h4>
                    <span class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('admin.group_review.auto_refresh') }}</span>
                </div>
                <div class="mb-3 text-sm space-y-1">
                    <template x-for="(line, index) in (signatures.summary || [])" :key="'sig-summary-' + index">
                        <p class="font-medium text-gray-800" x-text="line"></p>
                    </template>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ __('admin.group_review.col_member') }}</th>
                                <th class="px-4 py-2">{{ __('admin.group_review.col_amount') }}</th>
                                <th class="px-4 py-2">{{ __('admin.group_review.col_status') }}</th>
                                <th class="px-4 py-2">{{ __('admin.group_review.col_signed_at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="sigMember in (signatures.members || [])" :key="sigMember.id">
                                <tr>
                                    <td class="px-4 py-2">
                                        <p class="font-medium" x-text="sigMember.name"></p>
                                        <p class="text-xs text-gray-500 capitalize" x-text="sigMember.role"></p>
                                    </td>
                                    <td class="px-4 py-2" x-text="new Intl.NumberFormat('en-TZ', { style: 'currency', currency: 'TZS', maximumFractionDigits: 0 }).format(sigMember.requested_amount || 0)"></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium"
                                              :class="statusClass(sigMember.signature_status || 'pending')"
                                              x-text="sigMember.signature_label || sigMember.signature_status"></span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500">
                                        <span x-show="sigMember.signed_at" x-text="new Date(sigMember.signed_at).toLocaleString()"></span>
                                        <span x-show="! sigMember.signed_at">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endif
