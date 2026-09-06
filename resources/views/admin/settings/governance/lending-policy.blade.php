<x-admin.layout title="Lending Policy">
    <div class="space-y-6 max-w-5xl">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('admin.settings.governance') }}" class="text-sm text-brand hover:underline">← Governance</a>
                <h1 class="mt-2 text-2xl font-bold text-gray-900">Lending Policy</h1>
                <p class="mt-1 text-sm text-gray-600">Resolved from Settings / products. Not a Bank of Tanzania approval certificate.</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.governance.lending-policy.approve') }}" class="flex gap-2 items-end">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Approved by</label>
                    <input name="approved_by" value="{{ auth()->user()?->name }}" class="rounded-lg border-gray-200 text-sm">
                </div>
                <button class="rounded-xl bg-brand text-white font-semibold px-4 py-2.5 text-sm"
                        onclick="return confirm('Approve and snapshot the currently resolved Lending Policy?')">Approve snapshot</button>
            </form>
        </div>

        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @if (! empty($resolved['warnings']))
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900 space-y-1">
                @foreach ($resolved['warnings'] as $warning)
                    <p>⚠ {{ $warning }}</p>
                @endforeach
            </div>
        @endif

        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 text-sm grid sm:grid-cols-2 gap-3">
            <div><span class="text-gray-500">Version</span><p class="font-semibold">{{ $resolved['document']['version'] }}</p></div>
            <div><span class="text-gray-500">Status</span><p class="font-semibold">{{ $resolved['document']['status'] }}</p></div>
            <div><span class="text-gray-500">Jurisdiction</span><p class="font-semibold">{{ $resolved['document']['jurisdiction'] }}</p></div>
            <div><span class="text-gray-500">Fingerprint</span><p class="font-mono text-xs">{{ $resolved['fingerprint'] }}</p></div>
            <div><span class="text-gray-500">Current approved</span><p class="font-semibold">{{ $current?->version ?? '—' }}</p></div>
            <div><span class="text-gray-500">Resolved</span><p class="font-semibold">{{ $resolved['document']['resolved_at'] }}</p></div>
        </div>

        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <h2 class="font-bold text-gray-900">Products (auto-resolved)</h2>
            <div class="mt-3 space-y-3">
                @foreach ($resolved['products'] as $product)
                    <div class="rounded-xl ring-1 ring-gray-100 p-3 text-sm">
                        <p class="font-semibold">{{ $product['name'] }} <span class="text-gray-500">({{ $product['code'] }})</span></p>
                        <p class="text-gray-600 mt-1">
                            {{ format_money($product['min_amount'], false, 0) }}–{{ format_money($product['max_amount'], false, 0) }}
                            · {{ $product['tenure_min_months'] }}–{{ $product['tenure_max_months'] }} mo
                            · {{ $product['rate'] }}
                        </p>
                        @if (! empty($product['fees']))
                            <p class="mt-1 text-xs text-gray-500">
                                @foreach ($product['fees'] as $fee)
                                    {{ $fee['name'] ?? '' }}: {{ $fee['display'] ?? '' }}@if(! $loop->last); @endif
                                @endforeach
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <h2 class="font-bold text-gray-900">Version history</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($versions as $version)
                    <li class="flex flex-wrap justify-between gap-2 ring-1 ring-gray-100 rounded-xl px-3 py-2">
                        <span class="font-semibold">{{ $version->version }} · {{ $version->status }}</span>
                        <span class="text-gray-500">{{ optional($version->effective_at)->toDateString() }} · {{ $version->approved_by }}</span>
                    </li>
                @empty
                    <li class="text-gray-500">No approved snapshots yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-admin.layout>
