@props([
    'customer',
    'cta' => 'card', // card | profile | none
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
    $myCardUrl = route('site.borrower.profile', ['section' => 'membership']);
    $profileUrl = route('site.borrower.profile');
    $ctaUrl = $cta === 'profile' ? $profileUrl : ($cta === 'card' ? $myCardUrl : null);
    $ctaLabel = $cta === 'profile'
        ? __('borrower.profile.panel_profile')
        : ($cta === 'card' ? __('borrower.membership.my_card') : null);
@endphp

{{-- Canonical profile hero — same shell/language as Dashboard Hero --}}
<section class="mb-6 rounded-2xl p-5 sm:p-6 relative overflow-hidden kf-premium-panel">
    <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
    <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.45),_transparent_55%)] pointer-events-none" aria-hidden="true"></div>

    <div class="relative">
        <div class="flex items-start justify-between gap-3">
            <x-site.brand-mark size="sm" variant="light" />
            <x-site.grade-badge :grade="$grade" :plus="$plusActive" size="lg" class="shrink-0" />
        </div>

        <div class="mt-5 space-y-4 max-w-2xl">
            <div class="flex items-start gap-3 sm:gap-4 min-w-0">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="" class="size-14 sm:size-16 rounded-2xl object-cover ring-2 ring-brand-gold/50 bg-white/10 shrink-0">
                @else
                    <div class="size-14 sm:size-16 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 text-white grid place-items-center text-xl font-bold shrink-0">
                        {{ $initial }}
                    </div>
                @endif
                <div class="min-w-0 pt-0.5">
                    <p class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white break-words">{{ $displayName }}</p>
                    @if ($memberNo !== '')
                        <p class="text-sm font-mono mt-1.5 text-white/75 break-all">{{ $memberNo }}</p>
                    @endif
                </div>
            </div>

            @if ($ctaUrl && $ctaLabel)
                <div class="flex flex-wrap gap-2">
                    <a href="{{ $ctaUrl }}"
                       data-loading="click"
                       data-kf-motion="pop"
                       class="inline-flex justify-center font-semibold px-5 py-2.5 rounded-xl text-sm transition bg-white text-brand hover:bg-white/90 shadow-sm">
                        {{ $ctaLabel }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</section>
