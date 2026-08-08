@php
    $loan = $record->loan;
    $customer = $record->customer ?? $loan?->customer;
    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
@endphp

<x-admin.layout
    :title="'Restructure #'.$record->id"
    :heading="'Restructure request #'.$record->id"
    :subheading="$loan?->loan_number.' · '.$name">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4">
        <a href="{{ route('admin.restructure-requests.index') }}" class="text-sm font-semibold text-brand hover:text-brand-light">← Back to restructure requests</a>
    </div>

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
                        <dt class="text-xs text-gray-500 uppercase">Type</dt>
                        <dd>{{ str_replace('_', ' ', $record->restructure_type ?? '—') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Requested term</dt>
                        <dd>{{ $record->new_tenure_months ? $record->new_tenure_months.' months' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Outstanding</dt>
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
                <form method="post" action="{{ route('admin.restructure-requests.approve', $record) }}" class="bg-white rounded-xl ring-1 ring-emerald-200 p-5 space-y-3"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('admin.confirm.restructure_approve_title')),
                          message: @js(__('admin.confirm.restructure_approve_message')),
                          confirmLabel: @js(__('admin.confirm.restructure_approve_confirm')),
                          confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                          tone: 'confirm',
                      })">
                    @csrf
                    <h3 class="text-sm font-semibold text-emerald-900">Approve request</h3>
                    <textarea name="notes" rows="3" placeholder="Optional notes for borrower"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2"></textarea>
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm">Approve</button>
                </form>
                <form method="post" action="{{ route('admin.restructure-requests.reject', $record) }}" class="bg-white rounded-xl ring-1 ring-red-200 p-5 space-y-3"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('admin.confirm.restructure_reject_title')),
                          message: @js(__('admin.confirm.restructure_reject_message')),
                          confirmLabel: @js(__('admin.confirm.restructure_reject_confirm')),
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
        @endif
    </div>
</x-admin.layout>
