@props([
    'activeTab' => 'applications',
    'pendingGuarantorCount' => 0,
    'viewMode' => 'cards',
])

@php
    $tabs = [
        'applications' => __('borrower.loans_page.tab_applications'),
        'active' => __('borrower.loans_page.tab_active'),
        'guarantor-requests' => __('borrower.loans_page.tab_guarantor_requests'),
        'guaranteed' => __('borrower.loans_page.tab_guaranteed'),
    ];
@endphp

<nav class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-3">
    @foreach ($tabs as $key => $label)
        @php
            $params = ['tab' => $key];
            if ($key === 'applications' && in_array($viewMode, ['cards', 'table'], true)) {
                $params['view'] = $viewMode;
            }
            $isActive = $activeTab === $key;
        @endphp
        <a href="{{ route('site.borrower.loans', $params) }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $isActive ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
            <span>{{ $label }}</span>
            @if ($key === 'guarantor-requests' && $pendingGuarantorCount > 0)
                <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $pendingGuarantorCount > 9 ? '9+' : $pendingGuarantorCount }}</span>
            @endif
        </a>
    @endforeach
</nav>
