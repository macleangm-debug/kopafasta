<x-site.borrower-layout title="Renew membership — Kopafasta" active="membership">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">Renewal</p>
            <h1 class="text-2xl sm:text-3xl font-bold">Renew your KopaFasta membership</h1>
            <p class="text-sm text-gray-500 mt-1">Pay the annual renewal fee to extend access for another {{ $config['duration_days'] }} days.</p>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-6 shadow-lg mb-6">
            <p class="text-[10px] uppercase tracking-widest text-white/80">Renewal fee</p>
            <p class="mt-1 text-3xl font-extrabold">{{ $config['currency'] }} {{ number_format((float) $config['renewal_fee']) }}</p>
            <p class="mt-2 text-xs text-white/90">Valid {{ $config['duration_days'] }} days from current expiry.</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('site.membership.renew.post') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Payment channel</label>
                <select name="channel" class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm" required>
                    <option value="mobile_money">Mobile money (M-Pesa, Tigo, Airtel)</option>
                    <option value="bank">Bank transfer</option>
                    <option value="cash">Cash at branch</option>
                    <option value="wallet">KopaFasta wallet</option>
                </select>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Payment reference</label>
                <input type="text" name="payment_reference" class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm font-mono" placeholder="e.g. CFW8X12LA9" required>
                <p class="mt-1 text-xs text-gray-500">Use the transaction reference from your payment.</p>
            </div>
            <div class="pt-2">
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-3 rounded-full text-sm">
                    Renew membership
                </button>
            </div>
        </form>
    </div>
</x-site.borrower-layout>
