<div class="h-full text-white p-4 flex flex-col">
    <p class="text-[9px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta Plus</p>
    <p class="text-lg font-black mt-1 leading-tight">{{ $title }}</p>
    <p class="text-[11px] text-white/70 mt-1">{{ $body }}</p>
    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
        @foreach ($stats as $stat)
            <div>
                <p class="text-[8px] uppercase tracking-widest text-white/50">{{ $stat['label'] }}</p>
                <p class="text-xs font-bold mt-0.5 tabular-nums {{ $stat['gold'] ?? false ? 'text-brand-gold' : '' }}">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>
    @if (! empty($bar))
        <div class="mt-4 h-1.5 rounded-full bg-white/15 overflow-hidden">
            <div class="h-full bg-brand-gold rounded-full" style="width: {{ $bar }}%"></div>
        </div>
    @endif
    <div class="mt-auto rounded-xl bg-white/10 p-3 text-[11px] text-white/80">{{ $note }}</div>
</div>
