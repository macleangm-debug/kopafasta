@props(['activePanel' => 'profile'])

@php
    $panels = [
        'profile' => [
            'label' => __('borrower.profile.panel_profile'),
            'url'   => route('site.borrower.profile'),
        ],
        'membership' => [
            'label' => __('borrower.profile.panel_membership'),
            'url'   => route('site.borrower.profile', ['section' => 'membership']),
        ],
    ];
@endphp

<nav class="mb-6" aria-label="{{ __('borrower.profile.account_nav') }}">
    <div class="inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 backdrop-blur p-0.5 text-sm">
        @foreach ($panels as $key => $panel)
            <a href="{{ $panel['url'] }}"
               data-kf-motion="tab"
               @class([
                   'px-4 py-2 rounded-lg font-semibold transition',
                   $activePanel === $key
                       ? 'bg-brand text-white shadow-sm'
                       : 'text-gray-600 hover:bg-brand-muted/50',
               ])>
                {{ $panel['label'] }}
            </a>
        @endforeach
    </div>
</nav>
