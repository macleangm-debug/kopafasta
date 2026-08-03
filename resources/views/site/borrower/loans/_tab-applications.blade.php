@php
    $isClosedRow = fn (array $row): bool => ! empty($row['is_closed'])
        || in_array((string) ($row['status'] ?? ''), ['withdrawn', 'offer_declined', 'rejected'], true);

    $rows = $applicationRows ?? [];
    $activeRows = collect($rows)->reject($isClosedRow)->values()->all();
    $closedRows = collect($rows)->filter($isClosedRow)->values()->all();
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
    <div class="inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'cards']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'cards' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'table']) }}"
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
@elseif ($viewMode === 'table')
    @include('site.borrower.loans._applications-table', ['rows' => $activeRows])
@else
    @include('site.borrower.loans._applications-cards', ['rows' => $activeRows, 'toneClasses' => $toneClasses])
@endif

@if ($closedRows !== [])
    <details class="mt-2 group rounded-2xl ring-1 ring-gray-200/80 bg-white/70 overflow-hidden">
        <summary class="cursor-pointer list-none px-5 py-4 flex items-center justify-between gap-3 [&::-webkit-details-marker]:hidden hover:bg-gray-50/80 transition">
            <div>
                <p class="text-sm font-bold text-gray-900">{{ __('borrower.applications_list.closed_title') }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ __('borrower.applications_list.closed_hint') }}
                    · {{ trans_choice('borrower.applications_list.closed_count', count($closedRows), ['count' => count($closedRows)]) }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs font-semibold text-gray-500 group-open:hidden">{{ __('borrower.applications_list.show_closed') }}</span>
                <span class="text-xs font-semibold text-gray-500 hidden group-open:inline">{{ __('borrower.applications_list.hide_closed') }}</span>
                <svg class="size-4 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
            </div>
        </summary>
        <div class="px-5 pb-5 border-t border-gray-100 pt-4">
            @if ($viewMode === 'table')
                @include('site.borrower.loans._applications-table', ['rows' => $closedRows])
            @else
                @include('site.borrower.loans._applications-cards', ['rows' => $closedRows, 'toneClasses' => $toneClasses])
            @endif
        </div>
    </details>
@endif
