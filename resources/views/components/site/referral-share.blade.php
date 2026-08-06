@props(['link' => '', 'code' => '', 'message' => null])

@php
    $shareText = $message ?? __('borrower.referrals.share_default_message', ['link' => $link]);
    $encodedText = rawurlencode($shareText);
    $encodedLink = rawurlencode($link);
@endphp

<div x-data="referralShare(@js($link), @js($shareText))" class="w-full">
    <div class="flex flex-wrap gap-2">
        <button type="button" @click="copyLink()"
                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-white text-brand ring-1 ring-white/30 hover:bg-brand-gold hover:text-brand hover:ring-brand-gold transition">
            {{ __('borrower.referrals.share_copy') }}
        </button>
        <a href="https://wa.me/?text={{ $encodedText }}" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-brand-gold text-brand hover:brightness-95 transition">
            {{ __('borrower.referrals.share_whatsapp') }}
        </a>
        <a href="sms:?body={{ $encodedText }}"
           class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25 transition">
            {{ __('borrower.referrals.share_sms') }}
        </a>
        <button type="button" @click="nativeShare()"
                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25 transition">
            {{ __('borrower.referrals.share_native') }}
        </button>
    </div>
    <p x-show="copied" x-cloak x-transition class="mt-2 text-xs font-medium text-brand-gold">{{ __('borrower.referrals.share_copied') }}</p>
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
