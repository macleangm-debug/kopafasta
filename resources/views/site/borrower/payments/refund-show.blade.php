<x-site.borrower-layout :title="brand_title('Refund')" active="payments">

    <div class="mb-5">
        <a href="{{ route('site.borrower.payments') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← Payments</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-8">
        <p class="text-xs uppercase tracking-wide text-gray-500">Refund</p>
        <p class="text-2xl font-bold mt-1">{{ format_money($refund->amount) }}</p>
        <p class="text-sm text-gray-500 mt-1 font-mono">{{ $refund->reference }}</p>
        <p class="text-sm text-gray-600 mt-2">Loan {{ $refund->loan?->loan_number ?? '—' }}</p>

        @php
            $badge = match ($refund->status) {
                'pending', 'awaiting_payout' => 'bg-amber-100 text-amber-800',
                'paid' => 'bg-emerald-100 text-emerald-800',
                default => 'bg-gray-100 text-gray-600',
            };
        @endphp
        <p class="mt-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ str_replace('_', ' ', $refund->status) }}</span></p>

        @if ($refund->status === 'paid')
            <p class="text-sm text-gray-600 mt-4">Paid on {{ $refund->paid_at?->format('d M Y') }}. Reference: <span class="font-mono">{{ $refund->payment_reference }}</span></p>
        @elseif ($refund->needsPayoutDetails())
            <form method="POST" action="{{ route('site.borrower.refunds.details', $refund) }}" class="space-y-4 border-t border-gray-100 pt-6 mt-6" x-data="{ channel: '{{ old('payout_channel', $refund->payout_channel ?? 'mobile_money') }}' }">
                @csrf
                <p class="text-sm text-gray-700">Submit where we should send this refund.</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="payout_channel" value="mobile_money" x-model="channel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 p-3 text-sm font-medium">Mobile money</div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="payout_channel" value="bank" x-model="channel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 p-3 text-sm font-medium">Bank</div>
                    </label>
                </div>
                <div x-show="channel === 'mobile_money'" class="space-y-2">
                    <input type="text" name="payout_phone" value="{{ old('payout_phone', $refund->payout_phone ?? $customer->phone) }}" required placeholder="Phone number" class="w-full rounded-lg border-gray-300 text-sm">
                    <input type="text" name="payout_provider" value="{{ old('payout_provider', $refund->payout_provider) }}" placeholder="Provider (M-Pesa, Tigo Pesa…)" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div x-show="channel === 'bank'" x-cloak class="space-y-2">
                    <input type="text" name="payout_account_name" value="{{ old('payout_account_name', $refund->payout_account_name) }}" placeholder="Account name" class="w-full rounded-lg border-gray-300 text-sm">
                    <input type="text" name="payout_account_number" value="{{ old('payout_account_number', $refund->payout_account_number) }}" placeholder="Account number" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <button class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Submit payout details</button>
            </form>
        @elseif ($refund->status === 'awaiting_payout')
            <p class="text-sm text-gray-600 mt-4">Payout details received. We are processing your refund.</p>
        @endif
    </div>

</x-site.borrower-layout>
