<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.guarantor_invite.declined_thanks_title'))"
    :eyebrow="__('borrower.guarantor_invite.declined_thanks_title')"
    :heading="__('borrower.guarantor_invite.declined_result_title')"
    :lede="__('borrower.guarantor_invite.declined_thanks_message')"
>
    {{-- 1) Clear decline confirmation first — acceptance is already recorded. --}}
    <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-5 -mx-1 sm:mx-0">
        <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-white/10 pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-4 sm:px-5 py-5 text-white text-center sm:text-left">
            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.guarantor_invite.declined_thanks_title') }}</p>
            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight mt-1">{{ __('borrower.guarantor_invite.declined_result_title') }}</h1>
            <p class="mt-2 text-sm text-white/85">{{ __('borrower.guarantor_invite.declined_thanks_message') }}</p>
        </div>
    </section>

    {{-- Optional membership invitation — immediately under the decline confirmation. --}}
    <section class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 sm:px-5 py-5 mb-4 text-left">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">KopaFasta</p>
        <h2 class="text-base sm:text-lg font-bold text-brand mt-1">{{ __('borrower.guarantor_invite.declined_upsell_lede') }}</h2>
        <p class="mt-2 text-sm text-brand/80">{{ __('borrower.guarantor_invite.declined_upsell_body') }}</p>
        <div class="mt-5 flex flex-col gap-3">
            <a href="{{ route('site.register.borrower') }}"
               class="inline-flex w-full justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-3 rounded-xl text-sm shadow-sm">
                {{ __('borrower.guarantor_invite.declined_cta_member') }}
            </a>
            <a href="{{ route('site.home') }}"
               class="inline-flex w-full justify-center text-sm font-semibold text-brand/70 hover:text-brand py-2">
                {{ __('borrower.guarantor_invite.declined_cta_not_now') }}
            </a>
        </div>
    </section>
</x-site.guarantor-invite-shell>
