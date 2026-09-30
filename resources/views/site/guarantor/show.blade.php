@php
    $guarantorService = app(\App\Services\GuarantorInvitationService::class);
    $context = $guarantorService->invitationLoanContext($invitation);
    $comparison = $guarantorService->quoteComparison($invitation);
    $needsReconfirm = $invitation->needsQuoteReconfirmation();
    $borrowerName = $context['borrower_name'] ?? trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name);
    $guarantorName = trim((string) ($invitation->invitee_name ?: '—'));
    $stateLabel = $needsReconfirm
        ? __('borrower.guarantor_invite.state_reconfirm')
        : __('borrower.guarantor_invite.state_pending');
@endphp

<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.guarantor_invite.page_title'))"
    :heading="__('borrower.guarantor_invite.heading')"
    :lede="__('borrower.guarantor_invite.intro')"
>
    <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-5 -mx-1 sm:mx-0">
        <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-4 sm:px-5 py-4 sm:py-5 text-white">
            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.guarantor_invite.heading') }}</p>
            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight mt-1">{{ $context['product_name'] }}</h1>
            <p class="mt-2 text-sm text-white/85">{{ $borrowerName }} · {{ $context['application_reference'] }}</p>
            <p class="mt-3 inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $needsReconfirm ? 'bg-amber-400/25 ring-1 ring-amber-200/40 text-amber-50' : 'bg-white/15 ring-1 ring-white/25' }}">
                {{ $stateLabel }}
            </p>
        </div>
    </section>

    @if ($needsReconfirm)
        <div class="mb-5 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">{{ __('borrower.guarantor_invite.terms_changed_title') }}</p>
            <p class="mt-1 opacity-90">{{ __('borrower.guarantor_invite.terms_changed_body') }}</p>
        </div>

        @if (! empty($comparison['previous']))
            <div class="mb-4 rounded-2xl bg-gray-50 ring-1 ring-gray-200 overflow-hidden text-sm">
                <div class="px-4 py-2.5 border-b border-gray-200">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.previous_terms') }}</p>
                </div>
                <dl class="divide-y divide-gray-200">
                    <div class="px-4 py-2.5 flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.guarantor_invite.amount_label') }}</dt>
                        <dd class="font-semibold text-right line-through text-gray-500">{{ $comparison['previous']['amount_label'] ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.guarantor_invite.duration_label') }}</dt>
                        <dd class="font-semibold text-right line-through text-gray-500">{{ $comparison['previous']['duration_label'] ?? '—' }}</dd>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('borrower.guarantor_invite.installment_label') }}</dt>
                        <dd class="font-semibold text-right line-through text-gray-500">{{ $comparison['previous']['installment_label'] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        @endif

        <p class="mb-2 text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guarantor_invite.revised_terms') }}</p>
    @endif

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
        @if ($invitation->type === 'external')
            <div class="px-4 py-3 flex justify-between gap-3">
                <dt class="text-gray-500">{{ __('borrower.guarantor_invite.guarantor_label') }}</dt>
                <dd class="font-semibold text-right">{{ $guarantorName }}</dd>
            </div>
        @endif
    </dl>

    @unless ($needsReconfirm)
        <p class="text-sm text-gray-600 mb-6 text-center">{{ __('borrower.guarantor_invite.member_benefit') }}</p>
    @endunless

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        @if ($needsReconfirm)
            <form method="POST" action="{{ route('site.guarantor.reconfirm', $invitation->token) }}" class="flex-1"
                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor_invite.reconfirm_title')), message: @js(__('borrower.guarantor_invite.reconfirm_message')), confirmLabel: @js(__('borrower.guarantor_invite.reconfirm_cta')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' })">
                @csrf
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-3 rounded-xl text-sm">{{ __('borrower.guarantor_invite.reconfirm_cta') }}</button>
            </form>
            <form method="POST" action="{{ route('site.guarantor.reject', $invitation->token) }}" class="flex-1"
                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor_invite.reconfirm_decline_title')), message: @js(__('borrower.guarantor_invite.reconfirm_decline_message')), confirmLabel: @js(__('borrower.guarantor_invite.decline')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                @csrf
                <button type="submit" class="w-full bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-3 rounded-xl text-sm">{{ __('borrower.guarantor_invite.decline') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('site.guarantor.accept', $invitation->token) }}" class="flex-1"
                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor_invite.accept_title')), message: @js(__('borrower.guarantor_invite.accept_message')), confirmLabel: @js(__('borrower.guarantor_invite.accept')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' })">
                @csrf
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-3 rounded-xl text-sm">{{ __('borrower.guarantor_invite.accept') }}</button>
            </form>
            <form method="POST" action="{{ route('site.guarantor.reject', $invitation->token) }}" class="flex-1"
                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor_invite.decline_title')), message: @js(__('borrower.guarantor_invite.decline_message')), confirmLabel: @js(__('borrower.guarantor_invite.decline')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                @csrf
                <button type="submit" class="w-full bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-3 rounded-xl text-sm">{{ __('borrower.guarantor_invite.decline') }}</button>
            </form>
        @endif
    </div>
</x-site.guarantor-invite-shell>
