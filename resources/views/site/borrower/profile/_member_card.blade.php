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
@endphp

<section class="relative overflow-hidden rounded-[1.5rem] mb-6 kf-premium-panel">
    <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.45),_transparent_55%)] pointer-events-none"></div>
    <div class="relative p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex items-center gap-4 min-w-0 flex-1">
            <div class="shrink-0">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="" class="size-16 sm:size-20 rounded-2xl object-cover ring-2 ring-brand-gold/50 bg-white/10">
                @else
                    <div class="size-16 sm:size-20 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 text-white grid place-items-center text-xl font-bold">
                        {{ $initial }}
                    </div>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <h2 class="text-lg sm:text-xl font-bold text-white leading-tight break-words">{{ $displayName }}</h2>
                @if ($memberNo !== '')
                    <p class="mt-1 text-sm font-mono text-white/80 tracking-wide break-all">{{ $memberNo }}</p>
                @endif
                <div class="mt-2.5">
                    <x-site.grade-badge :grade="$grade" :plus="$plusActive" size="sm" />
                </div>
            </div>
        </div>

        @if ($cta === 'card')
            <a href="{{ $myCardUrl }}"
               data-kf-motion="pop"
               class="shrink-0 inline-flex items-center justify-center self-start sm:self-auto rounded-full bg-brand-gold hover:brightness-95 text-brand font-bold px-4 py-2.5 text-sm shadow-sm">
                {{ __('borrower.membership.my_card') }} →
            </a>
        @elseif ($cta === 'profile')
            <a href="{{ $profileUrl }}"
               data-kf-motion="pop"
               class="shrink-0 inline-flex items-center justify-center self-start sm:self-auto rounded-full bg-white/15 hover:bg-white/25 text-white font-bold px-4 py-2.5 text-sm ring-1 ring-white/25">
                {{ __('borrower.profile.panel_profile') }} →
            </a>
        @endif
    </div>
</section>
