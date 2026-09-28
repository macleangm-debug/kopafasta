@php
    $pending = $pendingGuarantorRequests ?? collect();
    $tracking = $trackingGuarantees ?? collect();
    $viewMode = $viewMode ?? 'cards';
    $isEmpty = $pending->isEmpty() && $tracking->isEmpty();
@endphp

<div class="mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">{{ __('borrower.loans_page.tab_guarantor_requests') }}</h2>
    <p class="text-sm text-gray-500">{{ __('borrower.guarantor.tab_hint') }}</p>
</div>

@if ($isEmpty)
    <x-site.empty-state
        icon="🤝"
        :title="__('borrower.guarantor_requests_page.empty_title')"
        :description="__('borrower.guarantor_requests_page.empty_desc')"
    />
@else
    @if ($pending->isNotEmpty())
        @if ($tracking->isNotEmpty())
            <p class="text-xs uppercase tracking-widest text-amber-800 font-semibold mb-3">
                {{ __('borrower.loans_page.guarantor_action_needed') }}
            </p>
        @endif
        @include('site.borrower.loans._guarantor-pending-list', [
            'rows' => $pending,
            'viewMode' => 'cards',
        ])
    @endif

    @if ($tracking->isNotEmpty())
        @if ($pending->isNotEmpty())
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mt-8 mb-3">
                {{ __('borrower.loans_page.guarantor_in_progress') }}
            </p>
        @endif
        @include('site.borrower.loans._guarantor-tracking-list', [
            'rows' => $tracking,
            'viewMode' => 'cards',
        ])
    @endif
@endif
