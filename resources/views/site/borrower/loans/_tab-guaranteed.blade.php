@php
    $rows = $guaranteedLinks ?? collect();
    $viewMode = $viewMode ?? 'cards';
@endphp

<div class="mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">{{ __('borrower.loans_page.tab_guaranteed') }}</h2>
    <p class="text-sm text-gray-500">{{ __('borrower.loans_page.guaranteed_hint') }}</p>
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
        'viewMode' => 'cards',
    ])
@endif
