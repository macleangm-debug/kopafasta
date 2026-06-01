<x-site.borrower-layout :title="brand_title('Make payment')" active="payments">

    <h1 class="text-2xl font-bold mb-1">Make a payment</h1>
    <p class="text-sm text-gray-500 mb-6">Choose any channel, then enter the transaction reference.</p>

    @if ($loans->isEmpty())
        <x-site.empty-state
            icon="💳"
            title="No payments available."
            description="Once you receive a loan, payment schedules will appear here."
            action-label="View loan products"
            :action-url="route('site.borrower.dashboard')"
        />
    @else
        @php $paymentThreshold = config('payments.mobile_money_threshold', 3_000_000); @endphp
        <div class="grid lg:grid-cols-3 gap-6" x-data="{
            amount: {{ (int) old('amount', 0) }},
            threshold: {{ $paymentThreshold }},
            get mobileAllowed() { return this.amount <= this.threshold; },
            get channels() {
                return this.mobileAllowed
                    ? ['M-Pesa','Tigo Pesa','Airtel Money','Bank transfer']
                    : ['Bank transfer'];
            }
        }">

            <div class="lg:col-span-1 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">Payment channels</h2>
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-xs text-amber-900">
                    Mobile money is available for amounts up to TZS {{ number_format($paymentThreshold) }}.
                    Above that threshold, bank transfer only.
                </div>
                <template x-for="channel in channels" :key="channel">
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <p class="font-semibold text-sm" x-text="channel"></p>
                        <p class="text-xs text-gray-500 mt-1" x-text="channel === 'Bank transfer' ? 'Use bank transfer for high-value payments.' : 'Pay via your mobile wallet, then submit the reference.'"></p>
                    </div>
                </template>
            </div>

            <form method="POST" action="{{ route('site.borrower.payments.store') }}" class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6"
                  @submit.prevent="window.confirmForm($el, { title: 'Submit this payment?', message: 'We will match your transaction reference to your loan once verified.', confirmLabel: 'Submit payment', confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
                @csrf
                <h2 class="text-lg font-semibold mb-1">Confirm your payment</h2>
                <p class="text-xs text-gray-500 mb-5">The system automatically determines allowed payment methods based on amount.</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Loan</label>
                        <select name="loan_id" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                            @foreach ($loans as $loan)
                                <option value="{{ $loan->id }}">{{ $loan->loan_number }} — TZS {{ number_format($loan->outstanding_balance) }} outstanding</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Amount (TZS)</label>
                        <input type="number" name="amount" min="100" step="100" required x-model.number="amount"
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                        <p class="text-xs mt-2" :class="mobileAllowed ? 'text-emerald-700' : 'text-amber-700'"
                           x-text="mobileAllowed ? 'Mobile money and bank transfer available.' : 'Amount exceeds TZS {{ number_format($paymentThreshold) }} — bank transfer only.'"></p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-2">Channel</label>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="channel in channels" :key="channel">
                                <label class="cursor-pointer">
                                    <input type="radio" name="channel" :value="channel" class="sr-only peer" required>
                                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 px-3 py-2 text-center text-xs font-medium transition" x-text="channel"></div>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Transaction reference</label>
                        <input name="reference" required placeholder="e.g. SHJ5K8GH22" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm uppercase font-mono">
                    </div>
                </div>

                <button class="mt-6 w-full sm:w-auto bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm inline-flex items-center gap-2">
                    Submit payment →
                </button>
                <p class="text-[11px] text-gray-400 mt-3">We will mark the payment as confirmed once we verify the reference (usually within minutes).</p>
            </form>
        </div>
    @endif

</x-site.borrower-layout>
