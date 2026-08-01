<x-site.investor-layout title="Wallet — Investor" active="wallet">
    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mb-1">Wallet</h1>
    <p class="text-gray-500 text-sm mb-6">Deposit funds to invest, or withdraw your earnings.</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-emerald-900 text-white p-6 shadow-lg">
                <p class="text-xs uppercase tracking-widest text-brand-gold/90 font-semibold">Available balance</p>
                <p class="text-4xl font-extrabold mt-1">TZS {{ $fmt($stats['available']) }}</p>
                <div class="grid grid-cols-3 gap-4 mt-5">
                    <div><p class="text-xs text-brand-gold/90 uppercase">Total deposited</p><p class="font-bold">TZS {{ $fmt($stats['deposited']) }}</p></div>
                    <div><p class="text-xs text-brand-gold/90 uppercase">Total withdrawn</p><p class="font-bold">TZS {{ $fmt($stats['withdrawn']) }}</p></div>
                    <div><p class="text-xs text-brand-gold/90 uppercase">Earned</p><p class="font-bold text-brand-gold">TZS {{ $fmt($stats['returnsPaid']) }}</p></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-6">
                    <h2 class="font-bold mb-1">Deposit funds</h2>
                    <p class="text-xs text-gray-500 mb-4">Add capital ready for investment.</p>
                    <form method="POST" action="{{ route('site.investor.wallet.deposit') }}" class="space-y-3">
                        @csrf
                        <div>
                            <x-site.numeric-input name="amount" label="Amount (TZS)" :required="true" :money="true" />
                        </div>
                        <div>
                            <label class="text-xs uppercase text-gray-500 font-semibold">Channel</label>
                            <select name="channel" required class="w-full mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <option value="bank">Bank transfer</option>
                                <option value="mobile_money">Mobile money</option>
                                <option value="stablecoin">Stablecoin (USDT/USDC)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs uppercase text-gray-500 font-semibold">Payment reference (optional)</label>
                            <input type="text" name="payment_reference" maxlength="80" class="w-full mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </div>
                        <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 text-sm">Deposit</button>
                    </form>
                </div>

                <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-6">
                    <h2 class="font-bold mb-1">Withdraw earnings</h2>
                    <p class="text-xs text-gray-500 mb-4">Cash out your available balance.</p>
                    <form method="POST" action="{{ route('site.investor.wallet.withdraw') }}" class="space-y-3">
                        @csrf
                        <div>
                            <x-site.numeric-input name="amount" label="Amount (TZS)" :required="true" :money="true" />
                        </div>
                        <div>
                            <label class="text-xs uppercase text-gray-500 font-semibold">To channel</label>
                            <select name="channel" required class="w-full mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <option value="bank">Bank account</option>
                                <option value="mobile_money">Mobile money</option>
                                <option value="stablecoin">Stablecoin wallet</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs uppercase text-gray-500 font-semibold">Destination reference</label>
                            <input type="text" name="payment_reference" maxlength="80" placeholder="Account or wallet number" class="w-full mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </div>
                        <button class="w-full rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold px-4 py-2.5 text-sm">Request withdrawal</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-6 h-fit">
            <h2 class="font-bold mb-4">Recent activity</h2>
            @if ($recent->isEmpty())
                <p class="text-sm text-gray-500">No activity yet.</p>
            @else
                <ul class="space-y-3 text-sm">
                    @foreach ($recent as $t)
                        <li class="flex items-center justify-between border-b border-slate-100 pb-2 last:border-0">
                            <div>
                                <p class="font-medium capitalize">{{ $t->type }}</p>
                                <p class="text-xs text-gray-500">{{ $t->created_at->diffForHumans() }}</p>
                            </div>
                            <p class="font-semibold {{ in_array($t->type, ['return','deposit']) ? 'text-brand' : '' }}">TZS {{ $fmt($t->amount) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-site.investor-layout>
