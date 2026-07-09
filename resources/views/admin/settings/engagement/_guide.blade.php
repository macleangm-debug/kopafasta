@props([
    'title' => 'How this works for members',
    'summary' => null,
    'borrowerSees' => [],
    'fields' => [],
    'example' => null,
    'tips' => [],
])

<div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-5 py-5 text-sm text-sky-950">
    <p class="text-xs font-semibold uppercase tracking-widest text-sky-700 mb-1">Admin guide</p>
    <h3 class="font-semibold text-sky-950 text-base">{{ $title }}</h3>
    @if ($summary)
        <p class="mt-2 text-sky-900/90 leading-relaxed">{{ $summary }}</p>
    @endif

    @if ($borrowerSees !== [])
        <div class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-sky-700 mb-2">What the borrower sees</p>
            <ul class="space-y-1.5 text-sky-900/90">
                @foreach ($borrowerSees as $item)
                    <li class="flex gap-2"><span class="text-sky-600 shrink-0">→</span><span>{{ $item }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($fields !== [])
        <div class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-sky-700 mb-2">How to set the fields</p>
            <dl class="space-y-2">
                @foreach ($fields as $label => $hint)
                    <div class="grid sm:grid-cols-[11rem_1fr] gap-1 sm:gap-3">
                        <dt class="font-semibold text-sky-950">{{ $label }}</dt>
                        <dd class="text-sky-900/90">{{ $hint }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    @if ($example)
        <div class="mt-4 rounded-lg bg-white/70 ring-1 ring-sky-200 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-sky-700 mb-1">Worked example</p>
            <p class="text-sky-900/90 leading-relaxed">{{ $example }}</p>
        </div>
    @endif

    @if ($tips !== [])
        <ul class="mt-4 space-y-1.5 text-sky-900/90">
            @foreach ($tips as $tip)
                <li class="flex gap-2"><span class="text-sky-600 shrink-0">•</span><span>{{ $tip }}</span></li>
            @endforeach
        </ul>
    @endif
</div>
