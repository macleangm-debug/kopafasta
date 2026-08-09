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
        'green'  => 'bg-emerald-100 text-emerald-800',
        'orange' => 'bg-amber-100 text-amber-800',
        'red'    => 'bg-rose-100 text-rose-800',
        default  => 'bg-slate-100 text-slate-800',
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
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 md:grid-cols-2 gap-4']) }}
     x-data="memberCardActions(@js($memberNoRaw), @js($shareText), @js($verifyUrl))">

    <div class="space-y-3">
        {{-- Visual card only (PDF/image capture target) --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $bgGradient }} text-white shadow-lg p-6"
             x-ref="cardFace">
            <div class="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/10"></div>
            <div class="absolute -left-10 -bottom-16 h-40 w-40 rounded-full bg-white/10"></div>

            <div class="flex items-start justify-between relative gap-4">
                <div class="flex items-start gap-4 min-w-0 flex-1">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="" crossorigin="anonymous" class="size-16 sm:size-20 rounded-xl object-cover ring-2 ring-white/30 bg-white/10 shrink-0">
                    @else
                        <div class="size-16 sm:size-20 rounded-xl bg-white/15 ring-2 ring-white/25 grid place-items-center text-2xl font-bold shrink-0">{{ $initial }}</div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-white/70">{{ brand_name() }} Member</p>
                        <h3 class="mt-1 text-xl font-bold tracking-wide truncate">{{ $name ?: '—' }}</h3>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $badgeClass }} shrink-0">
                    {{ $label }}
                </span>
            </div>

            <div class="relative mt-6 rounded-xl bg-black/15 px-4 py-4 ring-1 ring-white/20">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-white/60 mb-2">Membership number</p>
                        <p class="font-mono text-lg sm:text-xl md:text-2xl font-bold tracking-[0.12em] leading-tight break-all">
                            {{ $memberNoDisplay }}
                        </p>
                    </div>
                    @if ($memberNoRaw)
                        <button type="button"
                                @click="navigator.clipboard.writeText(copyNo).then(() => { copied = true; setTimeout(() => copied = false, 2500); })"
                                class="shrink-0 inline-flex items-center justify-center size-10 rounded-lg bg-white/15 hover:bg-white/25 ring-1 ring-white/25 transition"
                                :title="copied ? 'Copied!' : 'Copy membership number'">
                            <template x-if="!copied">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </template>
                            <template x-if="copied">
                                <svg class="size-5 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </template>
                        </button>
                    @endif
                </div>
                <p x-show="copied" x-cloak x-transition
                   class="mt-3 text-xs font-semibold text-emerald-100 bg-emerald-900/30 rounded-lg px-3 py-2 inline-flex items-center gap-1.5">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Membership Number Copied
                </p>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-3 relative text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-white/70">Issued</dt>
                    <dd class="font-semibold">{{ $issued }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-wider text-white/70">Expires</dt>
                    <dd class="font-semibold">{{ $expires }}</dd>
                </div>
            </dl>

            @if ($verifyUrl)
                <div class="relative mt-5 flex items-center gap-4 rounded-xl bg-black/15 px-4 py-4 ring-1 ring-white/20">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($verifyUrl) }}"
                         alt="Membership QR code" crossorigin="anonymous" class="size-[72px] rounded-lg bg-white p-1 shrink-0">
                    <div class="text-left min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-white/70">Scan to verify</p>
                        <p class="text-xs text-white/90 mt-1">Opens public member verification for this card.</p>
                    </div>
                </div>
            @endif
        </div>

        @if ($verifyUrl)
            <div class="flex flex-wrap gap-2">
                <button type="button"
                    @click="if (verifyUrl) { navigator.clipboard.writeText(verifyUrl).then(() => { shareCopied = true; setTimeout(() => shareCopied = false, 2500); }); }"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3 py-2 text-xs font-semibold">
                    {{ __('borrower.membership.copy_verify_link') }}
                </button>
                @if (Route::has('site.membership.card.download'))
                    <a href="{{ route('site.membership.card.download') }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3 py-2 text-xs font-semibold">
                        Download PDF
                    </a>
                @endif
                <button type="button"
                        @click="saveCardImage()"
                        :disabled="saving"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3 py-2 text-xs font-semibold disabled:opacity-60">
                    <span x-show="!saving && !saved">Save to Photos</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                    <span x-show="saved && !saving" x-cloak>Saved</span>
                </button>
                <button type="button"
                        @click="shareMembership()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand/10 hover:bg-brand/15 text-brand ring-1 ring-brand/20 px-3 py-2 text-xs font-semibold">
                    Share
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

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
            <script>
                document.addEventListener('alpine:init', function () {
                    Alpine.data('memberCardActions', function (copyNo, shareText, verifyUrl) {
                        return {
                            copied: false,
                            shareCopied: false,
                            saving: false,
                            saved: false,
                            copyNo: copyNo,
                            shareText: shareText,
                            verifyUrl: verifyUrl,
                            async saveCardImage() {
                                if (typeof html2canvas !== 'function') {
                                    alert('Unable to save image on this device. Try Download PDF instead.');
                                    return;
                                }

                                const target = this.$refs.cardFace;
                                if (! target) {
                                    alert('Could not find the membership card.');
                                    return;
                                }

                                this.saving = true;
                                this.saved = false;

                                try {
                                    const canvas = await html2canvas(target, {
                                        scale: window.devicePixelRatio > 1 ? 2 : 1,
                                        backgroundColor: null,
                                        useCORS: true,
                                        allowTaint: false,
                                    });

                                    const blob = await new Promise(function (resolve) {
                                        canvas.toBlob(resolve, 'image/png');
                                    });

                                    if (!blob) {
                                        throw new Error('Could not create image');
                                    }

                                    const filename = 'kopafasta-membership.png';
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
                                    if (!e || e.name !== 'AbortError') {
                                        alert('Could not save image. Try Download PDF instead.');
                                    }
                                } finally {
                                    this.saving = false;
                                    if (this.saved) {
                                        var self = this;
                                        setTimeout(function () { self.saved = false; }, 2500);
                                    }
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
