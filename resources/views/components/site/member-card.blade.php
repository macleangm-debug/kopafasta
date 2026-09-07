@props(['customer', 'referralCode' => null, 'referralLink' => null])
@php
    /** @var \App\Models\Customer $customer */
    use App\Support\MemberNumberFormatter;
    use App\Services\ReferralService;

    $permanent = $customer->usesPermanentIdentityCard();
    $color = $customer->membershipStatusColor();
    $label = $customer->membershipStatusLabel();

    $panelClass = match ($color) {
        'green'  => 'kf-premium-panel',
        'orange' => 'kf-premium-panel-orange',
        'red'    => 'kf-premium-panel-red',
        'slate'  => 'kf-premium-panel-slate',
        default  => 'kf-premium-panel-slate',
    };
    // Permanent identity never uses red as the default issued look.
    if ($permanent && $customer->hasCustomerIdentity() && ($customer->status ?? '') === 'active') {
        $panelClass = 'kf-premium-panel';
        $color = 'green';
    }

    $since = optional($customer->customerSinceDate())->format('d M Y') ?? '—';
    $issued = optional($customer->membership_issued_at)->format('d M Y') ?? $since;
    $expires = optional($customer->membership_expires_at)->format('d M Y') ?? '—';
    $plusActive = app(\App\Services\Plus\PlusService::class)->isActive($customer);
    $grade = strtolower((string) ($customer->grade ?: 'bronze'));
    $gradeKey = strtoupper($grade);
    $name = strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')));
    $memberNoRaw = MemberNumberFormatter::raw($customer->member_no);
    $memberNoDisplay = MemberNumberFormatter::display($customer->member_no);
    $base = rtrim(app(ReferralService::class)->appBaseUrl(), '/');
    $verifyUrl = $memberNoRaw ? $base.'/v/'.rawurlencode($memberNoRaw) : null;
    $shareText = $verifyUrl
        ? __('borrower.membership.share_message', [
            'member' => $memberNoDisplay,
            'link' => $verifyUrl,
            'register' => $referralLink ?: route('site.register.borrower'),
        ])
        : '';
    $whatsappUrl = $shareText !== '' ? 'https://wa.me/?text='.rawurlencode($shareText) : null;
    $photoUrl = app(\App\Services\FaceVerificationService::class)->avatarUrl($customer);
    $initial = strtoupper(substr(trim($customer->first_name ?? ''), 0, 1) ?: '?');
    $days = max(0, (int) $customer->membershipDaysRemaining());
    $duration = (int) (\App\Services\MembershipService::config()['duration_days'] ?? 365);
    $pct = $duration > 0 ? max(0, min(100, ($days / $duration) * 100)) : 0;
    $logoUrl = brand('logo_mark_url') ?: brand('logo_url') ?: 'images/brand/kopafasta-mark.png';
    $roleLabel = $permanent
        ? __('borrower.membership.member_role_customer')
        : __('borrower.membership.member_role');

    $qrDataUri = null;
    if ($verifyUrl) {
        $qrPng = @file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=6&data='.urlencode($verifyUrl));
        if (is_string($qrPng) && $qrPng !== '') {
            $qrDataUri = 'data:image/png;base64,'.base64_encode($qrPng);
        }
    }

    $urgencyLabel = match (true) {
        $permanent => __('borrower.membership.identity_standing_body'),
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
     x-data="memberCardActions(@js([
         'copyNo' => $memberNoRaw,
         'shareText' => $shareText,
         'verifyUrl' => $verifyUrl,
         'whatsappUrl' => $whatsappUrl,
         'cardFilename' => __('borrower.membership.share_card_filename'),
         'copyPrompt' => __('borrower.membership.share_copy_prompt'),
         'shareTitle' => brand_name(),
     ]))">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch">
        <div
            data-kf-card-export
            class="relative w-full text-left {{ $panelClass }} rounded-[1.35rem] p-5 sm:p-6 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold"
            role="button"
            tabindex="0"
            @click="expanded = true"
            @keydown.enter="expanded = true"
            aria-label="{{ __('borrower.membership.my_card') }}">
            <div class="absolute inset-[1px] rounded-[1.28rem] ring-1 ring-white/10 pointer-events-none"></div>
            <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-brand-gold/10 pointer-events-none"></div>
            <div class="absolute inset-0 opacity-[0.14] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.35' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>

            <div class="relative flex items-center justify-between gap-3 mb-5">
                <span class="inline-flex items-center gap-2.5 min-w-0">
                    <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" aria-hidden="true" class="h-10 sm:h-11 w-auto object-contain shrink-0">
                    <span class="text-xl sm:text-2xl font-bold tracking-tight text-white leading-none truncate">{{ brand_name() }}</span>
                </span>
                <x-site.grade-badge :grade="$grade" :plus="$plusActive" size="sm" class="shrink-0" />
            </div>

            <div class="relative flex items-start gap-3 sm:gap-4">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="" class="size-16 sm:size-20 rounded-2xl object-cover ring-2 ring-brand-gold/50 bg-white/10 shrink-0">
                @else
                    <div class="size-16 sm:size-20 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 grid place-items-center text-2xl font-bold shrink-0">{{ $initial }}</div>
                @endif
                <div class="min-w-0 pt-0.5 flex-1">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold leading-none">{{ $roleLabel }}</p>
                    <h3 class="mt-1 text-lg sm:text-2xl font-bold tracking-wide leading-snug break-words hyphens-auto">{{ $name ?: '—' }}</h3>
                    <p class="mt-2 font-mono text-sm text-white/90 tracking-wider break-all">{{ $memberNoDisplay }}</p>
                </div>
                @if ($verifyUrl && $qrDataUri)
                    <img src="{{ $qrDataUri }}" alt="" class="hidden sm:block size-16 sm:size-20 rounded-xl bg-white p-1 shrink-0 ring-1 ring-white/30">
                @endif
            </div>
            @if ($verifyUrl && $qrDataUri)
                <div class="relative mt-3 flex justify-end sm:hidden">
                    <img src="{{ $qrDataUri }}" alt="" class="size-16 rounded-xl bg-white p-1 ring-1 ring-white/30">
                </div>
            @endif

            <dl class="mt-4 grid grid-cols-2 gap-3 relative">
                <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10 min-h-[4.5rem] flex flex-col">
                    <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">
                        {{ $permanent ? __('borrower.membership.customer_since_label') : __('borrower.membership.issued_label') }}
                    </dt>
                    <dd class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $permanent ? $since : $issued }}</dd>
                </div>
                <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10 min-h-[4.5rem] flex flex-col">
                    <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">
                        {{ $permanent ? __('borrower.membership.status_title') : __('borrower.membership.expires_label') }}
                    </dt>
                    <dd class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">
                        {{ $permanent ? $label : $expires }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="relative overflow-hidden rounded-[1.35rem] bg-white p-6 flex flex-col min-h-[280px] shadow-[0_18px_40px_rgba(8,47,39,0.08)] ring-1 ring-brand/10">
            <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand via-brand-gold to-brand pointer-events-none"></div>
            <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-brand/5 pointer-events-none"></div>

            @if ($permanent)
                <div class="relative flex items-center justify-between gap-3">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-brand font-semibold">{{ __('borrower.membership.status_title') }}</p>
                    @if ($plusActive)
                        <span class="inline-flex items-center rounded-full bg-brand/10 text-brand px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em] ring-1 ring-brand/15">
                            {{ __('plus.card.plus') }}
                        </span>
                    @endif
                </div>
                <div class="relative mt-6 flex items-center gap-3 rounded-2xl bg-brand/5 px-4 py-3.5 ring-1 ring-brand/10">
                    <span class="size-9 rounded-full bg-brand text-brand-gold grid place-items-center shrink-0" aria-hidden="true">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-brand leading-tight">{{ __('borrower.membership.identity_standing_title') }}</p>
                        <p class="mt-0.5 text-xs text-gray-600">{{ __('borrower.membership.identity_standing_body') }}</p>
                    </div>
                </div>
                <dl class="relative mt-auto pt-5 grid grid-cols-2 gap-3 text-xs">
                    <div class="rounded-xl bg-brand-muted/40 px-3 py-2.5 ring-1 ring-brand/10">
                        <dt class="text-gray-500">{{ __('borrower.membership.grade_label') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5 uppercase tracking-wide">{{ $gradeKey }}</dd>
                    </div>
                    <div class="rounded-xl bg-brand-muted/40 px-3 py-2.5 ring-1 ring-brand/10">
                        <dt class="text-gray-500">{{ __('borrower.membership.access_label') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ __('borrower.membership.access_ready') }}</dd>
                    </div>
                </dl>
                @unless ($plusActive)
                    <a href="{{ route('site.borrower.plus.home') }}" class="relative mt-4 inline-flex items-center justify-center bg-brand-gold hover:brightness-95 text-brand text-sm font-bold px-4 py-2.5 rounded-xl">{{ __('site.plus.join') }}</a>
                @endunless
            @else
                <div class="relative flex items-center justify-between gap-3">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-brand font-semibold">{{ __('borrower.membership.status_title') }}</p>
                    @if ($customer->isMembershipActive() && ! $customer->isMembershipExpired())
                        <span class="inline-flex items-center rounded-full bg-brand/10 text-brand px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em] ring-1 ring-brand/15">
                            {{ $gradeKey }}{{ $plusActive ? ' · '.__('plus.card.plus') : '' }}
                        </span>
                    @endif
                </div>
                <div class="relative mt-6 flex items-end gap-3">
                    <span class="text-6xl font-black text-brand leading-none tabular-nums tracking-tight">{{ $days }}</span>
                    <div class="pb-1.5">
                        <p class="text-base font-bold text-gray-900 leading-none">{{ __('borrower.membership.days_unit') }}</p>
                        <p class="mt-1.5 text-xs text-gray-500 leading-snug">{{ $urgencyLabel }}</p>
                    </div>
                </div>
                <div class="relative mt-6">
                    <div class="flex justify-between text-[10px] uppercase tracking-wide text-gray-500 mb-1.5">
                        <span>{{ __('borrower.membership.year_progress') }}</span>
                        <span class="font-semibold tabular-nums text-brand">{{ format_number($pct, 0) }}%</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-brand-muted overflow-hidden ring-1 ring-brand/5">
                        <div class="h-full rounded-full {{ $barClass === 'bg-emerald-500' ? 'bg-brand' : $barClass }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @if (! $customer->hasMembership())
                    <a href="{{ route('site.membership.renew') }}" class="relative mt-4 inline-flex items-center justify-center bg-brand-gold hover:brightness-95 text-brand text-sm font-bold px-4 py-2.5 rounded-xl">{{ __('borrower.membership.pay_registration') }}</a>
                @elseif ($customer->isMembershipExpired())
                    <a href="{{ route('site.membership.renew') }}" class="relative mt-4 inline-flex items-center justify-center bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl">{{ __('borrower.membership.renew_now') }}</a>
                @elseif ($customer->isMembershipExpiringSoon(30))
                    <a href="{{ route('site.membership.renew') }}" class="relative mt-4 inline-flex items-center justify-center bg-brand-gold hover:brightness-95 text-brand text-sm font-bold px-4 py-2.5 rounded-xl">{{ __('borrower.membership.renew_early') }}</a>
                @endif
            @endif
        </div>
    </div>

    @if ($verifyUrl)
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="copyVerifyLink()"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3.5 py-2.5 text-xs font-semibold"
                    x-text="shareCopied ? @js(__('borrower.membership.link_copied')) : @js(__('borrower.membership.copy_verify_link'))">
                {{ __('borrower.membership.copy_verify_link') }}
            </button>
            <button type="button" @click="openShare()"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-white text-brand ring-1 ring-brand/20 hover:bg-brand/5 px-3.5 py-2.5 text-xs font-semibold">
                {{ __('borrower.membership.share') }}
            </button>
        </div>

        @php
            $enabledSocial = collect(social_links())->pluck('platform')->all();
            $showFacebook = in_array('facebook', $enabledSocial, true);
        @endphp
        <x-site.kopafasta-share-sheet
            :title="__('borrower.membership.share')"
            :hint="__('borrower.membership.share_sheet_hint')"
            :show-facebook="$showFacebook"
            open="shareOpen"
            :whatsapp-label="__('borrower.membership.share_whatsapp')"
            :facebook-label="__('borrower.membership.share_facebook')"
            :messages-label="__('borrower.membership.share_messages')"
            :email-label="__('borrower.membership.share_email')"
            :copy-label="__('borrower.membership.share_copy')"
            :copied-label="__('borrower.membership.share_copied_short')"
            :more-label="__('borrower.membership.share_more')"
        />
    @endif

    <section class="rounded-[1.35rem] kf-premium-panel p-5 sm:p-6">
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

    <div x-show="expanded" x-cloak
         class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 p-4 sm:p-8 overflow-y-auto"
         @keydown.escape.window="expanded = false">
        <button type="button" class="absolute inset-0 cursor-zoom-out" @click="expanded = false" aria-label="Close"></button>
        <div class="relative w-full max-w-lg sm:max-w-xl mx-auto my-auto" @click.stop>
            <button type="button" @click="expanded = false" class="absolute -top-3 -right-1 z-20 size-10 rounded-full bg-black/50 text-white grid place-items-center shadow-lg" aria-label="{{ __('borrower.membership.close_card') }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
            <div class="relative w-full text-left {{ $panelClass }} rounded-[1.35rem] p-5 sm:p-7">
                <div class="absolute inset-[1px] rounded-[1.28rem] ring-1 ring-white/10 pointer-events-none"></div>
                <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-brand-gold/10 pointer-events-none"></div>
                <div class="relative flex items-center justify-between gap-3 mb-5">
                    <span class="inline-flex items-center gap-2.5 min-w-0">
                        <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" aria-hidden="true" class="h-10 sm:h-11 w-auto object-contain shrink-0">
                        <span class="text-xl sm:text-2xl font-bold tracking-tight text-white leading-none truncate">{{ brand_name() }}</span>
                    </span>
                    <x-site.grade-badge :grade="$grade" :plus="$plusActive" size="sm" class="shrink-0" />
                </div>
                <div class="relative flex items-start gap-4 sm:gap-5">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="" class="size-20 sm:size-24 rounded-2xl object-cover ring-2 ring-brand-gold/50 bg-white/10 shrink-0">
                    @else
                        <div class="size-20 sm:size-24 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 grid place-items-center text-3xl font-bold shrink-0">{{ $initial }}</div>
                    @endif
                    <div class="min-w-0 pt-0.5 flex-1">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold leading-none">{{ $roleLabel }}</p>
                        <h3 class="mt-1.5 text-xl sm:text-3xl font-bold tracking-wide leading-snug break-words">{{ $name ?: '—' }}</h3>
                        <p class="mt-2.5 font-mono text-sm sm:text-base text-white/90 tracking-wider break-all">{{ $memberNoDisplay }}</p>
                    </div>
                    @if ($verifyUrl && $qrDataUri)
                        <img src="{{ $qrDataUri }}" alt="" class="size-20 sm:size-24 rounded-xl bg-white p-1.5 shrink-0 ring-1 ring-white/30">
                    @endif
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-3 relative">
                    <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10 min-h-[4.5rem] flex flex-col">
                        <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">
                            {{ $permanent ? __('borrower.membership.customer_since_label') : __('borrower.membership.issued_label') }}
                        </dt>
                        <dd class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $permanent ? $since : $issued }}</dd>
                    </div>
                    <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10 min-h-[4.5rem] flex flex-col">
                        <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">
                            {{ $permanent ? __('borrower.membership.status_title') : __('borrower.membership.expires_label') }}
                        </dt>
                        <dd class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">
                            {{ $permanent ? $label : $expires }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                document.addEventListener('alpine:init', function () {
                    Alpine.data('memberCardActions', function (cfg) {
                        cfg = cfg || {};
                        return {
                            copied: false,
                            shareCopied: false,
                            expanded: false,
                            shareOpen: false,
                            copyNo: cfg.copyNo || '',
                            shareText: cfg.shareText || '',
                            verifyUrl: cfg.verifyUrl || '',
                            whatsappUrl: cfg.whatsappUrl || '',
                            cardFilename: cfg.cardFilename || 'kopafasta-card.png',
                            copyPrompt: cfg.copyPrompt || 'Copy this message',
                            shareTitle: cfg.shareTitle || 'Kopafasta',
                            shareFile: null,
                            canNativeShare: typeof navigator !== 'undefined' && typeof navigator.share === 'function',
                            copyVerifyLink() {
                                if (! this.verifyUrl) return;
                                navigator.clipboard.writeText(this.verifyUrl).then(() => {
                                    this.shareCopied = true;
                                    var self = this;
                                    setTimeout(function () { self.shareCopied = false; }, 2500);
                                });
                            },
                            openShare() {
                                this.shareOpen = true;
                                this.prepareCardImage();
                            },
                            async prepareCardImage() {
                                if (this.shareFile) return;
                                var el = document.querySelector('[data-kf-card-export]');
                                if (! el || typeof window.kfExportElementPngFile !== 'function') return;
                                try {
                                    this.shareFile = await window.kfExportElementPngFile(el, this.cardFilename);
                                } catch (e) {
                                    this.shareFile = null;
                                }
                            },
                            async withFileOrFallback(fallback) {
                                await this.prepareCardImage();
                                if (this.shareFile && navigator.canShare && navigator.canShare({ files: [this.shareFile] })) {
                                    try {
                                        await navigator.share({
                                            files: [this.shareFile],
                                            title: this.shareTitle,
                                            text: this.shareText,
                                        });
                                        this.shareOpen = false;
                                        return;
                                    } catch (e) {
                                        if (e && e.name === 'AbortError') return;
                                    }
                                }
                                fallback();
                            },
                            shareWhatsApp() {
                                var self = this;
                                this.withFileOrFallback(function () {
                                    if (self.whatsappUrl) {
                                        window.open(self.whatsappUrl, '_blank', 'noopener');
                                    }
                                });
                            },
                            shareFacebook() {
                                var self = this;
                                this.withFileOrFallback(function () {
                                    if (! self.verifyUrl) return;
                                    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(self.verifyUrl), '_blank', 'noopener');
                                });
                            },
                            shareMessages() {
                                var self = this;
                                this.withFileOrFallback(function () {
                                    window.location.href = 'sms:?&body=' + encodeURIComponent(self.shareText);
                                });
                            },
                            shareEmail() {
                                var self = this;
                                this.withFileOrFallback(function () {
                                    window.location.href = 'mailto:?subject=' + encodeURIComponent(self.shareTitle)
                                        + '&body=' + encodeURIComponent(self.shareText);
                                });
                            },
                            async copyShare() {
                                try {
                                    await navigator.clipboard.writeText(this.shareText);
                                    this.copied = true;
                                    var self = this;
                                    setTimeout(function () { self.copied = false; }, 2200);
                                } catch (e) {
                                    window.prompt(this.copyPrompt, this.shareText);
                                }
                            },
                            async shareMore() {
                                if (! this.canNativeShare) return;
                                await this.prepareCardImage();
                                try {
                                    var payload = { title: this.shareTitle, text: this.shareText };
                                    if (this.shareFile && navigator.canShare && navigator.canShare({ files: [this.shareFile] })) {
                                        payload.files = [this.shareFile];
                                    } else if (this.verifyUrl) {
                                        payload.url = this.verifyUrl;
                                    }
                                    await navigator.share(payload);
                                    this.shareOpen = false;
                                } catch (e) {
                                    if (e && e.name === 'AbortError') return;
                                }
                            },
                        };
                    });
                });
            </script>
        @endpush
    @endonce
</div>
