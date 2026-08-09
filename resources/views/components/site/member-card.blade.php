@props(['customer', 'referralCode' => null, 'referralLink' => null])
@php
    /** @var \App\Models\Customer $customer */
    use App\Support\MemberNumberFormatter;
    use App\Services\ReferralService;

    $color = $customer->membershipStatusColor();
    $label = $customer->membershipStatusLabel();

    $bgGradient = match ($color) {
        'green'  => 'from-[#0B3D32] via-[#127A5F] to-[#082f27]',
        'orange' => 'from-[#7a4a10] via-[#b45309] to-[#5c370c]',
        'red'    => 'from-[#7f1d1d] via-[#b91c1c] to-[#450a0a]',
        default  => 'from-slate-700 via-slate-600 to-slate-800',
    };
    $badgeClass = match ($color) {
        'green'  => 'bg-white text-emerald-800',
        'orange' => 'bg-white text-amber-800',
        'red'    => 'bg-white text-rose-800',
        default  => 'bg-white text-slate-800',
    };

    $issued  = optional($customer->membership_issued_at)->format('d M Y') ?? '—';
    $expires = optional($customer->membership_expires_at)->format('d M Y') ?? '—';
    $name    = strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')));
    $memberNoRaw = MemberNumberFormatter::raw($customer->member_no);
    $memberNoDisplay = MemberNumberFormatter::display($customer->member_no);
    $base = rtrim(app(ReferralService::class)->appBaseUrl(), '/');
    $verifyUrl = $memberNoRaw ? $base.'/v/'.rawurlencode($memberNoRaw) : null;
    $shareText = $verifyUrl
        ? __('borrower.membership.share_message', ['name' => $name ?: brand_name(), 'member' => $memberNoDisplay, 'link' => $verifyUrl])
        : '';
    $whatsappUrl = $shareText !== '' ? 'https://wa.me/?text='.rawurlencode($shareText) : null;
    $facePhoto = app(\App\Services\FaceVerificationService::class)->latestByAngle($customer)->get('front');
    $photoUrl = $facePhoto?->file_path ? asset('storage/'.$facePhoto->file_path) : null;
    $initial = strtoupper(substr(trim($customer->first_name ?? ''), 0, 1) ?: '?');
    $days    = max(0, (int) $customer->membershipDaysRemaining());
    $duration = (int) (\App\Services\MembershipService::config()['duration_days'] ?? 365);
    $pct     = $duration > 0 ? max(0, min(100, ($days / $duration) * 100)) : 0;
    $logoUrl = brand('logo_mark_url') ?: brand('logo_url') ?: 'images/brand/kopafasta-mark.png';

    $qrDataUri = null;
    if ($verifyUrl) {
        $qrPng = @file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=6&data='.urlencode($verifyUrl));
        if (is_string($qrPng) && $qrPng !== '') {
            $qrDataUri = 'data:image/png;base64,'.base64_encode($qrPng);
        }
    }

    $urgencyLabel = match (true) {
        ! $customer->hasMembership() => __('borrower.membership.pay_registration'),
        $customer->isMembershipExpired() => __('borrower.membership.expired_renew'),
        $customer->isMembershipExpiringSoon(30) => __('borrower.membership.expiring_soon'),
        default => __('borrower.membership.days_remaining_label'),
    };
    $barClass = match ($color) {
        'green'  => 'bg-emerald-500',
        'orange' => 'bg-amber-500',
        'red'    => 'bg-rose-500',
        default  => 'bg-slate-500',
    };
@endphp

