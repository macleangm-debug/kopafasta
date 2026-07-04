@props(['activePanel' => 'profile'])

@php
    $panels = [
        'profile' => [
            'label' => __('borrower.profile.panel_profile'),
            'route' => route('site.borrower.profile', ['section' => 'personal']),
        ],
        'membership' => [
            'label' => __('borrower.profile.panel_membership'),
            'route' => route('site.borrower.profile', ['section' => 'membership']),
        ],
    ];
@endphp

<nav class="mb-6 -mx-1 px-1 overflow-x-auto" aria-label="{{ __('borrower.profile.account_nav') }}">
    <div class="inline-flex min-w-full sm:min-w-0 p-1 rounded-2xl bg-gray-100/80 ring-1 ring-gray-200/80 gap-1">
        @foreach ($panels as $key => $panel)
            @php $isActive = $activePanel === $key; @endphp
            <a href="{{ $panel['route'] }}"
               class="flex-1 sm:flex-none min-w-[8.5rem] text-center px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap
                      {{ $isActive ? 'bg-white text-brand shadow-sm ring-1 ring-gray-200/80' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60' }}">
                {{ $panel['label'] }}
            </a>
        @endforeach
    </div>
</nav>
