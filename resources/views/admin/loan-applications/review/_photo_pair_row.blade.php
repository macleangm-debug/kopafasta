@php
    $isExtra = ! empty($pair['extra']);
    $compare = $compare ?? ! $isExtra;
    $borrowerUrl = $pair['borrower']['url'] ?? null;
    $valuerUrl = $pair['valuer']['url'] ?? null;
    $angle = $pair['angle'] ?? \Illuminate\Support\Str::slug($pair['label'] ?? 'angle');
@endphp
<div class="p-3 space-y-2" x-data="{ pairMatch: '' }">
    <p class="text-[11px] font-bold text-brand">{{ $pair['label'] }}</p>
    @if ($compare)
        <button type="button"
                class="w-full grid grid-cols-2 gap-2 text-left"
                @click="openPair(@js($borrowerUrl), @js($valuerUrl), @js($pair['label']))">
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-500 mb-1">Borrower</p>
                @if ($borrowerUrl)
                    <img src="{{ $borrowerUrl }}" alt="Borrower {{ $pair['label'] }}" class="w-full h-24 sm:h-28 object-cover rounded-lg ring-1 ring-gray-200">
                @else
                    <div class="h-24 sm:h-28 grid place-items-center rounded-lg bg-slate-50 text-[11px] text-slate-600 ring-1 ring-slate-100 px-2 text-center">No borrower photo</div>
                @endif
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-500 mb-1">Valuer</p>
                @if ($valuerUrl)
                    <img src="{{ $valuerUrl }}" alt="Valuer {{ $pair['label'] }}" class="w-full h-24 sm:h-28 object-cover rounded-lg ring-1 ring-gray-200">
                @else
                    <div class="h-24 sm:h-28 grid place-items-center rounded-lg bg-amber-50 text-[11px] text-amber-800 ring-1 ring-amber-100 px-2 text-center">Valuer has not uploaded this angle</div>
                @endif
            </div>
        </button>
        <div class="flex flex-wrap gap-1.5">
            <label class="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-bold ring-1 cursor-pointer"
                   :class="pairMatch === 'pass' ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-white text-gray-600 ring-gray-200'">
                <input type="radio" class="sr-only" name="photo_match[{{ $angle }}]" value="pass" x-model="pairMatch">
                Matches
            </label>
            <label class="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-bold ring-1 cursor-pointer"
                   :class="pairMatch === 'fail' ? 'bg-amber-50 text-amber-950 ring-amber-200' : 'bg-white text-gray-600 ring-gray-200'">
                <input type="radio" class="sr-only" name="photo_match[{{ $angle }}]" value="fail" x-model="pairMatch">
                Concern
            </label>
        </div>
        <textarea x-show="pairMatch === 'fail'" x-cloak name="photo_match_notes[{{ $angle }}]" rows="2"
                  class="w-full rounded-lg border-amber-200 text-sm" placeholder="What does not match?"></textarea>
    @else
        <button type="button" class="text-left"
                @if ($valuerUrl)
                    @click="open(@js($valuerUrl), @js($pair['valuer']['label'] ?? $pair['label']))"
                @endif>
            <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-500 mb-1">Additional valuer photo</p>
            @if ($valuerUrl)
                <img src="{{ $valuerUrl }}" alt="{{ $pair['label'] }}" class="w-24 h-24 object-cover rounded-lg ring-1 ring-gray-200">
            @else
                <div class="h-24 w-24 grid place-items-center rounded-lg bg-slate-50 text-[11px] text-slate-600 ring-1 ring-slate-100">No photo</div>
            @endif
        </button>
    @endif
</div>
