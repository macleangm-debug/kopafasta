@php
    $closedRows = collect($closedApplicationRows ?? [])
        ->concat($closedLoanRows ?? [])
        ->sortByDesc(fn (array $row) => (int) ($row['sort_at'] ?? 0))
        ->values()
        ->all();
    $viewMode = $viewMode ?? 'cards';
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
        <h2 class="text-lg font-semibold">{{ __('borrower.applications_list.closed_title') }}</h2>
    </div>
    <div class="hidden lg:inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'closed', 'view' => 'cards']) }}"
           data-kf-motion="tab"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'cards' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'closed', 'view' => 'table']) }}"
           data-kf-motion="tab"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'table' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.table') }}
        </a>
    </div>
</div>

@if ($closedRows === [])
    <x-site.empty-state
        icon="📁"
        :title="__('borrower.applications_list.empty_closed_title')"
        :description="__('borrower.applications_list.empty_closed_desc')"
    />
@else
    <div class="lg:hidden">
        @include('site.borrower.loans._applications-closed', ['rows' => $closedRows, 'toneClasses' => $toneClasses])
    </div>
    <div class="hidden lg:block">
        @if ($viewMode === 'cards')
            @include('site.borrower.loans._applications-closed', ['rows' => $closedRows, 'toneClasses' => $toneClasses])
        @else
            @include('site.borrower.loans._applications-table', ['rows' => $closedRows, 'closed' => true])
        @endif
    </div>
@endif
