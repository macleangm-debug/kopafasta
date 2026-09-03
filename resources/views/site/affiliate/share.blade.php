<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.share_title'))" active="share">

    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.nav_share')"
        :title="__('site.affiliate_portal.share_title')"
        :subtitle="__('site.affiliate_portal.share_subtitle')"
    />

    @unless ($eligibility['can_share'] ?? false)
        <div class="glass-card p-5 mb-6 text-sm text-gray-700">{{ __('site.affiliate_portal.eligibility_blocked') }}</div>
    @endunless

    <section class="kf-premium-panel rounded-2xl p-6 sm:p-8 mb-6 relative overflow-hidden">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative grid lg:grid-cols-[1.2fr_0.8fr] gap-6 items-center">
            <div class="space-y-4">
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.promo_code') }}</p>
                    <p class="text-3xl font-bold font-mono tracking-wide mt-1">{{ $links['affiliate_code'] }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.referral_link') }}</p>
                    <p class="text-sm text-white/85 break-all mt-1">{{ $links['affiliate_link'] }}</p>
                </div>
                <x-site.referral-share
                    :link="$links['affiliate_link']"
                    :code="$links['affiliate_code']"
                    :message="$shareMessage"
                    :channels="['copy', 'whatsapp', 'sms', 'native']"
                />
            </div>
            <div class="flex flex-col items-center justify-center">
                <img src="{{ $qrUrl }}" alt="{{ __('site.affiliate_portal.qr_alt') }}" class="size-44 rounded-2xl bg-white p-3 ring-1 ring-white/30">
                <p class="text-xs text-white/70 mt-3 text-center">{{ __('site.affiliate_portal.qr_hint') }}</p>
            </div>
        </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="glass-card p-6 space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.share_message') }}</h2>
            <p class="text-sm text-gray-800 bg-gray-50 rounded-xl p-4 ring-1 ring-gray-100 whitespace-pre-line">{{ $shareMessage }}</p>
            <p class="text-xs text-gray-500">{{ __('site.affiliate_portal.attribution_window_note', ['days' => $attributionWindow]) }}</p>
        </section>

        <section class="glass-card p-6 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-500">{{ __('site.affiliate_portal.personalize_code') }}</h2>
            @if ($canChangeCode)
                <form method="POST" action="{{ route('site.affiliate.profile.update', ['section' => 'personal']) }}"
                      class="space-y-3"
                      x-data
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('site.affiliate_portal.edit_code_title')),
                          message: @js(__('site.affiliate_portal.edit_code_confirm')),
                          confirmLabel: @js(__('site.affiliate_portal.confirm_change')),
                          tone: 'confirm',
                      })">
                    @csrf @method('PUT')
                    <input type="hidden" name="focus" value="promo">
                    <input name="affiliate_code" value="{{ old('affiliate_code', $vendor->affiliate_code) }}"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono uppercase">
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.edit_code') }}</button>
                </form>
            @else
                <p class="text-sm text-gray-600">
                    {{ $nextCodeChangeAt ? __('site.affiliate_portal.code_cooldown', ['days' => max(1, now()->diffInDays($nextCodeChangeAt))]) : __('site.affiliate_portal.code_locked_hint') }}
                </p>
            @endif
        </section>
    </div>

</x-site.affiliate-layout>
