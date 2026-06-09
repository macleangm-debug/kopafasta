@php
    $context = app(\App\Services\GuarantorInvitationService::class)->invitationLoanContext($invitation);
    $borrowerName = trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name);
@endphp

<x-site.layout :title="brand_title(__('borrower.guarantor_invite.accepted_title'))">
    <div class="max-w-xl mx-auto px-4 py-12">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 text-center">
            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-emerald-100 text-2xl">✓</div>
            <h1 class="text-2xl font-bold mb-2">{{ __('borrower.guarantor_invite.accepted_title') }}</h1>
            <p class="text-sm text-gray-600 mb-6">{{ __('borrower.guarantor_invite.accepted_subtitle', ['borrower' => $borrowerName]) }}</p>

            <dl class="rounded-xl bg-gray-50 ring-1 ring-gray-200 divide-y divide-gray-200 text-sm mb-6 text-left">
                <div class="px-4 py-3 flex justify-between gap-3">
                    <dt class="text-gray-500">{{ __('borrower.guarantor_invite.product_label') }}</dt>
                    <dd class="font-semibold text-right">{{ $context['product_name'] }}</dd>
                </div>
                <div class="px-4 py-3 flex justify-between gap-3">
                    <dt class="text-gray-500">{{ __('borrower.guarantor_invite.amount_label') }}</dt>
                    <dd class="font-semibold text-right">{{ $context['amount_label'] }}</dd>
                </div>
            </dl>

            <p class="text-sm text-gray-700 mb-6">{{ __('borrower.guarantor_invite.accepted_next_steps') }}</p>

            <a href="{{ $cta_url }}"
               class="inline-flex w-full justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-3 rounded-full text-sm">
                {{ $cta_label }}
            </a>
        </div>
    </div>
</x-site.layout>
