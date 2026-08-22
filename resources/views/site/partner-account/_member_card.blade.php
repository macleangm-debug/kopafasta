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
    $bgGradient = match ($color) {
        'green'  => 'from-[#0B3D32] via-[#127A5F] to-[#082f27]',
        'orange' => 'from-[#7a4a10] via-[#b45309] to-[#5c370c]',
        default  => 'from-slate-700 via-slate-600 to-slate-800',
    };
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
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4 mb-6']) }}
     x-data="{
        copied: false,
        async copyId() {
            try {
                await navigator.clipboard.writeText(@js($partnerNumber));
                this.copied = true;
                setTimeout(() => this.copied = false, 1600);
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

    <div class="relative overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-[0_20px_50px_rgba(8,47,39,0.28)] p-5 sm:p-6 ring-1 ring-brand-gold/35">
        <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold pointer-events-none"></div>

        <div class="relative flex items-center justify-between gap-3 mb-5">
            <span class="inline-flex items-center gap-2.5 min-w-0">
                <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" class="h-10 w-auto object-contain shrink-0">
                <span class="text-xl font-bold tracking-tight truncate">{{ brand_name() }}</span>
            </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-[0.14em] {{ $badgeClass }} shrink-0">
                {{ $statusLabel }}
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
                <button type="button" @click="copyId()" class="mt-2 font-mono text-sm text-white/90 hover:text-white tracking-wider">
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

        @if ($verifyUrl)
            <div class="relative mt-4 flex flex-wrap gap-2">
                <a href="{{ $verifyUrl }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center rounded-xl bg-brand-gold hover:brightness-95 text-brand text-xs font-bold px-3.5 py-2">
                    {{ __('site.card_verify.preview') }}
                </a>
                @if ($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-semibold px-3.5 py-2 ring-1 ring-white/20">
                        WhatsApp
                    </a>
                @endif
                <a href="{{ route('site.card.verify') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-semibold px-3.5 py-2 ring-1 ring-white/20">
                    {{ __('site.card_verify.verify_another') }}
                </a>
            </div>
        @endif
    </div>
</div>
