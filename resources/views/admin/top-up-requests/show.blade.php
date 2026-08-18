@php
    $loan = $record->loan;
    $customer = $record->customer ?? $loan?->customer;
    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
@endphp

<x-admin.layout
    :title="'Top-up #'.$record->id"
    heading=""
    subheading="">

    <x-admin.letterhead
        kicker="Credit management"
        :title="'Top-up request #'.$record->id"
        :subtitle="($loan?->loan_number ?? '—').' · '.$name">
        <x-slot:actions>
            <a href="{{ route('admin.top-up-requests.index') }}" class="inline-flex items-center text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">All requests</a>
        </x-slot:actions>
    </x-admin.letterhead>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Request details</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Loan</dt>
                        <dd class="font-mono font-semibold">
                            @if ($loan)
                                <a href="{{ route('admin.loans.show', $loan) }}" class="text-brand hover:text-brand-light">{{ $loan->loan_number }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Status</dt>
                        <dd class="font-semibold">{{ ucfirst($record->status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Requested amount</dt>
                        <dd class="font-semibold text-lg">{{ format_money($record->requested_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Current outstanding</dt>
                        <dd>{{ $loan ? format_money($loan->outstanding_balance) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Submitted</dt>
                        <dd>{{ $record->created_at?->format('d M Y H:i') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500 uppercase mb-1">Reason</dt>
                        <dd class="whitespace-pre-line text-gray-700">{{ $record->reason }}</dd>
                    </div>
                    @if ($record->decision_notes)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-gray-500 uppercase mb-1">Decision notes</dt>
                            <dd class="whitespace-pre-line text-gray-700">{{ $record->decision_notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        @if ($record->status === 'pending')
            <div class="space-y-4">
                <form method="post" action="{{ route('admin.top-up-requests.approve', $record) }}" class="bg-white rounded-xl ring-1 ring-emerald-200 p-5 space-y-3"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('admin.confirm.top_up_approve_title')),
                          message: @js(__('admin.confirm.top_up_approve_message')),
                          confirmLabel: @js(__('admin.confirm.top_up_approve_confirm')),
                          confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                          tone: 'confirm',
                      })">
                    @csrf
                    <h3 class="text-sm font-semibold text-emerald-900">Approve top-up</h3>
                    <p class="text-xs text-gray-600">Approval authorises {{ format_money($record->requested_amount) }}. Disburse separately to update the loan balance and schedule.</p>
                    <textarea name="notes" rows="3" placeholder="Optional notes for borrower"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2"></textarea>
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm">Approve</button>
                </form>
                <form method="post" action="{{ route('admin.top-up-requests.reject', $record) }}" class="bg-white rounded-xl ring-1 ring-red-200 p-5 space-y-3"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('admin.confirm.top_up_reject_title')),
                          message: @js(__('admin.confirm.top_up_reject_message')),
                          confirmLabel: @js(__('admin.confirm.top_up_reject_confirm')),
                          confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                          tone: 'warning',
                      })">
                    @csrf
                    <h3 class="text-sm font-semibold text-red-900">Reject request</h3>
                    <textarea name="notes" rows="3" placeholder="Reason for rejection"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2"></textarea>
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm">Reject</button>
                </form>
            </div>
        @elseif ($record->status === 'approved' && ! $record->disbursed_at)
            <div class="space-y-4">
                <form method="post" action="{{ route('admin.top-up-requests.disburse', $record) }}" class="bg-white rounded-xl ring-1 ring-sky-200 p-5 space-y-3"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('admin.confirm.top_up_disburse_title')),
                          message: @js(__('admin.confirm.top_up_disburse_message')),
                          confirmLabel: @js(__('admin.confirm.top_up_disburse_confirm')),
                          confirmClass: 'bg-sky-600 hover:bg-sky-700 text-white',
                          tone: 'warning',
                      })">
                    @csrf
                    <h3 class="text-sm font-semibold text-sky-900">Disburse top-up</h3>
                    <p class="text-xs text-gray-600">Adds {{ format_money($record->requested_amount) }} to the loan and rebuilds remaining instalments.</p>
                    <textarea name="notes" rows="3" placeholder="Optional disbursement notes"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2"></textarea>
                    <button type="submit" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm">Disburse to loan</button>
                </form>
            </div>
        @endif
    </div>
</x-admin.layout>
