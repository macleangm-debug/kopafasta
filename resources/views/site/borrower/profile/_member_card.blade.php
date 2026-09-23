@props([
    'customer',
    'cta' => 'card', // card | profile | none
    'showCompletion' => true,
])

@php
    use App\Support\MemberNumberFormatter;

    $portalContext = app(\App\Services\PortalContextService::class);
    $displayName = $portalContext->displayName($customer);
    $photoUrl = app(\App\Services\FaceVerificationService::class)->avatarUrl($customer);
    $initial = strtoupper(substr(trim((string) $displayName), 0, 1) ?: '?');
    $memberNo = MemberNumberFormatter::display($customer->member_no)
        ?: (string) ($customer->customer_number ?? '');
    $plusActive = app(\App\Services\Plus\PlusService::class)->isActive($customer);
    $grade = $customer->grade ?? 'bronze';
    $completionPercent = (int) (app(\App\Services\ProfileCompletionService::class)->calculate($customer)['percent'] ?? 0);
    $showCompletion = (bool) $showCompletion;
    // Already on Profile — percent stays on the hero; CTA is Kopafasta Card only.
    $completionCtaUrl = null;
    $completionCtaLabel = null;
    $myCardUrl = route('site.borrower.profile', ['section' => 'membership']);
    $profileUrl = route('site.borrower.profile');
@endphp

@if ($cta === 'profile')
    <x-site.account-shell-hero
        mode="contextual"
        :title="__('borrower.membership.my_card')"
        :cta-url="$profileUrl"
        :cta-label="__('borrower.profile.panel_profile')"
    />
@else
    <x-site.account-shell-hero
        mode="identity"
        :display-name="$displayName"
        :member-no="$memberNo"
        :photo-url="$photoUrl"
        :initial="$initial"
        :grade="$grade"
        :plus="$plusActive"
        :completion-percent="$showCompletion ? $completionPercent : null"
        :completion-cta-url="$completionCtaUrl"
        :completion-cta-label="$completionCtaLabel"
        :cta-url="$cta === 'card' ? $myCardUrl : null"
        :cta-label="$cta === 'card' ? __('borrower.membership.my_card') : null"
    />
@endif
