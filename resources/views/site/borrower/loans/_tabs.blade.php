@props([
    'activeTab' => 'applications',
    'viewMode' => 'cards',
    'inline' => false,
    'showGuarantorTab' => false,
    'showGuaranteedTab' => false,
])

@php
    $tabs = [
        'applications' => __('borrower.loans_page.tab_applications'),
        'active' => __('borrower.loans_page.tab_active'),
    ];
    if ($showGuarantorTab) {
        $tabs['guarantor'] = __('borrower.loans_page.tab_guarantor_requests');
    }
    if ($showGuaranteedTab) {
        $tabs['guaranteed'] = __('borrower.loans_page.tab_guaranteed');
    }
@endphp

<nav class="-mx-1 px-1 overflow-x-auto snap-x snap-mandatory scrollbar-none {{ $inline ? '' : 'mb-6 border-b border-gray-200 pb-3' }}" aria-label="{{ __('borrower.loans_page.title') }}">
    <div class="inline-flex min-w-max gap-2 pb-1">
    @foreach ($tabs as $key => $label)
        @php
            $params = ['tab' => $key];
            if ($key === 'applications' && in_array($viewMode, ['cards', 'table'], true)) {
                $params['view'] = $viewMode;
            }
            $isActive = $activeTab === $key;
        @endphp
        <a href="{{ route('site.borrower.loans', $params) }}"
           data-kf-motion="tab"
           class="snap-start inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-sm font-medium transition whitespace-nowrap {{ $isActive ? 'bg-brand text-white shadow-sm' : 'bg-white/80 text-gray-600 ring-1 ring-gray-200/80 hover:bg-brand-muted hover:text-brand' }}">
            <span>{{ $label }}</span>
        </a>
    @endforeach
    </div>
</nav>
