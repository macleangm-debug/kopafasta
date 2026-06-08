@include('site.borrower.profile._profile_completion_summary', ['customer' => $customer, 'completionSummary' => $completionSummary ?? null])
@include('site.borrower.profile._verification_status', ['customer' => $customer])

@php
    $onboardingBanner = app(\App\Services\ApplicationRequirementsService::class)->onboardingBanner($customer);
@endphp

@if ($onboardingBanner['show'] ?? false)
    <x-site.onboarding-hero-banner :banner="$onboardingBanner" />
@endif
