@php
    $onboardingBanner = app(\App\Services\ApplicationRequirementsService::class)->onboardingBanner($customer);
@endphp

@if ($onboardingBanner['show'] ?? false)
    <x-site.onboarding-hero-banner :banner="$onboardingBanner" />
@endif
