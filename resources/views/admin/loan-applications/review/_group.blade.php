@if ($groupReview ?? null)
    <div id="review-group" class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">Group loan review</h3>
            <p class="text-sm text-gray-500 mt-1">{{ $groupReview['name'] }} · {{ $groupReview['group_number'] }}</p>
        </div>
        <div class="px-5 py-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm border-b border-gray-100">
            <div><span class="text-gray-500 block">Leader</span><span class="font-semibold">{{ $groupReview['leader'] ?? '—' }}</span></div>
            <div><span class="text-gray-500 block">Members</span><span class="font-semibold">{{ $groupReview['member_count'] }} / {{ $groupReview['target_member_count'] }}</span></div>
            <div><span class="text-gray-500 block">Per member</span><span class="font-semibold">{{ format_money($groupReview['amount_per_member']) }}</span></div>
            <div><span class="text-gray-500 block">Total group amount</span><span class="font-semibold">{{ format_money($groupReview['total_amount']) }}</span></div>
        </div>

        @if (filled($groupReview['leader_feedback'] ?? null))
            <div class="px-5 py-4 border-b border-gray-100 bg-amber-50 text-sm">
                <p class="text-xs uppercase tracking-widest text-amber-800 font-semibold mb-1">Leader-visible group feedback</p>
                <p class="text-gray-800 whitespace-pre-wrap">{{ $groupReview['leader_feedback'] }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.loan-applications.group-feedback', $record) }}" class="px-5 py-4 border-b border-gray-100 space-y-3">
            @csrf
            <label class="block text-xs font-semibold uppercase tracking-widest text-gray-500">Feedback visible to group leader</label>
            <textarea name="leader_feedback" rows="3" class="w-full rounded-lg border-gray-200 text-sm" placeholder="Overall group application feedback for the leader…">{{ old('leader_feedback', $groupReview['leader_feedback'] ?? '') }}</textarea>
            <button type="submit" class="inline-flex bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-2 rounded-lg text-sm">Save group feedback</button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Member</th>
                        <th class="px-4 py-3">ID / phone</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">KYC</th>
                        <th class="px-4 py-3">CRB</th>
                        <th class="px-4 py-3">Exposure</th>
                        <th class="px-4 py-3">Review</th>
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
                                    {{ $member['kyc_complete'] ? 'Complete' : 'Incomplete' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs align-top">
                                <p>{{ $member['crb_score'] ?? '—' }}</p>
                                <p class="text-gray-500">{{ $member['crb_status'] ?? 'Not checked' }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">{{ format_money($member['existing_exposure']) }}</td>
                            <td class="px-4 py-3 align-top min-w-[16rem]">
                                <form method="POST" action="{{ route('admin.loan-applications.review-group-member', [$record, $member['id']]) }}" class="space-y-2">
                                    @csrf
                                    <select name="underwriting_status" class="w-full rounded-lg border-gray-200 text-xs">
                                        @foreach ($groupReview['statuses'] as $status)
                                            <option value="{{ $status }}" @selected(($member['underwriting_status'] ?? 'pending') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="underwriting_notes" rows="2" class="w-full rounded-lg border-gray-200 text-xs" placeholder="Internal notes">{{ $member['underwriting_notes'] ?? '' }}</textarea>
                                    <textarea name="leader_feedback" rows="2" class="w-full rounded-lg border-gray-200 text-xs" placeholder="Feedback visible to leader">{{ $member['leader_feedback'] ?? '' }}</textarea>
                                    <button type="submit" class="text-xs font-semibold text-gray-900 underline">Save member review</button>
                                </form>
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
                    Refresh CRB for all members
                </button>
            </form>
        </div>
    </div>
@endif
