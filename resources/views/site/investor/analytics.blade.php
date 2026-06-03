<x-site.investor-layout title="Portfolio analytics — Investor" active="analytics">
    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mb-1">Portfolio analytics</h1>
    <p class="text-slate-500 text-sm mb-6">Diversification, exposure and performance at a glance.</p>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500 font-semibold">Active capital</p>
            <p class="text-2xl font-bold mt-1">TZS {{ $fmt($stats['active']) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500 font-semibold">Portfolio yield</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ $stats['portfolioPerf'] }}%</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500 font-semibold">Default rate</p>
            <p class="text-2xl font-bold {{ $stats['defaultRate'] > 5 ? 'text-red-600' : 'text-emerald-700' }} mt-1">{{ $stats['defaultRate'] }}%</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase text-slate-500 font-semibold">Active loans</p>
            <p class="text-2xl font-bold mt-1">{{ $stats['activeLoans'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold mb-4">Diversification by loan type</h2>
            @if ($diversificationPct->isEmpty())
                <p class="text-sm text-slate-500">No active investments yet.</p>
            @else
                @php $colors = ['salary' => 'sky', 'business' => 'emerald', 'car' => 'amber', 'emergency' => 'red', 'other' => 'slate']; @endphp
                <div class="space-y-3">
                    @foreach ($diversificationPct as $type => $pct)
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-semibold capitalize">{{ $type }}</span>
                                <span class="text-slate-600">{{ $pct }}% · TZS {{ $fmt($diversification[$type]) }}</span>
                            </div>
                            <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-{{ $colors[$type] ?? 'slate' }}-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="font-bold mb-4">Risk exposure</h2>
            @if ($riskExposurePct->isEmpty())
                <p class="text-sm text-slate-500">No active investments yet.</p>
            @else
                @php $rc = ['low' => 'emerald', 'medium' => 'amber', 'high' => 'red']; @endphp
                <div class="space-y-3">
                    @foreach ($riskExposurePct as $level => $pct)
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-semibold capitalize">{{ $level }} risk</span>
                                <span class="text-slate-600">{{ $pct }}% · TZS {{ $fmt($riskExposure[$level]) }}</span>
                            </div>
                            <div class="h-3 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-{{ $rc[$level] ?? 'slate' }}-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-site.investor-layout>
