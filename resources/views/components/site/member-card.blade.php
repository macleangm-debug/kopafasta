@props(['customer'])
@php
    /** @var \App\Models\Customer $customer */
    use App\Support\MemberNumberFormatter;
    use App\Services\ReferralService;

    $color = $customer->membershipStatusColor();
    $label = $customer->membershipStatusLabel();

    $bgGradient = match ($color) {
        'green'  => 'from-emerald-500 via-emerald-600 to-emerald-700',
        'orange' => 'from-amber-500 via-amber-600 to-amber-700',
        'red'    => 'from-rose-500 via-rose-600 to-rose-700',
        default  => 'from-slate-500 via-slate-600 to-slate-700',
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
    $verifyUrl = $memberNoRaw
        ? rtrim(app(ReferralService::class)->appBaseUrl(), '/').'/verify/member/'.urlencode($memberNoRaw)
        : null;
    $shareText = trim($name.' · '.brand_name().' member '.$memberNoDisplay.($verifyUrl ? ' · '.$verifyUrl : ''));
    $facePhoto = app(\App\Services\FaceVerificationService::class)->latestByAngle($customer)->get('front');
    $photoUrl = $facePhoto?->file_path ? asset('storage/'.$facePhoto->file_path) : null;
    $initial = strtoupper(substr(trim($customer->first_name ?? ''), 0, 1) ?: '?');
    $days    = max(0, (int) $customer->membershipDaysRemaining());
    $duration = (int) (\App\Services\MembershipService::config()['duration_days'] ?? 365);
    $pct     = $duration > 0 ? max(0, min(100, ($days / $duration) * 100)) : 0;
    $logoUrl = brand('logo_mark_url') ?: brand('logo_url') ?: 'images/brand/kopafasta-mark.png';
    $downloadName = 'membership-'.($memberNoRaw ?: 'card');
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 md:grid-cols-2 gap-4']) }}
     x-data="memberCardActions(@js($memberNoRaw), @js($shareText), @js($verifyUrl), @js($downloadName))">

    <div class="space-y-3">
        <p class="text-xs text-gray-500" data-html2canvas-ignore>{{ __('borrower.membership.tap_to_expand') }}</p>

        {{-- Campaign card face (web + export share this markup) --}}
        <div
                class="relative w-full text-left overflow-hidden rounded-2xl bg-gradient-to-br {{ $bgGradient }} text-white shadow-[0_20px_50px_rgba(0,77,64,0.35)] p-5 sm:p-6 ring-1 ring-white/10 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold"
                x-ref="cardFace"
                role="button"
                tabindex="0"
                @click="expanded = true"
                @keydown.enter="expanded = true"
                aria-label="{{ __('borrower.membership.my_card') }}">
            <div class="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/10 pointer-events-none"></div>
            <div class="absolute -left-10 -bottom-16 h-40 w-40 rounded-full bg-white/10 pointer-events-none"></div>
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-gold/80 via-white/40 to-brand-gold/80 pointer-events-none"></div>

            <div class="relative flex items-center justify-between gap-3 mb-5">
                <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="{{ brand_name() }}" class="h-9 w-9 object-contain drop-shadow-sm">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide {{ $badgeClass }} shrink-0 shadow-sm">
                    {{ $label }}
                </span>
            </div>

            <div class="relative flex items-start gap-4">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="" crossorigin="anonymous" class="size-16 sm:size-20 rounded-xl object-cover ring-2 ring-white/35 bg-white/10 shrink-0 shadow-md">
                @else
                    <div class="size-16 sm:size-20 rounded-xl bg-white/15 ring-2 ring-white/25 grid place-items-center text-2xl font-bold shrink-0">{{ $initial }}</div>
                @endif
                <div class="min-w-0 pt-0.5">
                    <p class="text-[10px] uppercase tracking-[0.18em] text-white/70 font-semibold">{{ brand_name() }} Member</p>
                    <h3 class="mt-1 text-lg sm:text-xl font-bold tracking-wide leading-snug break-words">{{ $name ?: '—' }}</h3>
                </div>
            </div>

            <div class="relative mt-5 rounded-xl bg-black/20 px-4 py-4 ring-1 ring-white/20 backdrop-blur-[2px]">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-white/60 mb-2">{{ __('borrower.membership.member_no_label') }}</p>
                        <p class="font-mono text-lg sm:text-xl md:text-2xl font-bold tracking-[0.12em] leading-tight break-all">
                            {{ $memberNoDisplay }}
                        </p>
                    </div>
                    @if ($memberNoRaw)
                        <span
                            role="button"
                            tabindex="0"
                            data-html2canvas-ignore
                            @click.stop="navigator.clipboard.writeText(copyNo).then(() => { copied = true; setTimeout(() => copied = false, 2500); })"
                            @keydown.enter.stop="navigator.clipboard.writeText(copyNo).then(() => { copied = true; setTimeout(() => copied = false, 2500); })"
                            class="shrink-0 inline-flex items-center justify-center size-10 rounded-lg bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition cursor-pointer"
                            :title="copied ? 'Copied!' : 'Copy membership number'">
                            <svg x-show="!copied" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied" x-cloak class="size-5 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                    @endif
                </div>
                <p x-show="copied" x-cloak data-html2canvas-ignore
                   class="mt-3 text-xs font-semibold text-emerald-100 bg-emerald-900/30 rounded-lg px-3 py-2 inline-flex items-center gap-1.5">
                    Membership Number Copied
                </p>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-3 relative text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-white/70">{{ __('borrower.membership.issued_label') }}</dt>
                    <dd class="font-semibold">{{ $issued }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-white/70">{{ __('borrower.membership.expires_label') }}</dt>
                    <dd class="font-semibold">{{ $expires }}</dd>
                </div>
            </dl>

            @if ($verifyUrl)
                <div class="relative mt-5 flex items-center gap-4 rounded-xl bg-black/20 px-4 py-3.5 ring-1 ring-white/20">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($verifyUrl) }}"
                         alt="Membership QR code" crossorigin="anonymous" class="size-[68px] rounded-lg bg-white p-1 shrink-0">
                    <div class="text-left min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">{{ __('borrower.membership.scan_to_verify') }}</p>
                        <p class="text-xs text-white/90 mt-1" data-html2canvas-ignore>{{ __('borrower.membership.scan_hint') }}</p>
                    </div>
                </div>
            @endif
        </div>

        @if ($verifyUrl)
            <div class="flex flex-wrap gap-2" data-html2canvas-ignore>
                <button type="button"
                    @click="navigator.clipboard.writeText(verifyUrl).then(() => { shareCopied = true; setTimeout(() => shareCopied = false, 2500); })"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3 py-2 text-xs font-semibold">
                    {{ __('borrower.membership.copy_verify_link') }}
                </button>
                <button type="button"
                        @click="downloadCardPdf()"
                        :disabled="saving"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3 py-2 text-xs font-semibold disabled:opacity-60">
                    <span x-show="!saving">{{ __('borrower.membership.download_card') }}</span>
                    <span x-show="saving" x-cloak>{{ __('borrower.membership.saving') }}</span>
                </button>
                <button type="button"
                        @click="saveCardImage()"
                        :disabled="saving"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3 py-2 text-xs font-semibold disabled:opacity-60">
                    <span x-show="!saving && !saved">{{ __('borrower.membership.save_to_photos') }}</span>
                    <span x-show="saving" x-cloak>{{ __('borrower.membership.saving') }}</span>
                    <span x-show="saved && !saving" x-cloak>{{ __('borrower.membership.saved') }}</span>
                </button>
                <button type="button"
                        @click="shareMembership()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3 py-2 text-xs font-semibold">
                    {{ __('borrower.membership.share') }}
                </button>
            </div>
            <p x-show="shareCopied" x-cloak class="text-xs text-brand">{{ __('borrower.membership.link_copied') }}</p>
        @endif

        <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-2">{{ __('borrower.membership.benefits_title') }}</p>
            <ul class="space-y-1.5 text-xs text-gray-700">
                @foreach (__('borrower.membership.benefits') as $benefit)
                    <li class="flex items-start gap-2">
                        <span class="text-brand shrink-0">✓</span>
                        <span>{{ $benefit }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Fullscreen expand for campaigns / show-and-share --}}
    <div x-show="expanded" x-cloak
         class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 p-4 sm:p-8"
         @keydown.escape.window="expanded = false"
         data-html2canvas-ignore>
        <button type="button" class="absolute inset-0 cursor-zoom-out" @click="expanded = false" aria-label="Close"></button>
        <div class="relative w-full max-w-md" @click.stop>
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br {{ $bgGradient }} text-white shadow-2xl p-6 sm:p-8 ring-1 ring-white/15">
                <div class="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/10"></div>
                <div class="absolute -left-10 -bottom-16 h-40 w-40 rounded-full bg-white/10"></div>
                <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-brand-gold via-white/50 to-brand-gold"></div>

                <div class="relative flex items-center justify-between gap-3 mb-6">
                    <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="{{ brand_name() }}" class="h-11 w-11 object-contain">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide {{ $badgeClass }}">{{ $label }}</span>
                </div>

                <div class="relative flex items-start gap-4">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="" class="size-24 rounded-2xl object-cover ring-2 ring-white/40 shrink-0">
                    @else
                        <div class="size-24 rounded-2xl bg-white/15 ring-2 ring-white/25 grid place-items-center text-3xl font-bold shrink-0">{{ $initial }}</div>
                    @endif
                    <div class="min-w-0 pt-1">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-white/70 font-semibold">{{ brand_name() }} Member</p>
                        <h3 class="mt-2 text-2xl font-bold tracking-wide leading-snug break-words">{{ $name ?: '—' }}</h3>
                    </div>
                </div>

                <div class="relative mt-6 rounded-2xl bg-black/20 px-5 py-5 ring-1 ring-white/20">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-white/60 mb-2">{{ __('borrower.membership.member_no_label') }}</p>
                    <p class="font-mono text-2xl font-bold tracking-[0.14em] break-all">{{ $memberNoDisplay }}</p>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-4 relative text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-wider text-white/70">{{ __('borrower.membership.issued_label') }}</dt>
                        <dd class="font-semibold text-base mt-0.5">{{ $issued }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-wider text-white/70">{{ __('borrower.membership.expires_label') }}</dt>
                        <dd class="font-semibold text-base mt-0.5">{{ $expires }}</dd>
                    </div>
                </dl>

                @if ($verifyUrl)
                    <div class="relative mt-6 flex items-center gap-4 rounded-2xl bg-black/20 px-4 py-4 ring-1 ring-white/20">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data={{ urlencode($verifyUrl) }}"
                             alt="" class="size-20 rounded-xl bg-white p-1.5 shrink-0">
                        <p class="text-xs uppercase tracking-widest text-white/80 font-semibold">{{ __('borrower.membership.scan_to_verify') }}</p>
                    </div>
                @endif
            </div>
            <button type="button" @click="expanded = false"
                    class="mt-4 w-full rounded-xl bg-white/95 text-brand font-semibold py-3 text-sm shadow-lg">
                {{ __('borrower.membership.close_card') }}
            </button>
        </div>
    </div>

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
            <script>
                document.addEventListener('alpine:init', function () {
                    Alpine.data('memberCardActions', function (copyNo, shareText, verifyUrl, downloadName) {
                        return {
                            copied: false,
                            shareCopied: false,
                            saving: false,
                            saved: false,
                            expanded: false,
                            copyNo: copyNo,
                            shareText: shareText,
                            verifyUrl: verifyUrl,
                            downloadName: downloadName || 'membership-card',
                            async captureCard() {
                                if (typeof html2canvas !== 'function') {
                                    throw new Error('html2canvas missing');
                                }
                                const target = this.$refs.cardFace;
                                if (! target) {
                                    throw new Error('card missing');
                                }
                                return html2canvas(target, {
                                    scale: Math.min(3, (window.devicePixelRatio || 1) * 2),
                                    backgroundColor: null,
                                    useCORS: true,
                                    allowTaint: false,
                                    logging: false,
                                });
                            },
                            async saveCardImage() {
                                this.saving = true;
                                this.saved = false;
                                try {
                                    const canvas = await this.captureCard();
                                    const blob = await new Promise(function (resolve) {
                                        canvas.toBlob(resolve, 'image/png');
                                    });
                                    if (! blob) throw new Error('blob');
                                    const filename = this.downloadName + '.png';
                                    const file = new File([blob], filename, { type: 'image/png' });
                                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                                        await navigator.share({ files: [file], title: 'Membership card' });
                                        this.saved = true;
                                        return;
                                    }
                                    const url = URL.createObjectURL(blob);
                                    const link = document.createElement('a');
                                    link.href = url;
                                    link.download = filename;
                                    document.body.appendChild(link);
                                    link.click();
                                    link.remove();
                                    URL.revokeObjectURL(url);
                                    this.saved = true;
                                } catch (e) {
                                    if (! e || e.name !== 'AbortError') {
                                        alert('Could not save image. Try again.');
                                    }
                                } finally {
                                    this.saving = false;
                                    if (this.saved) {
                                        var self = this;
                                        setTimeout(function () { self.saved = false; }, 2500);
                                    }
                                }
                            },
                            async downloadCardPdf() {
                                this.saving = true;
                                try {
                                    const canvas = await this.captureCard();
                                    const img = canvas.toDataURL('image/png');
                                    const jspdfNs = window.jspdf || {};
                                    const JsPDF = jspdfNs.jsPDF;
                                    if (! JsPDF) {
                                        // Fallback: PNG download if jsPDF CDN blocked
                                        await this.saveCardImage();
                                        return;
                                    }
                                    const pdf = new JsPDF({
                                        orientation: 'portrait',
                                        unit: 'mm',
                                        format: [105, 148],
                                        compress: true,
                                    });
                                    const pageW = pdf.internal.pageSize.getWidth();
                                    const pageH = pdf.internal.pageSize.getHeight();
                                    const ratio = Math.min(pageW / canvas.width, pageH / canvas.height);
                                    const w = canvas.width * ratio;
                                    const h = canvas.height * ratio;
                                    const x = (pageW - w) / 2;
                                    const y = (pageH - h) / 2;
                                    pdf.addImage(img, 'PNG', x, y, w, h);
                                    pdf.save(this.downloadName + '.pdf');
                                } catch (e) {
                                    alert('Could not download PDF. Try Save to Photos instead.');
                                } finally {
                                    this.saving = false;
                                }
                            },
                            shareMembership() {
                                var title = @js(brand_name().' membership');
                                if (navigator.share) {
                                    navigator.share({ title: title, text: this.shareText, url: this.verifyUrl }).catch(function () {});
                                } else if (this.verifyUrl) {
                                    navigator.clipboard.writeText(this.verifyUrl);
                                    this.shareCopied = true;
                                    var self = this;
                                    setTimeout(function () { self.shareCopied = false; }, 2500);
                                }
                            },
                        };
                    });
                });
            </script>
        @endpush
    @endonce

    @php
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
        $glowClass = match ($color) {
            'green'  => 'from-emerald-500/15 via-white to-emerald-50/40',
            'orange' => 'from-amber-500/15 via-white to-amber-50/40',
            'red'    => 'from-rose-500/15 via-white to-rose-50/40',
            default  => 'from-slate-500/10 via-white to-slate-50/40',
        };
    @endphp
    <div class="relative overflow-hidden rounded-2xl ring-1 ring-gray-200/80 bg-gradient-to-br {{ $glowClass }} shadow-sm p-6 flex flex-col min-h-[220px]">
        <div class="absolute -right-8 -top-8 size-32 rounded-full bg-brand/5"></div>
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold relative">{{ __('borrower.membership.status_title') }}</p>

        <div class="relative mt-4 flex items-end gap-3">
            <span class="text-5xl sm:text-6xl font-black text-gray-900 leading-none tabular-nums">{{ $days }}</span>
            <div class="pb-2">
                <p class="text-sm font-bold text-gray-800">{{ __('borrower.membership.days_unit') }}</p>
                <p class="text-xs text-gray-500">{{ $urgencyLabel }}</p>
            </div>
        </div>

        <div class="relative mt-5">
            <div class="flex justify-between text-[10px] uppercase tracking-wide text-gray-500 mb-1.5">
                <span>{{ __('borrower.membership.year_progress') }}</span>
                <span class="font-semibold tabular-nums">{{ format_number($pct, 0) }}%</span>
            </div>
            <div class="h-2.5 rounded-full bg-gray-200/80 overflow-hidden">
                <div class="h-full rounded-full {{ $barClass }} transition-all" style="width: {{ $pct }}%"></div>
            </div>
        </div>

        <dl class="relative mt-5 grid grid-cols-2 gap-3 text-xs">
            <div class="rounded-xl bg-white/70 ring-1 ring-gray-100 px-3 py-2.5">
                <dt class="text-gray-500">{{ __('borrower.membership.issued_label') }}</dt>
                <dd class="font-semibold text-gray-900 mt-0.5">{{ $issued }}</dd>
            </div>
            <div class="rounded-xl bg-white/70 ring-1 ring-gray-100 px-3 py-2.5">
                <dt class="text-gray-500">{{ __('borrower.membership.expires_label') }}</dt>
                <dd class="font-semibold text-gray-900 mt-0.5">{{ $expires }}</dd>
            </div>
        </dl>

        @if (! $customer->hasMembership())
            <a href="{{ route('site.membership.renew') }}" class="relative mt-auto pt-5 inline-flex items-center justify-center bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm">
                {{ __('borrower.membership.pay_registration') }}
            </a>
        @elseif ($customer->isMembershipExpired())
            <a href="{{ route('site.membership.renew') }}" class="relative mt-auto pt-5 inline-flex items-center justify-center bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm">
                {{ __('borrower.membership.renew_now') }}
            </a>
        @elseif ($customer->isMembershipExpiringSoon(30))
            <a href="{{ route('site.membership.renew') }}" class="relative mt-auto pt-5 inline-flex items-center justify-center bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shadow-sm">
                {{ __('borrower.membership.renew_early') }}
            </a>
        @endif
    </div>
</div>
