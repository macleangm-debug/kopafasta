@php
    $closedRows = collect($closedApplicationRows ?? [])
        ->concat($closedLoanRows ?? [])
        ->sortByDesc(fn (array $row) => (int) ($row['sort_at'] ?? 0))
        ->values()
        ->all();
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
    <h2 class="text-lg font-semibold">{{ __('borrower.applications_list.closed_title') }}</h2>
</div>

@if ($closedRows === [])
    <x-site.empty-state
        icon="📁"
        :title="__('borrower.applications_list.empty_closed_title')"
        :description="__('borrower.applications_list.empty_closed_desc')"
    />
@else
    <div class="lg:hidden">
        @include('site.borrower.loans._applications-cards', ['rows' => $closedRows, 'toneClasses' => $toneClasses])
    </div>
    <div class="hidden lg:block">
        @include('site.borrower.loans._applications-table', ['rows' => $closedRows, 'closed' => true])
    </div>
@endif
