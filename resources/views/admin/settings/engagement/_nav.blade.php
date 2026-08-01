@props(['active' => 'index'])

@php
    $links = [
        'index'            => ['Engagement hub', 'admin.settings.engagement'],
        'referral-levels'  => ['Referral levels', 'admin.settings.engagement.referral-levels'],
        'trust-score'      => ['Trust score', 'admin.settings.engagement.trust-score'],
        'milestones'       => ['Community milestones', 'admin.settings.engagement.milestones'],
        'repayment-streak' => ['Repayment streak', 'admin.settings.engagement.repayment-streak'],
        'profile-strength' => ['Profile strength', 'admin.settings.engagement.profile-strength'],
        'loyalty-points'   => ['Loyalty points', 'admin.settings.engagement.loyalty-points'],
        'underwriting'     => ['Underwriting boosts', 'admin.settings.engagement.underwriting'],
        'notifications'    => ['Notifications', 'admin.settings.engagement.notifications'],
        'profile-sections' => ['Profile builder', 'admin.profile-sections.index'],
    ];
@endphp

@include('admin.settings._tabs', ['active' => 'engagement'])

<nav class="mb-6 flex flex-wrap gap-2">
    @foreach ($links as $key => [$label, $route])
        <a href="{{ route($route) }}"
           class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ $active === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
