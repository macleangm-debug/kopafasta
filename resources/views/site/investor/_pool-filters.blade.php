@props(['risk' => '', 'type' => '', 'stacked' => false])

<div class="{{ $stacked ? 'space-y-4' : 'flex flex-wrap gap-2 items-center' }}">
    <div class="{{ $stacked ? 'space-y-2' : 'flex flex-wrap gap-2' }}">
        @if ($stacked)
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Risk level</p>
        @endif
        @foreach (['' => 'All', 'low' => 'Low risk', 'medium' => 'Medium risk', 'high' => 'High return'] as $k => $label)
            <a href="?risk={{ $k }}&type={{ $type }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold border inline-block
                      {{ $risk === $k ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </div>
    @unless ($stacked)
        <span class="mx-2 text-slate-300">|</span>
    @endunless
    <div class="{{ $stacked ? 'space-y-2' : 'flex flex-wrap gap-2' }}">
        @if ($stacked)
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Pool type</p>
        @endif
        @foreach (['' => 'All types', 'salary' => 'Salary', 'business' => 'Business', 'car' => 'Car', 'emergency' => 'Emergency'] as $k => $label)
            <a href="?risk={{ $risk }}&type={{ $k }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold border inline-block
                      {{ $type === $k ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
