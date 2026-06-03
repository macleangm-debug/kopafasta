<x-site.investor-layout title="Investment — {{ $investment->reference }}" active="investments">
    <a href="{{ route('site.investor.investments') }}" class="text-sm text-emerald-700 hover:underline font-semibold">← My investments</a>

    <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-emerald-900 text-white p-6 mt-4 mb-6 shadow-lg">
        <p class="text-xs uppercase tracking-widest text-emerald-300/80 font-semibold">Investment {{ $investment->reference }}</p>
        <p class="text-3xl font-extrabold mt-1">TZS {{ $fmt($investment->principal) }}</p>
        <p class="text-sm text-slate-300 mt-2">At {{ rtrim(rtrim(number_format($investment->return_rate, 2),'0'),'.') }}% expected yield · expected return TZS {{ $fmt($investment->return_amount) }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold mb-4">Details</h2>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-slate-500 text-xs uppercase">Pool</dt><dd class="font-semibold">{{ $investment->pool?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500 text-xs uppercase">Status</dt><dd class="font-semibold capitalize">{{ $investment->status }}</dd></div>
                <div><dt class="text-slate-500 text-xs uppercase">Invested</dt><dd class="font-semibold">{{ optional($investment->invested_at)->format('d M Y') }}</dd></div>
                <div><dt class="text-slate-500 text-xs uppercase">Matures</dt><dd class="font-semibold">{{ optional($investment->matures_at)->format('d M Y') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500 text-xs uppercase">Principal</dt><dd class="font-semibold">TZS {{ $fmt($investment->principal) }}</dd></div>
                <div><dt class="text-slate-500 text-xs uppercase">Expected return</dt><dd class="font-semibold text-emerald-700">TZS {{ $fmt($investment->return_amount) }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold mb-4">Payouts</h2>
            @if ($payouts->isEmpty())
                <p class="text-sm text-slate-500">No payouts yet.</p>
            @else
                <ul class="space-y-3 text-sm">
                    @foreach ($payouts as $p)
                        <li class="flex items-center justify-between">
                            <div>
                                <p class="font-medium capitalize">{{ $p->type }}</p>
                                <p class="text-xs text-slate-500">{{ $p->created_at->format('d M Y') }}</p>
                            </div>
                            <p class="font-semibold {{ $p->type === 'return' ? 'text-emerald-700' : '' }}">TZS {{ $fmt($p->amount) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-site.investor-layout>
