@php
    $dashboard = app(\App\Services\BorrowerApplicationsDashboardService::class);
    $rows = $applicationRows ?? [];
    $activeRows = collect($rows)->reject(fn (array $row) => $dashboard->isClosedRow($row))->values()->all();
    $toneClasses = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-brand-muted text-brand',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
        'orange'  => 'bg-orange-100 text-orange-700',
    ];
@endphp

<div class="mb-6">
    <h2 class="text-lg font-semibold">{{ __('borrower.applications_list.active_title') }}</h2>
    <p class="text-sm text-gray-500">{{ __('borrower.applications_list.active_hint') }}</p>
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
        @include('site.borrower.loans._applications-table', ['rows' => $activeRows])
    </div>
@endif
