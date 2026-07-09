@php
    $guarantorService = app(\App\Services\GuarantorInvitationService::class);
    $context = $guarantorService->invitationLoanContext($invitation);
    $borrowerName = trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name);
    $guarantorName = trim((string) ($invitation->invitee_name ?: '—'));
@endphp

<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.guarantor_invite.page_title'))"
    :heading="__('borrower.guarantor_invite.heading')"
    :lede="__('borrower.guarantor_invite.intro')"
>
    <h1 class="text-2xl font-bold tracking-tight mb-2">{{ __('borrower.guarantor_invite.heading') }}</h1>
    <p class="text-sm text-gray-600 mb-6">{{ __('borrower.guarantor_invite.request_explanation') }}</p>

    <dl class="rounded-xl bg-gray-50 ring-1 ring-gray-200 divide-y divide-gray-200 text-sm mb-6">
        <div class="px-4 py-3 flex justify-between gap-3">
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.borrower_label') }}</dt>
            <dd class="font-semibold text-right">{{ $borrowerName }}</dd>
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
            <dt class="text-gray-500">{{ __('borrower.guarantor_invite.installment_label') }}</dt>
            <dd class="font-semibold text-right">{{ $context['installment_label'] }}</dd>
        </div>
        @if ($invitation->type === 'external')
            <div class="px-4 py-3 flex justify-between gap-3">
                <dt class="text-gray-500">{{ __('borrower.guarantor_invite.guarantor_label') }}</dt>
                <dd class="font-semibold text-right">{{ $guarantorName }}</dd>
            </div>
        @endif
    </dl>

    <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 text-sm text-sky-900 mb-6">
        <p class="font-semibold mb-1">{{ __('borrower.guarantor_invite.role_heading') }}</p>
        <p>{{ __('borrower.guarantor_invite.role_body') }}</p>
    </div>

    @if ($invitation->type === 'external')
        <p class="text-sm text-gray-600 mb-6">{{ __('borrower.guarantor_invite.external_profile_note') }}</p>
    @endif

    <div class="flex flex-col sm:flex-row gap-3">
        <form method="POST" action="{{ route('site.guarantor.accept', $invitation->token) }}" class="flex-1"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor_invite.accept_title')), message: @js(__('borrower.guarantor_invite.accept_message')), confirmLabel: @js(__('borrower.guarantor_invite.accept')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' })">
            @csrf
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-3 rounded-full text-sm">{{ __('borrower.guarantor_invite.accept') }}</button>
        </form>
        <form method="POST" action="{{ route('site.guarantor.reject', $invitation->token) }}" class="flex-1"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor_invite.decline_title')), message: @js(__('borrower.guarantor_invite.decline_message')), confirmLabel: @js(__('borrower.guarantor_invite.decline')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
            @csrf
            <button type="submit" class="w-full bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-3 rounded-full text-sm">{{ __('borrower.guarantor_invite.decline') }}</button>
        </form>
    </div>
</x-site.guarantor-invite-shell>
