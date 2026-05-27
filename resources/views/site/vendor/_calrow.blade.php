@php
    $badge = $t->status === 'assigned' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700';
@endphp
<a href="{{ route('site.vendor.task', $t) }}" class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded-lg">
    <div class="min-w-0">
        <p class="font-semibold text-sm truncate">{{ ucfirst(str_replace('_',' ', $t->task_type)) }} · {{ $t->customer_name ?: '—' }}</p>
        <p class="text-xs text-gray-500 truncate">{{ $t->due_at->format('d M H:i') }} · {{ $t->location ?: '—' }}</p>
    </div>
    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge }} shrink-0 ml-3">{{ str_replace('_',' ', $t->status) }}</span>
</a>
