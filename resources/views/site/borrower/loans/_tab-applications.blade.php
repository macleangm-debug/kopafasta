@php
    $dashboard = app(\App\Services\BorrowerApplicationsDashboardService::class);
    $rows = $applicationRows ?? [];
    $activeRows = collect($rows)->reject(fn (array $row) => $dashboard->isClosedRow($row))->values()->all();
    $viewMode = $viewMode ?? 'table';
    $toneClasses = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-brand-muted text-brand',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
        'orange'  => 'bg-orange-100 text-orange-700',
    ];
@endphp

<div class="flex items-center justify-between flex-wrap gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold">{{ __('borrower.applications_list.active_title') }}</h2>
        <p class="text-sm text-gray-500">{{ __('borrower.applications_list.active_hint') }}</p>
    </div>
    {{-- Desktop-only view toggle. Mobile always uses cards. --}}
    <div class="hidden lg:inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'cards']) }}"
           data-kf-motion="tab"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'cards' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'table']) }}"
           data-kf-motion="tab"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'table' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.table') }}
        </a>
    </div>
</div>

@if ($activeRows === [])
    <div class="mb-8">
        <x-site.empty-state
            icon="📋"
            :title="__('borrower.applications_list.empty_active_title')"
            :description="__('borrower.applications_list.empty_active_desc')"
            :action-label="__('borrower.applications_list.empty_action')"
            :action-url="route('site.borrower.loan-products')"
        />
    </div>
@else
    <div class="lg:hidden">
        @include('site.borrower.loans._applications-cards', ['rows' => $activeRows, 'toneClasses' => $toneClasses])
    </div>
    <div class="hidden lg:block">
        @if ($viewMode === 'cards')
            @include('site.borrower.loans._applications-cards', ['rows' => $activeRows, 'toneClasses' => $toneClasses])
        @else
            @include('site.borrower.loans._applications-table', ['rows' => $activeRows])
        @endif
    </div>
@endif
