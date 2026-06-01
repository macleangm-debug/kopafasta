@props(['sections' => []])

<div class="mb-6 rounded-2xl bg-white ring-1 ring-gray-200 p-5">
    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Profile completion</p>
    <ul class="mt-4 space-y-2">
        @foreach ($sections as $section)
            @php
                $status = $section['status'] ?? 'missing';
                $icon = match ($status) {
                    'complete' => '✓',
                    'pending'  => '⏳',
                    default    => '⏳',
                };
            @endphp
            <li class="flex items-center justify-between gap-3 text-sm">
                <span class="flex items-center gap-2 min-w-0">
                    <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold shrink-0
                        {{ $status === 'complete' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">
                        {{ $icon }}
                    </span>
                    <span class="{{ $status === 'complete' ? 'text-gray-500 line-through' : 'text-gray-900 font-medium' }}">
                        {{ $section['label'] ?? '' }}
                    </span>
                </span>
                @if ($status !== 'complete' && ! empty($section['action_url']))
                    <a href="{{ $section['action_url'] }}" class="text-xs font-semibold text-amber-700 hover:underline shrink-0">Complete →</a>
                @endif
            </li>
        @endforeach
    </ul>
</div>
