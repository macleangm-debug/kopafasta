@props(['active' => 'personal'])

@php
    $tabs = [
        'personal'  => [__('borrower.profile.personal'), 'site.borrower.profile', ['section' => 'personal']],
        'activity'  => [__('borrower.profile.activity'), 'site.borrower.profile', ['section' => 'activity']],
        'residence' => [__('borrower.profile.residence'), 'site.borrower.profile', ['section' => 'residence']],
        'kin'       => [__('borrower.profile.kin'), 'site.borrower.profile', ['section' => 'kin']],
        'kyc'       => [__('borrower.profile.kyc'), 'site.borrower.profile', ['section' => 'kyc']],
        'security'  => [__('borrower.profile.security'), 'site.borrower.profile', ['section' => 'security']],
    ];
@endphp

<nav class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-3">
    @foreach ($tabs as $key => [$label, $route, $params])
        @php $isActive = $active === $key; @endphp
        <a href="{{ route($route, $params) }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $isActive ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
