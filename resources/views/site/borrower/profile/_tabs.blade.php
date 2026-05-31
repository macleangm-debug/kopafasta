@props(['active' => 'personal'])

@php
    $tabs = [
        'personal'  => ['Personal Information', 'site.borrower.profile', ['section' => 'personal']],
        'activity'  => ['Activity Information', 'site.borrower.profile', ['section' => 'activity']],
        'residence' => ['Residence Information', 'site.borrower.profile', ['section' => 'residence']],
        'kyc'       => ['KYC Information', 'site.borrower.profile', ['section' => 'kyc']],
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
