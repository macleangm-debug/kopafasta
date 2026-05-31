@props(['items' => []])

<div class="mb-6 rounded-2xl bg-white ring-1 ring-gray-200 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Loan eligibility</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1">Application requirements</h2>
            <p class="text-sm text-gray-500 mt-1">Complete every step below before you can apply for a loan.</p>
        </div>
        @if ($items['can_apply'] ?? false)
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-emerald-100 text-emerald-800">Ready to apply</span>
        @else
            <span class="text-xs font-semibold rounded-full px-3 py-1 bg-amber-100 text-amber-800">Action required</span>
        @endif
    </div>
    <ul class="space-y-2">
        @foreach (($items['items'] ?? []) as $item)
            <li class="flex items-start gap-3 rounded-xl px-3 py-3 {{ $item['complete'] ? 'bg-emerald-50/80' : 'bg-gray-50' }}">
                <span class="mt-0.5 shrink-0 w-5 h-5 rounded-full grid place-items-center text-xs font-bold {{ $item['complete'] ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500' }}">
                    @if ($item['complete'])
                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 10l3 3 7-7"/></svg>
                    @else
                        ·
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold {{ $item['complete'] ? 'text-emerald-900' : 'text-gray-900' }}">{{ $item['label'] }}</p>
                    <p class="text-xs {{ $item['complete'] ? 'text-emerald-700' : 'text-gray-500' }} mt-0.5">{{ $item['detail'] }}</p>
                </div>
                @if (! $item['complete'] && ! empty($item['action_url']))
                    <a href="{{ $item['action_url'] }}" class="shrink-0 text-xs font-semibold text-amber-700 hover:underline whitespace-nowrap">Complete →</a>
                @endif
            </li>
        @endforeach
    </ul>
</div>
