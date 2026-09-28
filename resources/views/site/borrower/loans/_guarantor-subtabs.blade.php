@php
    $section = $guarantorSection ?? 'requests';
    $subtabs = [
        'requests' => __('borrower.loans_page.tab_guarantor_requests'),
        'guaranteed' => __('borrower.loans_page.tab_guaranteed'),
    ];
@endphp

<nav class="mb-5 -mx-1 px-1 overflow-x-auto snap-x snap-mandatory scrollbar-none" aria-label="{{ __('borrower.loans_page.tab_guarantor') }}">
    <div class="inline-flex min-w-max gap-2">
        @foreach ($subtabs as $key => $label)
            <a href="{{ route('site.borrower.loans', ['tab' => 'guarantor', 'section' => $key, 'view' => $viewMode ?? 'cards']) }}"
               data-kf-motion="tab"
               class="snap-start inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-semibold transition whitespace-nowrap {{ $section === $key ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : 'text-gray-600 hover:bg-brand-muted/40' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
