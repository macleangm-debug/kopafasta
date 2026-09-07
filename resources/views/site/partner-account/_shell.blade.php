@props([
    'partner',
    'portal',
    'active' => 'hub',
    'profileRoute',
])

@php
    use App\Services\PartnerCodeService;
    use App\Services\PartnerMembershipService;
    use App\Services\PartnerProfileService;

    $codes = app(PartnerCodeService::class);
    $membership = app(PartnerMembershipService::class);
    $profile = app(PartnerProfileService::class);

    $partnerNumber = $codes->ensure($partner);
    $photoUrl = $profile->frontPhotoUrl($partner);
    $displayName = (string) ($partner->name ?? '');
    $initial = strtoupper(substr($displayName !== '' ? $displayName : '?', 0, 1) ?: '?');
    $category = $partner instanceof \App\Models\Lender ? 'investor' : ($partner->category ?? null);
    $roleKey = 'site.card_verify.roles.'.($category ?: 'partner');
    $role = __($roleKey);
    if ($role === $roleKey) {
        $role = \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) ($category ?: 'Partner')));
    }
    $profileComplete = $profile->isComplete($partner);
    $completionPercent = $profile->completionPercent($partner);
    $verified = ($partner->status ?? '') === 'active' && $membership->isActive($partner) && $profileComplete;
    $badgeLabel = $verified ? $role : __('site.card_verify.status.inactive');
    $hubUrl = route($profileRoute);
    $personalUrl = route($profileRoute, ['section' => 'personal']);
@endphp

@if ($active === 'hub')
    <x-site.account-shell-hero
        mode="contextual"
        :title="__('borrower.membership.my_card')"
        :cta-url="$personalUrl"
        :cta-label="__('borrower.profile.panel_profile')"
    />
@elseif ($active === 'membership')
    <x-site.account-shell-hero
        mode="contextual"
        :title="__('borrower.membership.my_card')"
        :cta-url="$hubUrl"
        :cta-label="__('borrower.profile.panel_profile')"
    />
@else
    <x-site.account-shell-hero
        mode="identity"
        :display-name="$displayName"
        :member-no="$partnerNumber"
        :photo-url="$photoUrl"
        :initial="$initial"
        :show-grade-badge="false"
        :badge-label="$badgeLabel"
        :completion-percent="$completionPercent"
        :cta-url="$hubUrl"
        :cta-label="__('borrower.membership.my_card')"
    />
@endif

@if ($active === 'hub')
    @include('site.partner-account._member_card', ['partner' => $partner])
    @include('site.partner-account._overview', ['partner' => $partner, 'profileRoute' => $profileRoute])
@elseif ($active === 'membership')
    {{-- Membership commercial pages render their own body; hero above is contextual. --}}
@else
    @include('site.partner-account._tabs', ['active' => $active, 'partner' => $partner, 'profileRoute' => $profileRoute, 'portal' => $portal])
@endif
