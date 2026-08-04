@php
    $pending = $pendingGuarantorRequests ?? collect();
    $tracking = $trackingGuarantees ?? collect();
    $activeTracking = $tracking->reject(fn ($row) => $row->is_terminal ?? false)->values();
    $needsProfile = $activeTracking->filter(fn ($row) => $row->needs_guarantor_profile ?? false)->values();
    $waitingOthers = $activeTracking->reject(fn ($row) => $row->needs_guarantor_profile ?? false)->values();
    $closedTracking = $tracking->filter(fn ($row) => $row->is_terminal ?? false)->values();
    $viewMode = $viewMode ?? 'cards';
    $isEmpty = $pending->isEmpty() && $tracking->isEmpty();
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-lg font-bold text-gray-900 mb-1">{{ __('borrower.loans_page.tab_guarantor_requests') }}</h2>
        <p class="text-sm text-gray-500">{{ __('borrower.guarantor.tab_hint') }}</p>
        @if (! empty($guarantorExposure))
            <div class="mt-3 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-xs text-gray-700 flex flex-wrap gap-4">
                <span>{{ __('borrower.loan_actions.guarantee_exposure') }}: <strong>{{ $guarantorExposure['count'] }}/{{ $guarantorExposure['max'] }}</strong></span>
                <span>{{ __('borrower.loan_actions.guarantee_total') }}: <strong>{{ format_money($guarantorExposure['exposure']) }}</strong></span>
            </div>
        @endif
    </div>
    <div class="inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'guarantor', 'view' => 'cards']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'cards' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'guarantor', 'view' => 'table']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'table' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.table') }}
        </a>
    </div>
</div>

@if ($isEmpty)
    <x-site.empty-state
        icon="🤝"
        :title="__('borrower.guarantor_requests_page.empty_title')"
        :description="__('borrower.guarantor_requests_page.empty_desc')"
    />
@else
    @if ($pending->isNotEmpty())
        <div class="mb-8">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-gray-900">{{ __('borrower.guarantor.section_needs_decision') }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.guarantor.section_needs_decision_hint') }}</p>
            </div>
            @include('site.borrower.loans._guarantor-pending-list', [
                'rows' => $pending,
                'viewMode' => $viewMode,
            ])
        </div>
    @endif

    @if ($needsProfile->isNotEmpty())
        <div class="mb-8">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-amber-900">{{ __('borrower.guarantor.section_needs_profile') }}</h3>
                <p class="text-xs text-amber-800/80 mt-0.5">{{ __('borrower.guarantor.section_needs_profile_hint') }}</p>
            </div>
            @include('site.borrower.loans._guarantor-tracking-list', [
                'rows' => $needsProfile,
                'viewMode' => $viewMode,
            ])
        </div>
    @endif

    @if ($waitingOthers->isNotEmpty())
        <div class="mb-8">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-gray-900">{{ __('borrower.guarantor.section_in_progress') }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.guarantor.section_in_progress_hint') }}</p>
            </div>
            @include('site.borrower.loans._guarantor-tracking-list', [
                'rows' => $waitingOthers,
                'viewMode' => $viewMode,
            ])
        </div>
    @endif

    @if ($closedTracking->isNotEmpty())
        <details class="mt-2 group rounded-2xl ring-1 ring-gray-200/80 bg-white/70 overflow-hidden">
            <summary class="cursor-pointer list-none px-5 py-4 flex items-center justify-between gap-3 [&::-webkit-details-marker]:hidden hover:bg-gray-50/80 transition">
                <div>
                    <p class="text-sm font-bold text-gray-900">{{ __('borrower.guarantor.section_closed') }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.guarantor.section_closed_hint') }}</p>
                </div>
                <svg class="size-4 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </summary>
            <div class="px-5 pb-5 border-t border-gray-100 pt-4">
                @include('site.borrower.loans._guarantor-tracking-list', [
                    'rows' => $closedTracking,
                    'viewMode' => $viewMode,
                ])
            </div>
        </details>
    @endif
@endif
