@props(['active' => 'personal', 'customer' => null])

@php
    $tabs = [
        'personal'  => [__('borrower.profile.personal'), 'site.borrower.profile', ['section' => 'personal']],
        'activity'  => [__('borrower.profile.activity'), 'site.borrower.profile', ['section' => 'activity']],
        'residence' => [__('borrower.profile.residence'), 'site.borrower.profile', ['section' => 'residence']],
        'kyc'       => [__('borrower.profile.kyc'), 'site.borrower.profile', ['section' => 'kyc']],
        'payment'   => [__('borrower.payment_details.tab'), 'site.borrower.profile', ['section' => 'payment']],
        'assets'    => [__('borrower.profile.my_collaterals'), 'site.borrower.profile', ['section' => 'assets']],
    ];
    $statuses = $customer
        ? app(\App\Services\ProfileCompletionService::class)->tabStatuses($customer)
        : [];
@endphp

<nav class="flex gap-2 mb-6 overflow-x-auto snap-x snap-mandatory scrollbar-none pb-1 -mx-1 px-1 scroll-smooth" aria-label="{{ __('borrower.profile.account_nav') }}">
    @foreach ($tabs as $key => [$label, $route, $params])
        @php
            $isActive = $active === $key;
            $tab = $statuses[$key] ?? null;
            $isComplete = $tab['complete'] ?? null;
            $isRequired = $tab['required'] ?? true;
            $inactiveRing = $isComplete === true
                ? 'ring-emerald-300/90 bg-emerald-50/90 text-emerald-900'
                : ($isComplete === false && $isRequired
                    ? 'ring-red-300/90 bg-red-50/70 text-red-900'
                    : 'bg-white/80 text-gray-600 ring-gray-200/80 hover:bg-brand-muted/40');
        @endphp
        <a href="{{ route($route, $params) }}"
           class="snap-start shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-semibold transition
                  {{ $isActive ? 'bg-brand text-white shadow-sm ring-2 ring-brand' : $inactiveRing }}">
            @if ($tab && $isComplete !== null)
                <span @class([
                    'size-2 rounded-full shrink-0',
                    $isComplete ? 'bg-emerald-500' : ($isRequired ? 'bg-red-500' : 'bg-gray-300'),
                    $isActive ? 'ring-2 ring-white/50' : '',
                ])></span>
            @endif
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
