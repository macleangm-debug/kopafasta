@props(['active' => 'personal', 'customer' => null])

@php
    $tabs = [
        'personal'  => [__('borrower.profile.personal'), 'site.borrower.profile', ['section' => 'personal']],
        'activity'  => [__('borrower.profile.activity'), 'site.borrower.profile', ['section' => 'activity']],
        'residence' => [__('borrower.profile.residence'), 'site.borrower.profile', ['section' => 'residence']],
        'payment'   => [__('borrower.payment_details.tab'), 'site.borrower.profile', ['section' => 'payment']],
        'assets'    => [__('borrower.profile.my_collaterals'), 'site.borrower.profile', ['section' => 'assets']],
    ];
@endphp

<nav class="flex gap-2 mb-6 overflow-x-auto snap-x snap-mandatory scrollbar-none pb-1 -mx-1 px-1 scroll-smooth" aria-label="{{ __('borrower.profile.account_nav') }}">
    @foreach ($tabs as $key => [$label, $route, $params])
        @php $isActive = $active === $key || ($active === 'kyc' && $key === 'activity'); @endphp
        <a href="{{ route($route, $params) }}"
           class="snap-start shrink-0 inline-flex items-center px-3.5 py-2 rounded-xl text-sm font-semibold transition
                  {{ $isActive ? 'bg-brand text-white shadow-sm ring-2 ring-brand' : 'bg-white/80 text-gray-600 ring-1 ring-gray-200/80 hover:bg-brand-muted/40' }}">
            <span>{{ $label }}</span>
        </a>
    @endforeach
</nav>
