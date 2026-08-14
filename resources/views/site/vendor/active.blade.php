<x-site.vendor-layout title="Active jobs" active="active">
    <h1 class="text-2xl font-extrabold mb-1">Active jobs</h1>
    <p class="text-sm text-gray-500 mb-5">Tasks you have accepted or started.</p>

    @if ($tasks->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">No active jobs right now.</div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($tasks as $t)
                <a href="{{ route('site.partner.task', $t) }}" data-kf-share="kf-task-{{ $t->id }}" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 hover:shadow-sm block">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-bold">{{ ucfirst(str_replace('_',' ', $t->task_type)) }}</p>
                            <p class="text-xs text-gray-500">#{{ $t->id }}</p>
                        </div>
                        @php $color = $t->status === 'assigned' ? 'amber' : 'indigo'; @endphp
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-{{ $color }}-100 text-{{ $color }}-700">{{ str_replace('_',' ', $t->status) }}</span>
                    </div>
                    <div class="mt-3 space-y-1 text-sm text-gray-700">
                        <p><span class="text-gray-500">Customer:</span> {{ $t->customer_name ?: '—' }}</p>
                        <p><span class="text-gray-500">Location:</span> {{ $t->location ?: '—' }}</p>
                        <p><span class="text-gray-500">Due:</span> {{ $t->due_at ? $t->due_at->format('d M H:i') : 'flexible' }}</p>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $tasks->links() }}</div>
    @endif
</x-site.vendor-layout>
