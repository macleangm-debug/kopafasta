<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.guarantor_invite.expired_title'))"
    :eyebrow="__('borrower.guarantor_invite.expired_title')"
    :heading="__('borrower.guarantor_invite.expired_title')"
    :lede="__('borrower.guarantor_invite.expired_message')"
    :aside-steps="[
        [__('borrower.guarantor_invite.shell_step_review'), __('borrower.guarantor_invite.shell_step_review_hint')],
        [__('borrower.guarantor_invite.shell_step_decide'), __('borrower.guarantor_invite.shell_step_decide_hint')],
        [__('borrower.guarantor_invite.shell_step_continue'), __('borrower.guarantor_invite.shell_step_continue_hint')],
    ]"
>
    <div class="text-center">
        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-gray-100 text-2xl text-gray-500">⏱</div>
        <h1 class="text-2xl font-bold tracking-tight mb-2">{{ __('borrower.guarantor_invite.expired_title') }}</h1>
        <p class="text-sm text-gray-600 mb-6">{{ __('borrower.guarantor_invite.expired_message') }}</p>
        <a href="{{ route('site.home') }}"
           class="inline-flex justify-center bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-full text-sm">
            {{ __('borrower.guarantor_invite.declined_cta_not_now') }}
        </a>
    </div>
</x-site.guarantor-invite-shell>
