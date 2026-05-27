<x-site.vendor-layout title="Calendar" active="calendar">
    <h1 class="text-2xl font-extrabold mb-1">Schedule</h1>
    <p class="text-sm text-gray-500 mb-5">Today, upcoming and overdue tasks.</p>

    @php
        $row = function ($t) {
            $badge = $t->status === 'assigned' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700';
            return view('site.vendor._calrow', ['t' => $t, 'badge' => $badge])->render();
        };
    @endphp

    @if ($overdue->isNotEmpty())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 mb-5">
            <h2 class="font-bold text-red-700 mb-3">Overdue ({{ $overdue->count() }})</h2>
            <div class="divide-y divide-red-100">
                @foreach ($overdue as $t)
                    <a href="{{ route('site.vendor.task', $t) }}" class="flex items-center justify-between py-2 hover:bg-white/50 -mx-2 px-2 rounded-lg">
                        <div>
                            <p class="font-semibold text-sm">{{ ucfirst(str_replace('_',' ', $t->task_type)) }} · {{ $t->customer_name ?: '—' }}</p>
                            <p class="text-xs text-red-700">Was due {{ $t->due_at->format('d M H:i') }} · {{ $t->location ?: '—' }}</p>
                        </div>
                        <span class="text-xs font-semibold text-red-700">→</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid md:grid-cols-2 gap-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="font-bold mb-3">Today</h2>
            @if ($today->isEmpty())
                <p class="text-sm text-gray-500">Nothing scheduled today.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($today as $t)
                        @include('site.vendor._calrow', ['t' => $t])
                    @endforeach
                </div>
            @endif
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <h2 class="font-bold mb-3">Upcoming (next 3 weeks)</h2>
            @if ($upcoming->isEmpty())
                <p class="text-sm text-gray-500">Nothing upcoming.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($upcoming as $t)
                        @include('site.vendor._calrow', ['t' => $t])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-site.vendor-layout>
