<x-admin.layout
    title="Membership Payments"
    heading="Membership Payments"
    subheading="Review and approve bank transfer payments for registration and renewal">

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">
            Borrowers who pay by bank transfer appear here until an admin verifies the transfer.
        </p>
        <a href="{{ route('admin.settings.membership') }}"
           class="text-sm font-semibold text-brand hover:text-brand-light">
            Membership settings
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden mb-8">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Pending verification</h2>
            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                {{ $pending->total() }} pending
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Submitted</th>
                        <th class="px-5 py-3">Customer</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Reference</th>
                        <th class="px-5 py-3">Channel</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pending as $payment)
                        @php
                            $customer = $payment->customer;
                            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                                <p class="font-semibold text-gray-900">{{ format_app_date($payment->created_at) }}</p>
                                <p class="tabular-nums mt-0.5">{{ format_app_datetime($payment->created_at, 'H:i') }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium text-gray-900">{{ $name ?: '—' }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $customer->member_no ?? 'No member no' }}
                                    @if ($customer->phone)
                                        · {{ $customer->phone }}
                                    @endif
                                </div>
                                @if ($customer)
                                    <a href="{{ route('admin.customers.show', $customer) }}"
                                       class="text-xs font-semibold text-brand hover:text-brand-light">
                                        View customer
                                    </a>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded bg-blue-50 text-blue-700 px-2 py-0.5 text-xs font-medium">
                                    {{ $payment->paymentTypeLabel() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 font-medium whitespace-nowrap">
                                {{ format_number((float) $payment->fee_amount, 0) }} TZS
                            </td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $payment->payment_reference }}</td>
                            <td class="px-5 py-3">{{ display_label($payment->channel ?? 'bank', 'channel') }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col items-end gap-2">
                                    <form method="POST"
                                          action="{{ route('admin.membership-payments.approve', $payment) }}"
                                          @submit.prevent="window.confirmForm($el, {
                                              title: @js('Approve this payment?'),
                                              message: @js('Approve this payment and activate membership?'),
                                              confirmLabel: @js('Approve'),
                                              confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                                              tone: 'confirm',
                                          })">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                            Approve
                                        </button>
                                    </form>

                                    <form method="POST"
                                          action="{{ route('admin.membership-payments.reject', $payment) }}"
                                          class="flex flex-col items-end gap-1 w-full max-w-xs"
                                          @submit.prevent="window.confirmForm($el, {
                                              title: @js('Reject this payment?'),
                                              message: @js('Reject this payment?'),
                                              confirmLabel: @js('Reject'),
                                              confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                              tone: 'warning',
                                          })">
                                        @csrf
                                        <input type="text"
                                               name="notes"
                                               placeholder="Rejection reason (optional)"
                                               class="w-full rounded-lg border-gray-200 text-xs focus:border-red-300 focus:ring-red-200">
                                        <button type="submit"
                                                class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-500">
                                No pending bank payments. New submissions appear when borrowers choose bank transfer on membership renewal.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pending->hasPages())
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
                {{ $pending->links() }}
            </div>
        @endif
    </div>

    @if ($recent->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Recently processed</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Reference</th>
                            <th class="px-5 py-3">Outcome</th>
                            <th class="px-5 py-3">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recent as $payment)
                            @php
                                $customer = $payment->customer;
                                $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2 text-xs text-gray-500 whitespace-nowrap">
                                    <p class="font-semibold text-gray-900">{{ format_app_date($payment->updated_at) }}</p>
                                    <p class="tabular-nums mt-0.5">{{ format_app_datetime($payment->updated_at, 'H:i') }}</p>
                                </td>
                                <td class="px-5 py-2">{{ $name ?: '—' }}</td>
                                <td class="px-5 py-2 font-mono text-xs">{{ $payment->payment_reference }}</td>
                                <td class="px-5 py-2">
                                    @if ($payment->event === 'payment_approved')
                                        <span class="rounded bg-emerald-50 text-emerald-700 px-2 py-0.5 text-xs font-medium">Approved</span>
                                    @else
                                        <span class="rounded bg-red-50 text-red-700 px-2 py-0.5 text-xs font-medium">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2 text-xs text-gray-600">{{ optional($payment->actor)->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-admin.layout>
