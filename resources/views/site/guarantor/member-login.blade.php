@php
    $context = app(\App\Services\GuarantorInvitationService::class)->invitationLoanContext($invitation);
    $borrowerName = $context['borrower_name']
        ?? trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? ''));
@endphp

<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.guarantor_invite.page_title'))"
    :heading="__('borrower.guarantor_invite.heading')"
    :lede="__('borrower.guarantor_invite.member_login_subtitle', ['borrower' => $borrowerName])"
>
    <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-5 -mx-1 sm:mx-0">
        <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-4 sm:px-5 py-4 sm:py-5 text-white">
            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.guarantor_invite.heading') }}</p>
            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight mt-1">{{ $context['product_name'] }}</h1>
            <p class="mt-2 text-sm text-white/85">{{ $borrowerName }} · {{ $context['application_reference'] }}</p>
            <p class="mt-3 inline-flex text-xs font-semibold rounded-full px-3 py-1.5 bg-white/15 ring-1 ring-white/25">
                {{ __('borrower.guarantor_invite.state_pending') }}
            </p>
        </div>
    </section>

    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.guarantor_invite.member_login_subtitle', ['borrower' => $borrowerName]) }}</p>

    <dl class="glass-card rounded-2xl ring-1 ring-brand/10 divide-y divide-gray-100 text-sm mb-6 overflow-hidden">
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.borrower_label') }}</dt>
            <dd class="font-semibold text-right text-brand">{{ $borrowerName }}</dd>
        </div>
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
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.frequency_label') }}</dt>
            <dd class="font-semibold text-right">{{ $context['repayment_frequency_label'] }}</dd>
        </div>
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.installment_label') }}</dt>
            <dd class="font-semibold text-right text-brand">{{ $context['installment_label'] }}</dd>
        </div>
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.reference_label') }}</dt>
            <dd class="font-semibold text-right font-mono">{{ $context['application_reference'] }}</dd>
        </div>
    </dl>

    <a href="{{ route('site.login') }}"
       class="inline-flex w-full justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-3 rounded-xl text-sm shadow-sm">
        {{ __('borrower.guarantor_invite.member_login_button') }}
    </a>

    <p class="mt-4 text-center">
        <a href="{{ route('site.login', ['clear_guarantor' => 1]) }}" class="text-sm text-gray-600 hover:underline">
            {{ __('borrower.guarantor_invite.login_different_account') }}
        </a>
    </p>
</x-site.guarantor-invite-shell>
