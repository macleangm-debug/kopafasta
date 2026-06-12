<x-site.borrower-layout :title="brand_title('Post-approval fees')" active="loans">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('site.borrower.application', $application) }}" class="text-sm text-amber-700 hover:text-amber-800">&larr; Back to application</a>
            <h1 class="text-2xl font-bold mt-2">Post-approval fees</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $application->product?->name }} · Ref {{ $application->reference ?? $application->id }}</p>
        </div>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden mb-6">
        <ul class="divide-y divide-gray-100">
            @foreach ($application->postApprovalFees as $fee)
                <li class="px-5 py-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="font-medium text-gray-900">{{ $fee->name }}</p>
                        <p class="text-xs text-gray-500">{{ ucfirst($fee->fee_type) }} · {{ $fee->code }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold">{{ format_money($fee->calculated_amount) }}</p>
                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $fee->isPaid() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ ucfirst($fee->status) }}
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="px-5 py-4 bg-gray-50 space-y-2">
            @if ($feeQuote['discount'] > 0)
                <div class="flex items-center justify-between text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span>{{ format_money($feeQuote['base']) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm text-emerald-700">
                    <span>Referral discount</span>
                    <span>- {{ format_money($feeQuote['discount']) }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="font-semibold">Total due</span>
                <span class="text-lg font-bold">{{ format_money($feeQuote['after_discount']) }}</span>
            </div>
        </div>
    </div>

    @if ($application->postApprovalFees->contains(fn ($f) => ! $f->isPaid()))
        <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-5 py-4 mb-6 text-sm text-sky-900">
            <p class="font-semibold mb-2">How to pay</p>
            <p class="text-xs text-sky-800 mb-3">Use reference <span class="font-mono font-bold">{{ $paymentReference }}</span> on all transfers.</p>

            @if (! empty($mobileDetails['number']))
                <div class="mb-3">
                    <p class="font-medium">Mobile money — {{ $mobileDetails['provider'] ?? 'M-Pesa' }}</p>
                    <p class="font-mono">{{ $mobileDetails['number'] }}</p>
                    @if (! empty($mobileDetails['instructions']))
                        <p class="text-xs mt-1">{{ $mobileDetails['instructions'] }}</p>
                    @endif
                </div>
            @endif

            @foreach ($bankAccounts as $acct)
                <div class="mb-2 last:mb-0">
                    <p class="font-medium">{{ $acct['bank'] }}</p>
                    <p class="text-xs">{{ $acct['account_name'] }} · {{ $acct['account_number'] }}@if (! empty($acct['branch'])) · {{ $acct['branch'] }}@endif</p>
                    @if (! empty($acct['instructions']))
                        <p class="text-xs mt-0.5">{{ $acct['instructions'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($wallet->balance > 0 && $application->postApprovalFees->contains(fn ($f) => ! $f->isPaid()))
        <div class="mb-6 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-4 py-4 text-sm text-indigo-900">
            <p>Referral wallet balance: <strong>{{ format_money($wallet->balance) }}</strong>.</p>
            <p class="mt-1 text-xs">You may apply up to {{ rtrim(rtrim(format_number($referralSettings['wallet_max_fee_percent'], 2), '0'), '.') }}% of the fee total from your wallet (max <strong>{{ format_money($maxWalletQuote['wallet_applied']) }}</strong>).</p>
        </div>
    @endif

    @if ($application->postApprovalFees->contains(fn ($f) => ! $f->isPaid()))
        <form method="POST" action="{{ route('site.borrower.application.post-approval-fees.pay', $application) }}" class="space-y-4"
              @submit.prevent="window.confirmForm($el, { title: 'Confirm payment?', message: 'Record that you have paid all post-approval fees.', confirmLabel: 'Confirm payment', confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf
            @if ($wallet->balance > 0 && $maxWalletQuote['wallet_usable'] > 0)
                <label class="flex items-start gap-3 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm cursor-pointer">
                    <input type="checkbox" name="use_wallet" value="1" class="mt-1 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                    <span>
                        Use referral wallet (up to <strong>{{ format_money(min($wallet->balance, $maxWalletQuote['wallet_usable'])) }}</strong>).
                        Estimated cash due after wallet: <strong>{{ format_money($maxWalletQuote['cash_due']) }}</strong>.
                    </span>
                </label>
            @endif
            <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">I have paid these fees</button>
        </form>
    @else
        <p class="text-sm text-emerald-700 font-semibold">All post-approval fees are paid.</p>
    @endif

    </div>
</x-site.borrower-layout>
