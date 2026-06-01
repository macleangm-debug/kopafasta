@props(['link' => '', 'code' => '', 'message' => null])

@php
    $shareText = $message ?? 'Join KopaFasta with my referral link and get started with asset-backed loans: '.$link;
    $encodedText = rawurlencode($shareText);
    $encodedLink = rawurlencode($link);
@endphp

<div x-data="referralShare(@js($link), @js($shareText))">
    <div class="flex flex-wrap gap-2">
        <button type="button" @click="copyLink()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold bg-gray-900 text-white hover:bg-gray-800">
            Copy link
        </button>
        <a href="https://wa.me/?text={{ $encodedText }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700">
            WhatsApp
        </a>
        <a href="sms:?body={{ $encodedText }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold bg-sky-600 text-white hover:bg-sky-700">
            SMS
        </a>
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedLink }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700">
            Facebook
        </a>
        <button type="button" @click="nativeShare()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold bg-amber-500 text-gray-900 hover:bg-amber-400">
            Share…
        </button>
    </div>
    <p x-show="copied" x-cloak x-transition class="mt-3 text-sm font-medium text-emerald-700">Link copied to clipboard.</p>
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
                                await navigator.share({ title: 'KopaFasta referral', text: shareText, url: link });
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
