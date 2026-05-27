<x-site.borrower-layout title="Make payment — Kopafasta" active="payments">

    <h1 class="text-2xl font-bold mb-1">Make a payment</h1>
    <p class="text-sm text-gray-500 mb-6">Choose any channel, then enter the transaction reference.</p>

    @if ($loans->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <p class="text-gray-500">You have no active loans to repay.</p>
        </div>
    @else
        <div class="grid lg:grid-cols-3 gap-6">

            {{-- Payment instructions --}}
            <div class="lg:col-span-1 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">Payment channels</h2>
                @foreach ([
                    ['M-Pesa', '123456', 'Lipa na M-Pesa → Pay Bill', 'bg-emerald-50 text-emerald-700'],
                    ['Tigo Pesa', '654321', 'Lipa kwa Tigo Pesa', 'bg-sky-50 text-sky-700'],
                    ['Airtel Money', '987654', 'Airtel Money → Pay merchant', 'bg-red-50 text-red-700'],
                    ['Bank (CRDB)', '0150-XXXXX-00', 'A/c: Kopafasta Microfinance Ltd', 'bg-amber-50 text-amber-700'],
                ] as [$name, $till, $note, $cls])
                    <div class="bg-white rounded-2xl border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-1">
                            <p class="font-semibold text-sm">{{ $name }}</p>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded {{ $cls }}">{{ $till }}</span>
                        </div>
                        <p class="text-xs text-gray-500">{{ $note }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Pay form --}}
            <form method="POST" action="{{ route('site.borrower.payments.store') }}" class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
                @csrf
                <h2 class="text-lg font-semibold mb-1">Confirm your payment</h2>
                <p class="text-xs text-gray-500 mb-5">After paying via your chosen channel, fill this form so we can match it to your loan.</p>

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
                        <label class="block text-xs font-medium text-gray-600 mb-2">Channel</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach (['M-Pesa','Tigo Pesa','Airtel Money','Bank (CRDB)'] as $ch)
                                <label class="cursor-pointer">
                                    <input type="radio" name="channel" value="{{ $ch }}" class="sr-only peer" required>
                                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 px-3 py-2 text-center text-xs font-medium transition">{{ $ch }}</div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Amount (TZS)</label>
                            <input type="number" name="amount" min="100" step="100" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Transaction reference</label>
                            <input name="reference" required placeholder="e.g. SHJ5K8GH22" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm uppercase font-mono">
                        </div>
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
