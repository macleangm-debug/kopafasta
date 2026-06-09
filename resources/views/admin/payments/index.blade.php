<x-admin.layout
    title="Payments"
    heading="Payment verification"
    subheading="Review, verify and reconcile borrower payments">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ([
            'pending' => $counts['pending'].' pending',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'all' => 'All',
        ] as $key => $label)
            <a href="{{ route('admin.payments.index', ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $key ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
        <a href="{{ route('admin.settings.payment-accounts') }}"
           class="ml-auto text-sm font-semibold text-amber-700 hover:text-amber-800 self-center">
            Payment account settings
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Reference</th>
                        <th class="px-5 py-3">Customer</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Method</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($payments as $payment)
                        @php
                            $customer = $payment->customer;
                            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 font-mono text-xs font-semibold">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="text-indigo-600 hover:text-indigo-700">
                                    {{ $payment->reference }}
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $name ?: '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $customer->customer_number ?? '' }}</div>
                            </td>
                            <td class="px-5 py-3">{{ $payment->typeLabel() }}</td>
                            <td class="px-5 py-3 font-medium whitespace-nowrap">{{ format_money($payment->amount) }}</td>
                            <td class="px-5 py-3">{{ $payment->methodShortLabel() }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $badge = match ($payment->status) {
                                        'verified', 'paid' => 'bg-emerald-50 text-emerald-700',
                                        'rejected' => 'bg-red-50 text-red-700',
                                        'clarification_requested' => 'bg-sky-50 text-sky-700',
                                        default => 'bg-amber-50 text-amber-800',
                                    };
                                @endphp
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                    {{ $payment->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ $payment->created_at?->format('d-M-Y') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.payments.show', $payment) }}"
                                   class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                                    Review →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-gray-500">
                                No payments in this view.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payments->hasPages())
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</x-admin.layout>
