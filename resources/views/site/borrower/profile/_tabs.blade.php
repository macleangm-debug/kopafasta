@props(['active' => 'personal'])

@php
    $tabs = [
        'personal'  => [__('borrower.profile.personal'), 'site.borrower.profile', ['section' => 'personal']],
        'activity'  => [__('borrower.profile.activity'), 'site.borrower.profile', ['section' => 'activity']],
        'residence' => [__('borrower.profile.residence'), 'site.borrower.profile', ['section' => 'residence']],
        'kyc'       => [__('borrower.profile.kyc'), 'site.borrower.profile', ['section' => 'kyc']],
        'security'  => [__('borrower.profile.security'), 'site.borrower.profile', ['section' => 'security']],
        'payment'   => [__('borrower.payment_details.tab'), 'site.borrower.profile', ['section' => 'payment']],
        'assets'    => [__('borrower.profile.my_assets'), 'site.borrower.profile', ['section' => 'assets']],
    ];
@endphp

<nav class="flex gap-2 mb-6 overflow-x-auto scrollbar-none pb-1 -mx-1 px-1">
    @foreach ($tabs as $key => [$label, $route, $params])
        @php $isActive = $active === $key; @endphp
        <a href="{{ route($route, $params) }}"
           class="shrink-0 px-3.5 py-2 rounded-xl text-sm font-semibold transition {{ $isActive ? 'bg-brand text-white shadow-sm' : 'bg-white/80 text-gray-600 ring-1 ring-gray-200/80 hover:bg-brand-muted/40' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
