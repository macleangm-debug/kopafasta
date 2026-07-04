@if (app(\App\Services\ProfileCompletionService::class)->identityRequiredDuringProfile())
    @include('site.borrower.profile._verification_status', ['customer' => $customer])
@endif

@php
    $onboardingBanner = app(\App\Services\ApplicationRequirementsService::class)->onboardingBanner($customer);
@endphp

@if ($onboardingBanner['show'] ?? false)
    <x-site.onboarding-hero-banner :banner="$onboardingBanner" />
@endif