<div {{ $attributes->merge(['class' => 'space-y-5']) }}
     x-data="memberCardActions(@js($memberNoRaw), @js($shareText), @js($verifyUrl), @js($whatsappUrl))">

    <div class="flex items-center justify-between gap-3">
        <h2 class="min-w-0 text-xl sm:text-2xl font-bold tracking-tight text-gray-900">{{ __('borrower.membership.you_are_member') }}</h2>
        @if ($verifyUrl)
            <button type="button"
                    @click="shareMembership()"
                    class="shrink-0 inline-flex items-center justify-center size-11 rounded-xl bg-brand text-white hover:bg-brand/90 shadow-sm"
                    title="{{ __('borrower.membership.share') }}"
                    aria-label="{{ __('borrower.membership.share') }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/>
                </svg>
            </button>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch">
        {{-- Card face --}}
        <div
            class="relative w-full text-left overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-[0_24px_60px_rgba(8,47,39,0.45)] p-5 sm:p-6 ring-1 ring-brand-gold/35 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold"
            role="button"
            tabindex="0"
            @click="expanded = true"
            @keydown.enter="expanded = true"
            aria-label="{{ __('borrower.membership.my_card') }}">
            <div class="absolute inset-[1px] rounded-[1.28rem] ring-1 ring-white/10 pointer-events-none"></div>
            <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-brand-gold/10 pointer-events-none"></div>
            <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold pointer-events-none"></div>

            <div class="relative flex items-center justify-between gap-3 mb-5">
                <span class="inline-flex items-center gap-2.5 min-w-0">
                    <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" aria-hidden="true" class="h-10 sm:h-11 w-auto object-contain shrink-0">
                    <span class="text-xl sm:text-2xl font-bold tracking-tight text-white leading-none truncate">{{ brand_name() }}</span>
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-[0.14em] {{ $badgeClass }} shrink-0 ring-1 ring-brand-gold/30">
                    {{ $label }}
                </span>
            </div>

            <div class="relative flex items-start gap-4">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="" class="size-16 sm:size-20 rounded-2xl object-cover ring-2 ring-brand-gold/50 bg-white/10 shrink-0">
                @else
                    <div class="size-16 sm:size-20 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 grid place-items-center text-2xl font-bold shrink-0">{{ $initial }}</div>
                @endif
                <div class="min-w-0 pt-0.5">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold leading-none">{{ __('borrower.membership.member_role') }}</p>
                    <h3 class="mt-1 text-lg sm:text-xl font-bold tracking-wide leading-[1.1] break-words">{{ $name ?: '—' }}</h3>
                </div>
            </div>

            <div class="relative mt-5 rounded-2xl bg-black/25 px-4 py-4 ring-1 ring-white/15">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-white/55 mb-2">{{ __('borrower.membership.member_no_label') }}</p>
                        <p class="font-mono text-lg sm:text-xl font-bold tracking-[0.12em] leading-tight break-all">{{ $memberNoDisplay }}</p>
                    </div>
                    @if ($memberNoRaw)
                        <span
                            role="button"
                            tabindex="0"
                            @click.stop="navigator.clipboard.writeText(copyNo).then(() => { copied = true; setTimeout(() => copied = false, 2500); })"
                            class="shrink-0 inline-flex items-center justify-center size-10 rounded-xl bg-white/10 hover:bg-white/20 ring-1 ring-white/20 cursor-pointer">
                            <svg x-show="!copied" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            <svg x-show="copied" x-cloak class="size-5 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                    @endif
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-3 relative">
                <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10 min-h-[4.5rem] flex flex-col">
                    <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.issued_label') }}</dt>
                    <dd class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $issued }}</dd>
                </div>
                <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10 min-h-[4.5rem] flex flex-col">
                    <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.expires_label') }}</dt>
                    <dd class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $expires }}</dd>
                </div>
            </dl>

            @if ($verifyUrl && $qrDataUri)
                <div class="relative mt-4 flex items-center gap-4 rounded-2xl bg-black/25 px-4 py-3.5 ring-1 ring-white/15">
                    <img src="{{ $qrDataUri }}" alt="" class="size-[64px] rounded-xl bg-white p-1.5 shrink-0">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.membership.scan_to_verify') }}</p>
                </div>
            @endif
        </div>

        {{-- Days remaining --}}
        <div class="relative overflow-hidden rounded-[1.35rem] ring-1 ring-gray-200 bg-white p-6 flex flex-col min-h-[280px] shadow-sm">
            <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500 font-semibold">{{ __('borrower.membership.status_title') }}</p>
            <div class="mt-6 flex items-end gap-3">
                <span class="text-6xl font-black text-gray-900 leading-none tabular-nums tracking-tight">{{ $days }}</span>
                <div class="pb-1.5">
                    <p class="text-base font-bold text-gray-800 leading-none">{{ __('borrower.membership.days_unit') }}</p>
                    <p class="mt-1.5 text-xs text-gray-500 leading-snug">{{ $urgencyLabel }}</p>
                </div>
            </div>
            <div class="mt-6">
                <div class="flex justify-between text-[10px] uppercase tracking-wide text-gray-500 mb-1.5">
                    <span>{{ __('borrower.membership.year_progress') }}</span>
                    <span class="font-semibold tabular-nums">{{ format_number($pct, 0) }}%</span>
                </div>
                <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full {{ $barClass }}" style="width: {{ $pct }}%"></div>
                </div>
            </div>
            <dl class="mt-auto pt-6 grid grid-cols-2 gap-3 text-xs">
                <div class="rounded-xl bg-gray-50 px-3 py-2.5 ring-1 ring-gray-100">
                    <dt class="text-gray-500">{{ __('borrower.membership.issued_label') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-0.5 tabular-nums">{{ $issued }}</dd>
                </div>
                <div class="rounded-xl bg-gray-50 px-3 py-2.5 ring-1 ring-gray-100">
                    <dt class="text-gray-500">{{ __('borrower.membership.expires_label') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-0.5 tabular-nums">{{ $expires }}</dd>
                </div>
            </dl>
            @if (! $customer->hasMembership())
                <a href="{{ route('site.membership.renew') }}" class="mt-4 inline-flex items-center justify-center bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl">{{ __('borrower.membership.pay_registration') }}</a>
            @elseif ($customer->isMembershipExpired())
                <a href="{{ route('site.membership.renew') }}" class="mt-4 inline-flex items-center justify-center bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl">{{ __('borrower.membership.renew_now') }}</a>
            @elseif ($customer->isMembershipExpiringSoon(30))
                <a href="{{ route('site.membership.renew') }}" class="mt-4 inline-flex items-center justify-center bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl">{{ __('borrower.membership.renew_early') }}</a>
            @endif
        </div>
    </div>

    @if ($verifyUrl)
        <div class="flex flex-wrap gap-2">
            <button type="button"
                    @click="copyVerifyLink()"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3.5 py-2.5 text-xs font-semibold">
                {{ __('borrower.membership.copy_verify_link') }}
            </button>
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 rounded-xl bg-brand-gold text-brand hover:brightness-95 px-3.5 py-2.5 text-xs font-bold">
                {{ __('borrower.membership.share_whatsapp') }}
            </a>
            <button type="button"
                    @click="shareMembership()"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-white text-brand ring-1 ring-brand/20 hover:bg-brand/5 px-3.5 py-2.5 text-xs font-semibold">
                {{ __('borrower.membership.share') }}
            </button>
        </div>
        <p x-show="shareCopied" x-cloak class="text-xs font-medium text-brand">{{ __('borrower.membership.link_copied') }}</p>
    @endif

    {{-- Referral prompt --}}
    <section class="rounded-[1.35rem] bg-brand text-white p-5 sm:p-6 shadow-lg relative overflow-hidden">
        <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-[0.16em] text-brand-gold font-semibold">{{ __('borrower.membership.refer_eyebrow') }}</p>
                <h3 class="mt-1.5 text-lg font-bold tracking-tight">{{ __('borrower.membership.refer_title') }}</h3>
                <p class="mt-1.5 text-sm text-white/80 max-w-xl">{{ __('borrower.membership.refer_body') }}</p>
            </div>
            <a href="{{ route('site.borrower.engagement', ['tab' => 'referrals']) }}"
               class="shrink-0 inline-flex items-center justify-center bg-brand-gold hover:brightness-95 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                {{ __('borrower.membership.refer_cta') }}
            </a>
        </div>
    </section>

    {{-- Expand --}}
    <div x-show="expanded" x-cloak
         class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 p-4 sm:p-8"
         @keydown.escape.window="expanded = false">
        <button type="button" class="absolute inset-0 cursor-zoom-out" @click="expanded = false" aria-label="Close"></button>
        <div class="relative w-full max-w-md" @click.stop>
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br {{ $bgGradient }} text-white shadow-2xl p-6 sm:p-8 ring-1 ring-brand-gold/40">
                <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold"></div>
                <div class="relative flex items-center justify-between gap-3 mb-6">
                    <span class="inline-flex items-center gap-2.5">
                        <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" class="h-11 w-auto object-contain">
                        <span class="text-2xl font-bold tracking-tight">{{ brand_name() }}</span>
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-[0.14em] {{ $badgeClass }}">{{ $label }}</span>
                </div>
                <div class="relative flex items-start gap-4">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="" class="size-24 rounded-2xl object-cover ring-2 ring-brand-gold/50">
                    @else
                        <div class="size-24 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 grid place-items-center text-3xl font-bold">{{ $initial }}</div>
                    @endif
                    <div class="min-w-0 pt-1">
                        <p class="text-[11px] uppercase tracking-[0.2em] text-brand-gold font-semibold leading-none">{{ __('borrower.membership.member_role') }}</p>
                        <h3 class="mt-1.5 text-2xl font-bold tracking-wide leading-[1.1] break-words">{{ $name ?: '—' }}</h3>
                    </div>
                </div>
                <div class="relative mt-6 rounded-2xl bg-black/25 px-5 py-5 ring-1 ring-white/15">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-white/55 mb-2">{{ __('borrower.membership.member_no_label') }}</p>
                    <p class="font-mono text-2xl font-bold tracking-[0.14em] break-all">{{ $memberNoDisplay }}</p>
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
                @if ($verifyUrl && $qrDataUri)
                    <div class="relative mt-6 flex items-center gap-4 rounded-2xl bg-black/25 px-4 py-4 ring-1 ring-white/15">
                        <img src="{{ $qrDataUri }}" alt="" class="size-20 rounded-xl bg-white p-1.5">
                        <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.membership.scan_to_verify') }}</p>
                    </div>
                @endif
            </div>
            <button type="button" @click="expanded = false" class="mt-4 w-full rounded-xl bg-white/95 text-brand font-semibold py-3 text-sm shadow-lg">
                {{ __('borrower.membership.close_card') }}
            </button>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                document.addEventListener('alpine:init', function () {
                    Alpine.data('memberCardActions', function (copyNo, shareText, verifyUrl, whatsappUrl) {
                        return {
                            copied: false,
                            shareCopied: false,
                            expanded: false,
                            copyNo: copyNo,
                            shareText: shareText,
                            verifyUrl: verifyUrl,
                            whatsappUrl: whatsappUrl,
                            copyVerifyLink() {
                                if (! this.verifyUrl) return;
                                navigator.clipboard.writeText(this.verifyUrl).then(() => {
                                    this.shareCopied = true;
                                    var self = this;
                                    setTimeout(function () { self.shareCopied = false; }, 2500);
                                });
                            },
                            shareMembership() {
                                var title = @js(brand_name().' membership');
                                if (navigator.share) {
                                    navigator.share({ title: title, text: this.shareText, url: this.verifyUrl }).catch(function () {});
                                    return;
                                }
                                if (this.whatsappUrl) {
                                    window.open(this.whatsappUrl, '_blank', 'noopener');
                                    return;
                                }
                                this.copyVerifyLink();
                            },
                        };
                    });
                });
            </script>
        @endpush
    @endonce
</div>
