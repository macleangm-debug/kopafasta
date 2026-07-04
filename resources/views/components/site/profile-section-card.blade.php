@props([
    'title',
    'editing' => false,
    'editUrl' => null,
    'complete' => null,
    'empty' => false,
    'addUrl' => null,
    'addLabel' => null,
])

<div class="glass-card overflow-hidden">
    <div class="flex items-start justify-between gap-3 px-6 py-4 border-b border-gray-100/80">
        <div>
            <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
            @if ($complete === true)
                <p class="text-xs text-emerald-700 mt-1">{{ __('borrower.profile.section_complete') }}</p>
            @elseif ($complete === false)
                <p class="text-xs text-amber-700 mt-1">{{ __('borrower.profile.section_incomplete') }}</p>
            @endif
        </div>
        @if (! $editing && $editUrl)
            <a href="{{ $editUrl }}"
               class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 hover:text-amber-800 px-3 py-1.5 rounded-full ring-1 ring-amber-200 bg-amber-50">
                {{ __('borrower.profile.edit_section') }}
            </a>
        @endif
    </div>

    <div class="p-6">
        @if ($empty && $addUrl)
            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-5 py-8 text-center">
                <p class="text-sm text-gray-600">{{ __('borrower.profile.section_empty') }}</p>
                <a href="{{ $addUrl }}"
                   class="inline-flex mt-4 items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    {{ $addLabel ?? __('borrower.profile.add_details') }}
                </a>
            </div>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
