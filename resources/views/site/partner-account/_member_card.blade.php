@props(['partner'])

@php
    use App\Services\PartnerCodeService;
    use App\Services\PartnerMembershipService;
    use App\Services\PartnerProfileService;
    use App\Services\ReferralService;

    $codes = app(PartnerCodeService::class);
    $membership = app(PartnerMembershipService::class);
    $profile = app(PartnerProfileService::class);

    $partnerNumber = $codes->ensure($partner);
    $photoUrl = $profile->frontPhotoUrl($partner);
    $initial = strtoupper(substr((string) ($partner->name ?? '?'), 0, 1) ?: '?');
    $category = $partner instanceof \App\Models\Lender ? 'investor' : ($partner->category ?? null);
    $roleKey = 'site.card_verify.roles.'.($category ?: 'partner');
    $role = __($roleKey);
    if ($role === $roleKey) {
        $role = \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) ($category ?: 'Partner')));
    }

    $isCompany = $partner instanceof \App\Models\Partner && $partner->isCompanyApplicant();
    $profileComplete = $profile->isComplete($partner);
    $verified = ($partner->status ?? '') === 'active' && $membership->isActive($partner) && $profileComplete;
    $color = $verified ? 'green' : (($partner->status ?? '') === 'active' ? 'orange' : 'slate');
    $panelClass = match ($color) {
        'green'  => 'kf-premium-panel-bronze',
        'orange' => 'kf-premium-panel-orange',
        default  => 'kf-premium-panel-slate',
    };
    // Verified partners use bronze identity family (not borrower green).
    if ($verified) {
        $panelClass = 'kf-premium-panel-bronze';
    }
    $badgeClass = match ($color) {
        'green'  => 'bg-white text-emerald-800',
        'orange' => 'bg-white text-amber-800',
        default  => 'bg-white text-slate-800',
    };
    $statusLabel = $verified
        ? __('site.card_verify.status.active')
        : __('site.card_verify.status.inactive');

    $base = rtrim(app(ReferralService::class)->appBaseUrl(), '/');
    $verifyUrl = $partnerNumber ? $base.'/v/p/'.rawurlencode($partnerNumber) : null;
    $name = strtoupper((string) ($partner->name ?? ''));
    $shareText = $verifyUrl
        ? __('site.card_verify.share_message', ['name' => $name ?: brand_name(), 'id' => $partnerNumber, 'link' => $verifyUrl])
        : '';
    $whatsappUrl = $shareText !== '' ? 'https://wa.me/?text='.rawurlencode($shareText) : null;

    $qrDataUri = null;
    if ($verifyUrl) {
        $qrPng = @file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=6&data='.urlencode($verifyUrl));
        if (is_string($qrPng) && $qrPng !== '') {
            $qrDataUri = 'data:image/png;base64,'.base64_encode($qrPng);
        }
    }

    $issued = optional($partner->membership_started_at)->format('d M Y') ?? '—';
    $expires = optional($partner->membership_expires_at)->format('d M Y') ?? '—';
    $logoUrl = brand('logo_mark_url') ?: brand('logo_url') ?: 'images/brand/kopafasta-mark.png';
    $membershipActive = $membership->isActive($partner);
    $daysLeft = $membership->daysRemaining($partner);
    $durationDays = $membership->durationDays();
    $progressPct = $durationDays > 0 ? max(0, min(100, ($daysLeft / $durationDays) * 100)) : 0;
    $needsPay = $membership->requiresPayment($partner) && ! $membershipActive;
    $payRouteName = ($partner instanceof \App\Models\Partner && $partner->isAffiliate())
        ? 'site.affiliate.membership.pay'
        : 'site.partner.membership.pay';
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4 mb-6']) }}
     x-data="{
        copied: false,
        linkCopied: false,
        expanded: false,
        async copyId() {
            try {
                await navigator.clipboard.writeText(@js($partnerNumber));
                this.copied = true;
                setTimeout(() => this.copied = false, 1600);
            } catch (e) {}
        },
        copyId() { this.copyId(); },
        async copyVerifyLink() {
            if (! @js($verifyUrl)) return;
            try {
                await navigator.clipboard.writeText(@js($verifyUrl));
                this.linkCopied = true;
                setTimeout(() => this.linkCopied = false, 1600);
            } catch (e) {}
        },
        shareCard() {
            if (navigator.share) {
                navigator.share({ title: @js(brand_name()), text: @js($shareText), url: @js($verifyUrl) }).catch(() => {});
                return;
            }
            this.copyId();
        }
     }">

    @if ($verifyUrl)
        <div class="flex items-center justify-end">
            <button type="button"
                    @click="shareCard()"
                    class="shrink-0 inline-flex items-center justify-center size-11 rounded-xl bg-brand text-white hover:bg-brand/90 shadow-sm"
                    title="{{ __('site.card_verify.share') }}"
                    aria-label="{{ __('site.card_verify.share') }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/>
                </svg>
            </button>
        </div>
    @endif

    <div @class([
             'grid gap-4 items-stretch w-full',
             'md:grid-cols-2' => ($membershipActive && $partner->membership_expires_at) || $needsPay,
         ])>
    <div class="relative {{ $panelClass }} rounded-[1.35rem] p-5 sm:p-6 cursor-pointer"
         role="button"
         tabindex="0"
         @click="expanded = true"
         @keydown.enter="expanded = true"
         aria-label="{{ __('site.card_verify.my_card_title') }}">

        <div class="relative flex items-center justify-between gap-3 mb-5">
            <span class="inline-flex items-center gap-2.5 min-w-0">
                <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" class="h-10 w-auto object-contain shrink-0">
                <span class="text-xl font-bold tracking-tight truncate">{{ brand_name() }}</span>
            </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-[0.14em] {{ $badgeClass }} shrink-0">
                {{ $verified ? $role : $statusLabel }}
            </span>
        </div>

        <div class="relative flex items-start gap-4">
            @if ($isCompany)
                <div class="size-16 sm:size-20 rounded-2xl bg-white/10 ring-2 ring-brand-gold/50 grid place-items-center shrink-0">
                    <svg class="size-8 text-brand-gold" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/>
                    </svg>
                </div>
            @elseif ($photoUrl)
                <img src="{{ $photoUrl }}" alt="" class="size-16 sm:size-20 rounded-2xl object-cover ring-2 ring-brand-gold/50 shrink-0 bg-white/10">
            @else
                <div class="size-16 sm:size-20 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 grid place-items-center text-xl font-bold shrink-0">{{ $initial }}</div>
            @endif
            <div class="min-w-0 pt-0.5 flex-1">
                <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">
                    {{ $role }}@if ($isCompany)<span class="ml-2 text-white/70">· {{ __('site.affiliate.type_company') }}</span>@endif
                </p>
                <h3 class="mt-1 text-lg sm:text-xl font-bold tracking-wide leading-tight break-words">{{ $name ?: '—' }}</h3>
                @if ($isCompany && filled($partner->legal_name) && strcasecmp((string) $partner->legal_name, (string) $partner->name) !== 0)
                    <p class="mt-0.5 text-xs text-white/75 leading-snug">{{ $partner->legal_name }}</p>
                @endif
                <button type="button" @click.stop="copyId()" class="mt-2 font-mono text-sm text-white/90 hover:text-white tracking-wider">
                    {{ $partnerNumber }}
                    <span x-show="copied" x-cloak class="ml-2 text-[10px] uppercase tracking-wider text-brand-gold">{{ __('site.card_verify.copied') }}</span>
                </button>
            </div>
            @if ($qrDataUri)
                <img src="{{ $qrDataUri }}" alt="" class="size-16 sm:size-20 rounded-xl bg-white p-1 shrink-0 ring-1 ring-white/30">
            @endif
        </div>

        @if ($isCompany && (filled($partner->registration_number) || filled($partner->tin)))
            <div class="relative mt-4 grid grid-cols-2 gap-3 text-sm">
                @if (filled($partner->registration_number))
                    <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                        <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">BRELA</p>
                        <p class="mt-1 font-semibold font-mono text-xs break-all">{{ $partner->registration_number }}</p>
                    </div>
                @endif
                @if (filled($partner->tin))
                    <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                        <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">TIN</p>
                        <p class="mt-1 font-semibold font-mono text-xs break-all">{{ $partner->tin }}</p>
                    </div>
                @endif
            </div>
        @endif

        <div class="relative mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.issued_label') }}</p>
                <p class="mt-1 font-semibold tabular-nums">{{ $issued }}</p>
            </div>
            <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.expires_label') }}</p>
                <p class="mt-1 font-semibold tabular-nums">{{ $expires }}</p>
            </div>
        </div>

        </div>

    @if (($membershipActive && $partner->membership_expires_at) || $needsPay)
        <div class="relative overflow-hidden rounded-[1.35rem] bg-white p-6 flex flex-col min-h-[280px] shadow-[0_18px_40px_rgba(8,47,39,0.08)] ring-1 ring-brand/10">
            <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand via-brand-gold to-brand pointer-events-none"></div>
            <div class="relative flex items-center justify-between gap-3">
                <p class="text-[11px] uppercase tracking-[0.16em] text-brand font-semibold">{{ __('borrower.membership.status_title') }}</p>
                @if ($membershipActive)
                    <span class="inline-flex items-center rounded-full bg-brand/10 text-brand px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em] ring-1 ring-brand/15">
                        {{ $verified ? __('site.card_verify.status.verified') : $role }}
                    </span>
                @endif
            </div>
            @if ($membershipActive)
                <div class="relative mt-6 flex items-end gap-3">
                    <span class="text-6xl font-black text-brand leading-none tabular-nums tracking-tight">{{ $daysLeft }}</span>
                    <div class="pb-1.5">
                        <p class="text-base font-bold text-gray-900 leading-none">{{ __('borrower.membership.days_unit') }}</p>
                        <p class="mt-1.5 text-xs text-gray-500 leading-snug">{{ __('borrower.membership.days_remaining_label') }}</p>
                    </div>
                </div>
                <div class="relative mt-6">
                    <div class="flex justify-between text-[10px] uppercase tracking-wide text-gray-500 mb-1.5">
                        <span>{{ __('borrower.membership.year_progress') }}</span>
                        <span class="font-semibold tabular-nums text-brand">{{ $progressPct }}%</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-brand-muted overflow-hidden ring-1 ring-brand/5">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $progressPct }}%"></div>
                    </div>
                </div>
                <dl class="relative mt-auto pt-5 grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl bg-brand-muted/40 px-3 py-2.5 ring-1 ring-brand/10">
                        <dt class="text-gray-500">{{ __('site.card_verify.types.partner') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $role }}</dd>
                    </div>
                    <div class="rounded-xl bg-brand-muted/40 px-3 py-2.5 ring-1 ring-brand/10">
                        <dt class="text-gray-500">{{ __('borrower.membership.access_label') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">
                            {{ $verified ? __('site.card_verify.status.verified') : $statusLabel }}
                        </dd>
                    </div>
                </dl>
            @else
                <p class="relative mt-6 text-sm text-gray-600">{{ __('site.partner_portal.membership_due_title') }}</p>
                <p class="relative mt-2 text-2xl font-black text-brand tabular-nums">{{ format_money($membership->feeFor($partner)) }}</p>
                <a href="{{ route($payRouteName) }}" class="relative mt-auto inline-flex items-center justify-center rounded-xl bg-brand-gold hover:brightness-95 text-brand text-sm font-bold px-4 py-2.5">
                    {{ __('site.partner_portal.cta_pay_membership') }}
                </a>
            @endif
        </div>
    @endif
    </div>

    <div x-show="expanded" x-cloak
         class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 p-4 sm:p-8"
         @keydown.escape.window="expanded = false">
        <button type="button" class="absolute inset-0 cursor-zoom-out" @click="expanded = false" aria-label="Close"></button>
        <div class="relative w-full max-w-3xl" @click.stop>
            <div class="relative {{ $panelClass }} rounded-3xl p-6 sm:p-8">
                <div class="relative flex items-center justify-between gap-3 mb-6">
                    <span class="inline-flex items-center gap-2.5 min-w-0">
                        <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" class="h-11 w-auto object-contain shrink-0">
                        <span class="text-2xl font-bold tracking-tight truncate">{{ brand_name() }}</span>
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-[0.14em] {{ $badgeClass }}">{{ $verified ? $role : $statusLabel }}</span>
                </div>
                <div class="relative flex items-start gap-4">
                    @if ($isCompany)
                        <div class="size-24 rounded-2xl bg-white/10 ring-2 ring-brand-gold/50 grid place-items-center shrink-0">
                            <svg class="size-10 text-brand-gold" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/>
                            </svg>
                        </div>
                    @elseif ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="" class="size-24 rounded-2xl object-cover ring-2 ring-brand-gold/50 shrink-0">
                    @else
                        <div class="size-24 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 grid place-items-center text-3xl font-bold shrink-0">{{ $initial }}</div>
                    @endif
                    <div class="min-w-0 pt-1 flex-1">
                        <p class="text-[11px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ $role }}</p>
                        <h3 class="mt-1.5 text-2xl font-bold tracking-wide leading-[1.1] break-words">{{ $name ?: '—' }}</h3>
                        <button type="button" @click="copyId()" class="mt-2 font-mono text-lg font-bold tracking-wider">
                            {{ $partnerNumber }}
                            <span x-show="copied" x-cloak class="ml-2 text-[10px] uppercase tracking-wider text-brand-gold">{{ __('site.card_verify.copied') }}</span>
                        </button>
                    </div>
                    @if ($qrDataUri)
                        <img src="{{ $qrDataUri }}" alt="" class="size-24 rounded-xl bg-white p-1.5 shrink-0">
                    @endif
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                        <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.issued_label') }}</dt>
                        <dd class="mt-1.5 font-semibold tabular-nums">{{ $issued }}</dd>
                    </div>
                    <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                        <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.expires_label') }}</dt>
                        <dd class="mt-1.5 font-semibold tabular-nums">{{ $expires }}</dd>
                    </div>
                </dl>
            </div>
            <button type="button" @click="expanded = false" class="absolute top-3 right-3 z-10 size-10 rounded-full bg-black/40 text-white grid place-items-center" aria-label="{{ __('borrower.membership.close_card') }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
    </div>
</div>
