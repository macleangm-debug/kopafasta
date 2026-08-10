@php
    $loan = $arrearCase->loan;
    $customer = $loan?->customer;
    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
@endphp

<x-admin.layout
    :title="'Collection case #'.$arrearCase->id"
    :heading="'Collection case #'.$arrearCase->id"
    :subheading="($loan?->loan_number ?? '—').' · '.$name">

<div class="mb-4 flex flex-wrap gap-3">
        <a href="{{ route('admin.arrear-cases.index') }}" class="text-sm font-semibold text-brand hover:text-brand-light">← All collection cases</a>
        @if ($loan)
            <a href="{{ route('admin.loans.show', $loan) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-800">Loan profile</a>
        @endif
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Case summary</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Loan</dt>
                        <dd class="font-mono font-semibold">
                            @if ($loan)
                                <a href="{{ route('admin.loans.show', $loan) }}" class="text-amber-700">{{ $loan->loan_number }}</a>
                            @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Customer</dt>
                        <dd class="font-semibold">{{ $name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Days past due</dt>
                        <dd class="font-semibold text-red-700">{{ $arrearCase->days_past_due }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Amount in arrears</dt>
                        <dd class="font-semibold text-red-700">{{ format_money($arrearCase->amount_in_arrears) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Penalty</dt>
                        <dd class="font-semibold">{{ format_money($arrearCase->penalty_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Last follow-up</dt>
                        <dd>{{ optional($arrearCase->last_follow_up_at)->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($servicing)
                    <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-lg bg-gray-50 px-3 py-2">
                            <p class="text-[10px] uppercase text-gray-500">Outstanding</p>
                            <p class="font-bold">{{ format_money($servicing['outstanding_balance']) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-3 py-2">
                            <p class="text-[10px] uppercase text-gray-500">Next due</p>
                            <p class="font-bold">{{ $servicing['next_due_date']?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-3 py-2">
                            <p class="text-[10px] uppercase text-gray-500">Overdue installments</p>
                            <p class="font-bold">{{ $servicing['overdue_installments'] ?? 0 }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Collection actions</h2>
                @if ($arrearCase->actions->isEmpty())
                    <p class="text-sm text-gray-500">No actions logged yet.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($arrearCase->actions as $action)
                            <li class="py-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-900">{{ str_replace('_', ' ', $action->action_type) }}</span>
                                    <span class="text-xs text-gray-500">{{ optional($action->performed_at)->format('d M Y H:i') }}</span>
                                </div>
                                @if ($action->result)
                                    <p class="text-xs text-gray-600 mt-0.5">Result: {{ str_replace('_', ' ', $action->result) }}</p>
                                @endif
                                @if ($action->notes)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $action->notes }}</p>
                                @endif
                                @if ($action->performer)
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $action->performer->name }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Update case</h2>
                <form method="POST" action="{{ route('admin.arrear-cases.update', $arrearCase) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full rounded-lg border-gray-300 text-sm">
                            @foreach (['open', 'escalated', 'resolved'] as $s)
                                <option value="{{ $s }}" @selected($arrearCase->status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Assigned collector</label>
                        <select name="assigned_to" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">— Unassigned —</option>
                            @foreach ($collectors as $user)
                                <option value="{{ $user->id }}" @selected((int) $arrearCase->assigned_to === (int) $user->id)>
                                    {{ $user->name }} ({{ $user->role }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full text-sm font-semibold text-white bg-brand hover:bg-brand-light px-3 py-2 rounded-lg">
                        Save changes
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Log action</h2>
                <form method="POST" action="{{ route('admin.arrear-cases.actions', $arrearCase) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Action type</label>
                        <select name="action_type" required class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="phone_call">Phone call</option>
                            <option value="sms_reminder">SMS reminder</option>
                            <option value="email_reminder">Email reminder</option>
                            <option value="field_visit">Field visit</option>
                            <option value="promise_to_pay">Promise to pay</option>
                            <option value="escalation">Escalation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Result</label>
                        <select name="result" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">—</option>
                            <option value="contacted">Contacted</option>
                            <option value="no_answer">No answer</option>
                            <option value="promised_payment">Promised payment</option>
                            <option value="dispute">Dispute</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                        <textarea name="notes" rows="3" maxlength="2000" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                    </div>
                    <button type="submit" class="w-full text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-2 rounded-lg">
                        Log action
                    </button>
                </form>
            </div>

            @include('admin.arrear-cases._recovery-partners')

            @include('admin.arrear-cases._auction-settlement')

            @if ($loan && ! empty($approvalRequired) && ! empty($canRecommendWriteOff))
                <div class="bg-white rounded-xl ring-1 ring-red-200 p-6">
                    <h2 class="text-sm font-semibold text-red-900 mb-4">Recommend write-off</h2>
                    <form method="POST" action="{{ route('admin.arrear-cases.write-off-requests.store', $arrearCase) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Amount (TZS)</label>
                            <input type="number" step="0.01" name="amount" value="{{ old('amount', (float) $loan->outstanding_balance) }}"
                                   class="w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Reason</label>
                            <textarea name="reason" rows="3" required maxlength="2000" class="w-full rounded-lg border-gray-300 text-sm">{{ old('reason') }}</textarea>
                        </div>
                        <button type="submit" class="w-full text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-3 py-2 rounded-lg">
                            Recommend write-off
                        </button>
                    </form>
                </div>
            @endif

            @if ($loan)
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Quick links</h2>
                    <div class="space-y-2 text-sm">
                        <a href="{{ route('admin.repayments.create', ['loan_id' => $loan->id]) }}" class="block text-emerald-700 font-semibold hover:underline">Record repayment</a>
                        <a href="{{ route('admin.restructure-requests.index') }}" class="block text-amber-700 font-semibold hover:underline">Restructure requests</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin.layout>
