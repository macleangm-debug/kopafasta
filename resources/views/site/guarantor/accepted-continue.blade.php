@php
    $context = app(\App\Services\GuarantorInvitationService::class)->invitationLoanContext($invitation);
    $borrowerName = $context['borrower_name']
        ?? trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? ''));
@endphp

<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.guarantor_invite.accepted_title'))"
    :eyebrow="__('borrower.guarantor_invite.accepted_title')"
    :heading="__('borrower.guarantor_invite.accepted_title')"
    :lede="__('borrower.guarantor_invite.accepted_next_steps')"
    :aside-steps="[
        [__('borrower.guarantor_invite.shell_step_account'), __('borrower.guarantor_invite.shell_step_account_hint')],
        [__('borrower.guarantor_invite.shell_step_profile'), __('borrower.guarantor_invite.shell_step_profile_hint')],
        [__('borrower.guarantor_invite.shell_step_sign'), __('borrower.guarantor_invite.shell_step_sign_hint')],
    ]"
>
    <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-5 -mx-1 sm:mx-0">
        <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-brand-gold/15 pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-4 sm:px-5 py-5 text-white">
            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.guarantor_invite.accepted_title') }}</p>
            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight mt-1">{{ __('borrower.guarantor_invite.accepted_result_title') }}</h1>
            <p class="mt-2 text-sm text-white/85">{{ __('borrower.guarantor_invite.accepted_subtitle', ['borrower' => $borrowerName]) }}</p>
        </div>
    </section>

    <dl class="glass-card rounded-2xl ring-1 ring-brand/10 divide-y divide-gray-100 text-sm mb-5 overflow-hidden">
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.product_label') }}</dt>
            <dd class="font-semibold text-right">{{ $context['product_name'] }}</dd>
        </div>
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.amount_label') }}</dt>
            <dd class="font-semibold text-right">{{ $context['amount_label'] }}</dd>
        </div>
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.duration_label') }}</dt>
            <dd class="font-semibold text-right">{{ $context['duration_label'] }}</dd>
        </div>
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.installment_label') }}</dt>
            <dd class="font-semibold text-right text-brand">{{ $context['installment_label'] }}</dd>
        </div>
    </dl>

    <p class="text-sm text-gray-700 mb-5">{{ __('borrower.guarantor_invite.accepted_next_steps') }}</p>

    <a href="{{ $cta_url }}"
       class="inline-flex w-full justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
        {{ $cta_label }}
    </a>

    <p class="mt-4 text-center">
        <a href="{{ route('site.login', ['clear_guarantor' => 1]) }}" class="text-sm text-gray-600 hover:underline">
            {{ __('borrower.guarantor_invite.login_different_account') }}
        </a>
    </p>
</x-site.guarantor-invite-shell>
