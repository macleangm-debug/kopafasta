<x-site.layout :title="brand_title(__('site.affiliate_portal.verify_page_title'))">
    <div class="min-h-[70vh] premium-gradient py-12 px-4">
        <div class="max-w-lg mx-auto">
            <div class="text-center mb-8">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.affiliate_portal.verify_eyebrow') }}</p>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2">{{ __('site.affiliate_portal.verify_page_title') }}</h1>
                <p class="text-sm text-gray-500 mt-2">{{ __('site.affiliate_portal.verify_page_subtitle') }}</p>
            </div>

            @if ($verified && $affiliate)
                <div class="glass-card rounded-2xl ring-1 ring-emerald-200/80 p-8 text-center bg-white/90">
                    <div class="mx-auto mb-4 size-16 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center text-2xl font-bold ring-1 ring-emerald-200">✓</div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700 font-semibold">{{ __('site.affiliate_portal.verified_badge') }}</p>
                    <h2 class="text-xl font-bold text-gray-900 mt-2">{{ $affiliate->name }}</h2>
                    <p class="mt-2 font-mono text-sm text-brand font-semibold">{{ $affiliate->affiliate_code ?? $code }}</p>
                    @if (! empty($notice))
                        <p class="mt-4 text-sm text-gray-600">{{ $notice }}</p>
                    @endif
                    @if (! empty($verify_url))
                        <div class="mt-6 flex flex-col items-center gap-2">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data={{ urlencode($verify_url) }}"
                                 alt="Affiliate verification QR" class="size-36 rounded-xl bg-white p-2 ring-1 ring-gray-200">
                            <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.verify_qr_hint') }}</p>
                        </div>
                    @endif
                    @if ($affiliate->phone)
                        <p class="mt-4 text-xs text-gray-500">{{ $affiliate->phone }}</p>
                    @endif
                </div>
            @elseif ($affiliate)
                <div class="glass-card rounded-2xl ring-1 ring-amber-200/80 p-8 text-center bg-white/90">
                    <p class="text-[10px] uppercase tracking-widest text-amber-700 font-semibold">{{ __('site.affiliate_portal.kyc_pending') }}</p>
                    <h2 class="text-xl font-bold text-gray-900 mt-2">{{ $affiliate->name }}</h2>
                    <p class="mt-2 font-mono text-sm">{{ $affiliate->affiliate_code ?? $code }}</p>
                    <p class="mt-4 text-sm text-amber-800">{{ __('site.affiliate_portal.verify_unverified_body') }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ __('site.affiliate_portal.kyc_status', ['status' => ucfirst($affiliate->affiliate_kyc_status ?? 'pending')]) }}</p>
                </div>
            @else
                <div class="glass-card rounded-2xl ring-1 ring-gray-200 p-8 text-center bg-white/90">
                    <h2 class="text-xl font-bold text-gray-900">{{ __('site.affiliate_portal.verify_not_found') }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.affiliate_portal.verify_not_found_body', ['code' => $code]) }}</p>
                </div>
            @endif

            <p class="text-center text-xs text-gray-400 mt-8">
                <a href="{{ route('site.home') }}" class="text-brand font-semibold hover:underline">{{ brand_name() }}</a>
            </p>
        </div>
    </div>
</x-site.layout>
