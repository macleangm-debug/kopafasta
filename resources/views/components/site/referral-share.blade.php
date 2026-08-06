@props(['link' => '', 'code' => '', 'message' => null, 'tone' => 'on-brand'])

@php
    $shareText = $message ?? __('borrower.referrals.share_default_message', ['link' => $link]);
    $encodedText = rawurlencode($shareText);
    $onBrand = $tone === 'on-brand';
    $primary = $onBrand
        ? 'bg-brand-gold text-brand hover:brightness-95'
        : 'bg-brand-gold text-brand hover:brightness-95';
    $secondary = $onBrand
        ? 'bg-white text-brand ring-1 ring-white/30 hover:bg-brand-gold hover:ring-brand-gold'
        : 'bg-white text-brand ring-1 ring-brand/20 hover:bg-brand-muted/50';
    $ghost = $onBrand
        ? 'bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25'
        : 'bg-brand/5 text-brand ring-1 ring-brand/15 hover:bg-brand/10';
@endphp

<div x-data="referralShare(@js($link), @js($shareText))" class="w-full">
    <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2">
        <button type="button" @click="copyLink()"
                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-bold {{ $secondary }} transition">
            {{ __('borrower.referrals.share_copy') }}
        </button>
        <a href="https://wa.me/?text={{ $encodedText }}" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-bold {{ $primary }} transition">
            {{ __('borrower.referrals.share_whatsapp') }}
        </a>
        <a href="sms:?body={{ $encodedText }}"
           class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-bold {{ $ghost }} transition">
            {{ __('borrower.referrals.share_sms') }}
        </a>
        <button type="button" @click="nativeShare()"
                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-bold {{ $ghost }} transition">
            {{ __('borrower.referrals.share_native') }}
        </button>
    </div>
    <p x-show="copied" x-cloak x-transition
       class="mt-2 text-xs font-medium {{ $onBrand ? 'text-brand-gold' : 'text-brand' }}">
        {{ __('borrower.referrals.share_copied') }}
    </p>
</div>

@once
    @push('scripts')
        <script>
            function referralShare(link, shareText) {
                return {
                    copied: false,
                    async copyLink() {
                        try {
                            await navigator.clipboard.writeText(link);
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2500);
                        } catch (e) {
                            window.prompt('Copy this link:', link);
                        }
                    },
                    async nativeShare() {
                        if (navigator.share) {
                            try {
                                await navigator.share({ title: @js(brand_name().' referral'), text: shareText, url: link });
                            } catch (e) {}
                        } else {
                            this.copyLink();
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
