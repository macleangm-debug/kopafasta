<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.guarantor_invite.declined_thanks_title'))"
    :eyebrow="__('borrower.guarantor_invite.declined_thanks_title')"
    :heading="__('borrower.guarantor_invite.declined_cta_title')"
    :lede="__('borrower.guarantor_invite.declined_upsell_lede')"
    :aside-steps="[
        [__('borrower.guarantor_invite.declined_benefit_fast'), __('borrower.guarantor_invite.declined_benefit_fast_hint')],
        [__('borrower.guarantor_invite.declined_benefit_rewards'), __('borrower.guarantor_invite.declined_benefit_rewards_hint')],
        [__('borrower.guarantor_invite.declined_benefit_referrals'), __('borrower.guarantor_invite.declined_benefit_referrals_hint')],
    ]"
>
    <div class="text-center mb-6">
        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-700">✓</div>
        <h1 class="text-2xl font-bold tracking-tight mb-2">{{ __('borrower.guarantor_invite.declined_thanks_title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('borrower.guarantor_invite.declined_thanks_message') }}</p>
    </div>

    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-5 py-6 text-sm text-amber-950 text-left">
        <p class="font-semibold text-base mb-1">{{ __('borrower.guarantor_invite.declined_cta_title') }}</p>
        <p class="text-amber-900/90 mb-4">{{ __('borrower.guarantor_invite.declined_upsell_body') }}</p>
        <ul class="space-y-2.5 mb-6 text-amber-900">
            <li class="flex gap-2"><span class="text-amber-600 font-bold">✓</span>{{ __('borrower.guarantor_invite.declined_benefit_fast') }}</li>
            <li class="flex gap-2"><span class="text-amber-600 font-bold">✓</span>{{ __('borrower.guarantor_invite.declined_benefit_flexible') }}</li>
            <li class="flex gap-2"><span class="text-amber-600 font-bold">✓</span>{{ __('borrower.guarantor_invite.declined_benefit_rewards') }}</li>
            <li class="flex gap-2"><span class="text-amber-600 font-bold">✓</span>{{ __('borrower.guarantor_invite.declined_benefit_referrals') }}</li>
        </ul>
        <div class="flex flex-col gap-3">
            <a href="{{ route('site.register.borrower') }}"
               class="inline-flex justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-3 rounded-full text-sm">
                {{ __('borrower.guarantor_invite.declined_cta_member') }}
            </a>
            <a href="{{ route('site.products') }}"
               class="inline-flex justify-center bg-white ring-1 ring-amber-300 hover:bg-amber-50 text-amber-950 font-semibold px-5 py-3 rounded-full text-sm">
                {{ __('borrower.guarantor_invite.declined_cta_apply') }}
            </a>
            <a href="{{ route('site.home') }}"
               class="inline-flex justify-center text-sm font-semibold text-amber-900/70 hover:text-amber-950 py-1">
                {{ __('borrower.guarantor_invite.declined_cta_not_now') }}
            </a>
        </div>
    </div>
</x-site.guarantor-invite-shell>
