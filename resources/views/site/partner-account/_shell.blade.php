@props([
    'partner',
    'portal',
    'active' => 'hub',
    'profileRoute',
])

@php
    $hubUrl = route($profileRoute);
    $cardUrl = route($profileRoute, ['section' => 'card']);
    $profile = app(\App\Services\PartnerProfileService::class);
    $displayName = (string) ($partner->name ?? '');
    $initial = strtoupper(substr(trim($displayName), 0, 1) ?: '?');
    $photoUrl = $profile->frontPhotoUrl($partner);
    $partnerNumber = $partner instanceof \App\Models\Partner
        ? app(\App\Services\PartnerCodeService::class)->ensure($partner)
        : (string) ($partner->code ?? '');
    $category = $partner instanceof \App\Models\Lender ? 'investor' : ($partner->category ?? null);
    $roleKey = 'site.card_verify.roles.'.($category ?: 'partner');
    $role = __($roleKey);
    if ($role === $roleKey) {
        $role = \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) ($category ?: 'Partner')));
    }
    $completionPercent = $profile->completionPercent($partner);
    $cardLabel = __('site.card_verify.my_card_title');
    $profileLabel = __('site.partner_portal.nav_profile');
    $isAffiliatePortal = $portal === 'affiliate' && $partner instanceof \App\Models\Partner && $partner->isAffiliate();
    $remainingCount = $isAffiliatePortal ? $profile->remainingItemCount($partner) : null;
    $firstIncomplete = $isAffiliatePortal ? $profile->firstIncompleteSection($partner) : null;
    $affiliateBadge = $isAffiliatePortal
        ? ($partner->isPremiumAffiliate()
            ? (app(\App\Services\AffiliateSettingsService::class)->premiumBadgeLabel() ?: __('site.affiliate_portal.hero_type_premium'))
            : __('site.affiliate_portal.hero_type_standard'))
        : $role;
@endphp

@if ($isAffiliatePortal)
    <x-site.account-shell-hero
        mode="identity"
        :display-name="$displayName"
        :member-no="$partnerNumber"
        :photo-url="$photoUrl"
        :initial="$initial"
        :show-grade-badge="false"
        :badge-label="$affiliateBadge"
        :completion-percent="$completionPercent"
        :remaining-count="$remainingCount"
        :completion-cta-url="$firstIncomplete ? route($profileRoute, ['section' => $firstIncomplete]) : null"
        :completion-cta-label="__('borrower.profile.hero_completion_cta')"
        :cta-url="$completionPercent >= 100 ? $cardUrl : null"
        :cta-label="$completionPercent >= 100 ? $cardLabel : null"
    />
@endif

@if ($active === 'hub')
    @unless ($isAffiliatePortal)
    <x-site.account-shell-hero
        mode="identity"
        :display-name="$displayName"
        :member-no="$partnerNumber"
        :photo-url="$photoUrl"
        :initial="$initial"
        :show-grade-badge="false"
        :badge-label="$role"
        :completion-percent="$completionPercent"
        :cta-url="$cardUrl"
        :cta-label="$cardLabel"
    />
    @endunless
    @include('site.partner-account._overview', ['partner' => $partner, 'profileRoute' => $profileRoute, 'portal' => $portal])
@elseif ($active === 'card')
    @unless ($isAffiliatePortal)
    <x-site.account-shell-hero
        mode="contextual"
        :title="$cardLabel"
        :cta-url="$hubUrl"
        :cta-label="$profileLabel"
    />
    @endunless
    @include('site.partner-account._member_card', ['partner' => $partner])
@elseif ($active === 'membership')
    @unless ($isAffiliatePortal)
    <x-site.account-shell-hero
        mode="contextual"
        :title="__('site.card_verify.my_card_title')"
        :cta-url="$hubUrl"
        :cta-label="$profileLabel"
    />
    @endunless
@else
    @include('site.partner-account._tabs', ['active' => $active, 'partner' => $partner, 'profileRoute' => $profileRoute, 'portal' => $portal])
@endif
