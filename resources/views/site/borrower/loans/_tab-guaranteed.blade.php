@php
    $rows = $guaranteedLinks ?? collect();
    $viewMode = $viewMode ?? 'cards';
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-lg font-bold text-gray-900 mb-1">{{ __('borrower.loans_page.tab_guaranteed') }}</h2>
        <p class="text-sm text-gray-500">{{ __('borrower.loans_page.guaranteed_hint') }}</p>
    </div>
    <div class="inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'guaranteed', 'view' => 'cards']) }}"
           data-kf-motion="tab"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'cards' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'guaranteed', 'view' => 'table']) }}"
           data-kf-motion="tab"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'table' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.table') }}
        </a>
    </div>
</div>

@if ($rows->isEmpty())
    <x-site.empty-state
        icon="🛡"
        :title="__('borrower.loans_page.no_guaranteed')"
        :description="__('borrower.loans_page.guaranteed_empty_desc')"
    />
@else
    @include('site.borrower.loans._guarantor-tracking-list', [
        'rows' => $rows,
        'viewMode' => $viewMode,
    ])
@endif
