@if ($groupReview ?? null)
    <div id="review-group"
         class="rounded-2xl ring-1 ring-brand/15 bg-white overflow-hidden shadow-sm"
         x-data="{ groupPanel: @js(request('m') ? 'members' : 'overview') }">
        <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white">
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">Group loan</p>
            <div class="flex flex-wrap items-start justify-between gap-3 mt-0.5">
                <div>
                    <h3 class="text-base font-bold text-gray-900">{{ __('admin.group_review.title') }}</h3>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $groupReview['name'] }} · {{ $groupReview['group_number'] }}</p>
                </div>
                @if ($groupReview['application_status'] ?? null)
                    @php
                        $appStatus = $groupReview['application_status'];
                        $statusTone = match ($appStatus['tone'] ?? 'gray') {
                            'emerald' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                            'amber'   => 'bg-amber-100 text-amber-800 ring-amber-200',
                            'blue'    => 'bg-sky-100 text-sky-800 ring-sky-200',
                            'red'     => 'bg-rose-100 text-rose-800 ring-rose-200',
                            default   => 'bg-gray-100 text-gray-700 ring-gray-200',
                        };
                    @endphp
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $statusTone }}">
                        {{ $appStatus['label'] }}
                    </span>
                @endif
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5" role="tablist" aria-label="Group review panels">
                @foreach ([
                    'overview' => 'Overview',
                    'members' => 'Members',
                    'feedback' => 'Feedback',
                    'signatures' => 'Signatures',
                ] as $gKey => $gLabel)
                    <button type="button"
                            role="tab"
                            @click="groupPanel = @js($gKey)"
                            :aria-selected="(groupPanel === @js($gKey)).toString()"
                            :class="groupPanel === @js($gKey)
                                ? 'bg-brand text-white ring-brand shadow-sm'
                                : 'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40'"
                            class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ring-1 transition">
                        {{ $gLabel }}
                    </button>
                @endforeach
            </div>
            <p class="text-[11px] text-gray-500 mt-2">
                Subject chips at the top (Leader / Member) are separate checklists — marking Pass/Fail on one person never writes onto another.
            </p>
        </div>

        <div x-show="groupPanel === 'overview'" role="tabpanel" class="space-y-0">
        @if ($groupReview['scoring'] ?? null)
            @php $scoring = $groupReview['scoring']; @endphp
            <div class="px-5 py-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm border-b border-gray-100">
                <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-3 py-2.5">
                    <span class="text-brand block text-[10px] uppercase tracking-widest font-semibold">{{ __('admin.group_review.scoring.completion') }}</span>
                    <span class="font-bold text-gray-900 text-lg tabular-nums">{{ number_format($scoring['member_completion_percent'] ?? 0, 1) }}%</span>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                    <span class="text-gray-500 block text-[10px] uppercase tracking-widest font-semibold">{{ __('admin.group_review.scoring.avg_credit') }}</span>
                    <span class="font-bold text-gray-900 text-lg tabular-nums">{{ isset($scoring['average_credit_score']) ? number_format($scoring['average_credit_score'], 0) : '—' }}</span>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                    <span class="text-gray-500 block text-[10px] uppercase tracking-widest font-semibold">{{ __('admin.group_review.scoring.avg_income') }}</span>
                    <span class="font-bold text-gray-900 text-lg tabular-nums">{{ isset($scoring['average_income']) ? format_money($scoring['average_income']) : '—' }}</span>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                    <span class="text-gray-500 block text-[10px] uppercase tracking-widest font-semibold">{{ __('admin.group_review.scoring.risk_score') }}</span>
                    <span class="font-bold text-gray-900 text-lg tabular-nums">
                        {{ $scoring['group_risk_score'] ?? '—' }}
                        @if (! empty($scoring['risk_band']))
                            <span class="text-xs font-medium text-gray-500">({{ __('admin.group_review.scoring.risk_band.'.$scoring['risk_band']) }})</span>
                        @endif
                    </span>
                </div>
            </div>
        @endif
        <div class="px-5 py-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm border-b border-gray-100">
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5"><span class="text-gray-500 block text-[10px] uppercase tracking-widest font-semibold">{{ __('admin.group_review.leader') }}</span><span class="font-semibold text-gray-900">{{ $groupReview['leader'] ?? '—' }}</span></div>
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5"><span class="text-gray-500 block text-[10px] uppercase tracking-widest font-semibold">{{ __('admin.group_review.members') }}</span><span class="font-semibold text-gray-900">{{ $groupReview['member_count'] }} / {{ $groupReview['target_member_count'] }}</span></div>
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5"><span class="text-gray-500 block text-[10px] uppercase tracking-widest font-semibold">{{ __('admin.group_review.per_member') }}</span><span class="font-semibold text-gray-900">{{ format_money($groupReview['amount_per_member']) }}</span></div>
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-2.5"><span class="text-gray-500 block text-[10px] uppercase tracking-widest font-semibold">{{ __('admin.group_review.total_amount') }}</span><span class="font-semibold text-gray-900">{{ format_money($groupReview['total_amount']) }}</span></div>
        </div>

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

        <div class="px-5 py-4 border-t border-gray-100">
            <p class="text-xs text-gray-500">{{ __('admin.group_review.automated_only_hint') }}</p>
        </div>

        </div>

        <div x-show="groupPanel === 'members'" x-cloak role="tabpanel">
        <div class="px-5 py-4 border-b border-gray-100 space-y-4">
            @php
                $members = collect($groupReview['members'] ?? []);
                $statusCounts = [
                    'pending' => $members->where('underwriting_status', 'pending')->count(),
                    'approved' => $members->where('underwriting_status', 'approved')->count(),
                    'flagged' => $members->where('underwriting_status', 'flagged')->count(),
                    'rejected' => $members->where('underwriting_status', 'rejected')->count(),
                    'replacement_requested' => $members->where('underwriting_status', 'replacement_requested')->count(),
                ];
                $selectedMemberId = (int) request('m', 0);
                $selectedMember = $members->first(fn ($row) => (int) ($row['id'] ?? 0) === $selectedMemberId)
                    ?? $members->first(fn ($row) => ($row['underwriting_status'] ?? 'pending') !== 'approved')
                    ?? $members->first();
                $memberUrl = function (int $memberId) use ($record) {
                    return route('admin.loan-applications.show', [
                        'loan_application' => $record,
                        'person' => 'borrower',
                        'tab' => 'group',
                        'm' => $memberId,
                    ]).'#borrower-file';
                };
            @endphp

            <div class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-amber-50 text-amber-900 ring-1 ring-amber-200 px-2.5 py-1 font-semibold">
                    {{ __('admin.group_review.underwriting_status.pending') }} {{ $statusCounts['pending'] }}
                </span>
                <span class="rounded-full bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200 px-2.5 py-1 font-semibold">
                    {{ __('admin.group_review.underwriting_status.approved') }} {{ $statusCounts['approved'] }}
                </span>
                <span class="rounded-full bg-sky-50 text-sky-900 ring-1 ring-sky-200 px-2.5 py-1 font-semibold">
                    {{ __('admin.group_review.underwriting_status.flagged') }} {{ $statusCounts['flagged'] }}
                </span>
                <span class="rounded-full bg-rose-50 text-rose-900 ring-1 ring-rose-200 px-2.5 py-1 font-semibold">
                    {{ __('admin.group_review.underwriting_status.rejected') }} {{ $statusCounts['rejected'] }}
                </span>
                @if ($statusCounts['replacement_requested'] > 0)
                    <span class="rounded-full bg-orange-50 text-orange-900 ring-1 ring-orange-200 px-2.5 py-1 font-semibold">
                        {{ __('admin.group_review.underwriting_status.replacement_requested') }} {{ $statusCounts['replacement_requested'] }}
                    </span>
                @endif
            </div>

            <p class="text-xs text-gray-500">{{ __('admin.group_review.roster_hint') }}</p>

            <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2">{{ __('admin.group_review.col_member') }}</th>
                            <th class="px-3 py-2">{{ __('admin.group_review.col_kyc') }}</th>
                            <th class="px-3 py-2">{{ __('admin.group_review.col_crb') }}</th>
                            <th class="px-3 py-2">{{ __('admin.group_review.col_status') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($members as $member)
                            @php $isSelected = $selectedMember && (int) $selectedMember['id'] === (int) $member['id']; @endphp
                            <tr @class(['bg-brand-muted/30' => $isSelected])>
                                <td class="px-3 py-2.5">
                                    <p class="font-medium text-gray-900">{{ $member['name'] }}</p>
                                    <p class="text-[11px] text-gray-500 capitalize">{{ $member['role'] }} · {{ format_money($member['requested_amount']) }}</p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium {{ $member['kyc_complete'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $member['kyc_complete'] ? __('admin.group_review.kyc_complete') : __('admin.group_review.kyc_incomplete') }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    <p>{{ $member['crb_score'] ?? '—' }}</p>
                                    <p class="text-gray-500">{{ $member['crb_status'] ?? __('admin.group_review.crb_not_checked') }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-xs font-semibold capitalize">
                                    {{ __('admin.group_review.underwriting_status.'.($member['underwriting_status'] ?? 'pending')) }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <a href="{{ $memberUrl((int) $member['id']) }}"
                                       class="text-xs font-semibold text-brand hover:underline">
                                        {{ $isSelected ? __('admin.group_review.reviewing') : __('admin.group_review.open_member') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($selectedMember)
                <div class="rounded-2xl ring-1 ring-brand/15 bg-white p-4 sm:p-5 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('admin.group_review.selected_member') }}</p>
                            <h4 class="text-base font-bold text-gray-900 mt-0.5">{{ $selectedMember['name'] }}</h4>
                            <p class="text-xs text-gray-500 mt-0.5 capitalize">
                                {{ $selectedMember['role'] }}
                                · {{ $selectedMember['customer_number'] ?? '—' }}
                                · {{ $selectedMember['phone'] ?? '—' }}
                            </p>
                        </div>
                        <div class="text-right text-xs text-gray-600 space-y-1">
                            <p>{{ __('admin.group_review.col_amount') }}: <span class="font-semibold text-gray-900">{{ format_money($selectedMember['requested_amount']) }}</span></p>
                            <p>{{ __('admin.group_review.col_exposure') }}: <span class="font-semibold text-gray-900">{{ format_money($selectedMember['existing_exposure']) }}</span></p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.loan-applications.review-group-member', [$record, $selectedMember['id']]) }}" class="grid sm:grid-cols-2 gap-3">
                        @csrf
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">{{ __('admin.group_review.col_review') }}</label>
                            <select name="underwriting_status" class="w-full rounded-xl border-gray-200 text-sm">
                                @foreach ($groupReview['statuses'] as $status)
                                    <option value="{{ $status }}" @selected(($selectedMember['underwriting_status'] ?? 'pending') === $status)>
                                        {{ __('admin.group_review.underwriting_status.'.$status) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">{{ __('admin.group_review.internal_notes_placeholder') }}</label>
                            <textarea name="underwriting_notes" rows="3" class="w-full rounded-xl border-gray-200 text-sm" placeholder="{{ __('admin.group_review.internal_notes_placeholder') }}">{{ $selectedMember['underwriting_notes'] ?? '' }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-gray-500 mb-1">{{ __('admin.group_review.leader_notes_placeholder') }}</label>
                            <textarea name="leader_feedback" rows="3" class="w-full rounded-xl border-gray-200 text-sm" placeholder="{{ __('admin.group_review.leader_notes_placeholder') }}">{{ $selectedMember['leader_feedback'] ?? '' }}</textarea>
                        </div>
                        <div class="sm:col-span-2 flex flex-wrap gap-2">
                            <button type="submit" class="inline-flex bg-brand text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-brand-light">
                                {{ __('admin.group_review.save_member_review') }}
                            </button>
                        </div>
                    </form>

                    @if ($selectedMember['can_request_replacement'] ?? false)
                        <form method="POST" action="{{ route('admin.loan-applications.request-group-member-replacement', [$record, $selectedMember['id']]) }}"
                              class="space-y-2 border-t border-gray-100 pt-4"
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('admin.group_review.request_replacement')),
                                  message: @js(__('admin.group_review.replacement_confirm')),
                                  confirmLabel: @js(__('admin.group_review.request_replacement')),
                                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                  tone: 'warning',
                              })">
                            @csrf
                            <textarea name="reason" rows="2" class="w-full rounded-xl border-amber-200 text-sm" placeholder="{{ __('admin.group_review.replacement_reason_placeholder') }}"></textarea>
                            <button type="submit" class="inline-flex text-sm font-semibold text-amber-800 underline">
                                {{ __('admin.group_review.request_replacement') }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        </div>

        <div x-show="groupPanel === 'feedback'" x-cloak role="tabpanel">
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
            <button type="submit" class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">{{ __('admin.group_review.save_group_feedback') }}</button>
        </form>

        </div>

        <div x-show="groupPanel === 'signatures'" x-cloak role="tabpanel">
        @if ($groupReview['membership_signatures'] ?? null)
            @php $membershipSigs = $groupReview['membership_signatures']; @endphp
            <div class="px-5 py-4 border-t border-gray-100 space-y-4"
                 x-data="{
                    slide: 0,
                    total: {{ max(1, count($membershipSigs['members'] ?? [])) }},
                    next() { this.slide = (this.slide + 1) % this.total },
                    prev() { this.slide = (this.slide - 1 + this.total) % this.total },
                 }">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('admin.group_review.membership_signatures') }}</h4>
                        <p class="text-sm text-gray-600 mt-1">{{ __('admin.group_review.membership_signatures_hint') }}</p>
                    </div>
                    <p class="text-xs font-semibold tabular-nums {{ ($membershipSigs['all_signed'] ?? false) ? 'text-emerald-700' : 'text-amber-800' }}">
                        {{ __('admin.group_review.membership_signatures_count', [
                            'signed' => $membershipSigs['signed_count'] ?? 0,
                            'total' => $membershipSigs['total'] ?? 0,
                        ]) }}
                    </p>
                </div>

                <div class="relative">
                    @foreach ($membershipSigs['members'] ?? [] as $index => $sigMember)
                        <div x-show="slide === {{ $index }}" x-cloak class="rounded-2xl ring-1 ring-gray-200 bg-white overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 truncate">{{ $sigMember['name'] }}</p>
                                    <p class="text-[11px] text-gray-500 capitalize mt-0.5">{{ $sigMember['role'] ?? 'member' }}</p>
                                </div>
                                <span @class([
                                    'inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold',
                                    'bg-emerald-100 text-emerald-800' => ! empty($sigMember['signed']),
                                    'bg-amber-100 text-amber-900' => empty($sigMember['signed']),
                                ])>
                                    {{ $sigMember['status_label'] }}
                                </span>
                            </div>
                            <div class="px-4 py-6 min-h-[9rem] grid place-items-center bg-slate-50">
                                @if (! empty($sigMember['signature_data']))
                                    <img src="{{ $sigMember['signature_data'] }}" alt="" class="max-h-28 w-auto max-w-full object-contain">
                                    @if (! empty($sigMember['signed_at']))
                                        <p class="mt-3 text-[11px] text-gray-500">
                                            {{ __('admin.group_review.col_signed_at') }}:
                                            {{ \Illuminate\Support\Carbon::parse($sigMember['signed_at'])->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                                        </p>
                                    @endif
                                @else
                                    <p class="text-sm font-semibold text-amber-900">{{ __('admin.group_review.membership_signature_waiting') }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (count($membershipSigs['members'] ?? []) > 1)
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" @click="prev()" class="text-xs font-semibold text-brand hover:underline">← {{ __('admin.group_review.signature_prev') }}</button>
                        <div class="flex gap-1.5">
                            @foreach ($membershipSigs['members'] ?? [] as $index => $sigMember)
                                <button type="button" @click="slide = {{ $index }}" class="size-2 rounded-full"
                                        :class="slide === {{ $index }} ? 'bg-brand' : 'bg-gray-300'"></button>
                            @endforeach
                        </div>
                        <button type="button" @click="next()" class="text-xs font-semibold text-brand hover:underline">{{ __('admin.group_review.signature_next') }} →</button>
                    </div>
                @endif
            </div>
        @endif

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
    </div>
@endif
