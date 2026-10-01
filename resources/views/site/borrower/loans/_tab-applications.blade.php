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
    $groupInvite = app(\App\Services\GroupMemberApplicationService::class)
        ->dashboardBanner(auth()->user()?->customer);
@endphp

<div class="mb-6">
    <h2 class="text-lg font-semibold">{{ __('borrower.applications_list.active_title') }}</h2>
    <p class="text-sm text-gray-500">{{ __('borrower.applications_list.active_hint') }}</p>
</div>

@if (! empty($groupInvite['show']))
    <div class="mb-6 glass-card p-5 ring-1 ring-brand/15" data-kf-share="kf-group-invite">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.group.loan_label') }}</p>
                <p class="text-lg font-bold text-gray-900 tracking-tight mt-0.5 leading-snug">{{ $groupInvite['title'] }}</p>
                @if (! empty($groupInvite['reference']))
                    <p class="font-mono text-xs text-gray-500 mt-1">{{ $groupInvite['reference'] }}</p>
                @endif
            </div>
            <span class="shrink-0 text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-900">
                {{ __('borrower.guarantor.action_required') }}
            </span>
        </div>
        <p class="text-sm text-gray-600 mb-4">{{ $groupInvite['message'] }}</p>
        <a href="{{ $groupInvite['cta_url'] }}"
           class="inline-flex items-center justify-center w-full sm:w-auto font-bold px-5 py-3 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
            {{ $groupInvite['cta_label'] ?? __('borrower.applications_list.view') }}
        </a>
    </div>
@endif

@if ($activeRows === [] && empty($groupInvite['show']))
    <div class="mb-8">
        <x-site.empty-state
            icon="📋"
            :title="__('borrower.applications_list.empty_active_title')"
            :description="__('borrower.applications_list.empty_active_desc')"
            :action-label="__('borrower.applications_list.empty_action')"
            :action-url="route('site.borrower.loan-products')"
        />
    </div>
@elseif ($activeRows !== [])
    <div class="lg:hidden">
        @include('site.borrower.loans._applications-cards', ['rows' => $activeRows, 'toneClasses' => $toneClasses])
    </div>
    <div class="hidden lg:block">
        @include('site.borrower.loans._applications-table', ['rows' => $activeRows])
    </div>
@endif
