@php $isExtra = ! empty($pair['extra']); @endphp
<div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">
    <button type="button" class="p-3 text-left hover:bg-gray-50/80 transition"
            @if (! empty($pair['borrower']['url']))
                @click="open(@js($pair['borrower']['url']), @js($pair['borrower']['label'] ?? $pair['label']))"
            @endif>
        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-2">
            Asset · {{ $pair['label'] }}
            @if ($isExtra)
                <span class="normal-case tracking-normal font-semibold text-slate-500">· extra</span>
            @endif
        </p>
        @if (! empty($pair['borrower']['url']))
            <img src="{{ $pair['borrower']['url'] }}" alt="{{ $pair['label'] }}" class="w-full max-h-56 object-cover rounded-lg ring-1 ring-gray-200">
            <span class="text-[11px] font-semibold text-brand mt-1.5 inline-block">Enlarge</span>
        @elseif ($isExtra)
            <div class="h-40 grid place-items-center rounded-lg bg-slate-50 text-sm text-slate-600 ring-1 ring-slate-100 px-3 text-center">Not asked of the owner — valuer extra</div>
        @else
            <div class="h-40 grid place-items-center rounded-lg bg-slate-50 text-sm text-slate-600 ring-1 ring-slate-100">No {{ strtolower($pair['label'] ?? 'angle') }} photo on the asset profile</div>
        @endif
    </button>
    <button type="button" class="p-3 text-left hover:bg-gray-50/80 transition"
            @if (! empty($pair['valuer']['url']))
                @click="open(@js($pair['valuer']['url']), @js($pair['valuer']['label'] ?? $pair['label']))"
            @endif>
        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-2">Valuer · {{ $pair['label'] }}</p>
        @if (! empty($pair['valuer']['url']))
            <img src="{{ $pair['valuer']['url'] }}" alt="{{ $pair['label'] }}" class="w-full max-h-56 object-cover rounded-lg ring-1 ring-gray-200">
            <span class="text-[11px] font-semibold text-brand mt-1.5 inline-block">Enlarge</span>
        @else
            <div class="h-40 grid place-items-center rounded-lg bg-amber-50 text-sm text-amber-800 ring-1 ring-amber-100">Valuer has not uploaded this angle</div>
        @endif
    </button>
</div>
