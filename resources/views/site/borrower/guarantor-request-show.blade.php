<x-site.borrower-layout
    :title="brand_title(__('borrower.guarantor.detail_title'))"
    active="loans"
    content-width="narrow">

    @php
        $loanContext = $loanContext ?? app(\App\Services\GuarantorInvitationService::class)->invitationLoanContext($invitation);
        $quoteComparison = $quoteComparison ?? app(\App\Services\GuarantorInvitationService::class)->quoteComparison($invitation);
        $needsReconfirm = $invitation->needsQuoteReconfirmation();
        $borrowerName = $loanContext['borrower_name'] ?? (trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? '')) ?: '—');
        $productName = $loanContext['product_name'] ?? __('borrower.guarantor.loan');
        $reference = $loanContext['application_reference'] ?? '—';
        $profileMet = (bool) ($profileStatus['met'] ?? false);
        $profilePercent = (int) ($profileStatus['percent'] ?? 0);
        $stateLabel = $needsReconfirm
            ? __('borrower.guarantor_invite.state_reconfirm')
            : __('borrower.guarantor.action_required');
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => 'guarantor']) }}" data-kf-motion="pop" class="text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.guarantor.back_to_requests') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-6">
        <div class="absolute -right-16 -top-16 h-44 w-44 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-5 sm:px-6 py-5 sm:py-6 text-white">
            <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.loans_page.guarantor_badge') }}</p>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-1">{{ $productName }}</h1>
            <p class="mt-2 text-sm text-white/85">{{ $borrowerName }} · {{ $reference }}</p>
            <p class="mt-4 inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $needsReconfirm ? 'bg-amber-400/25 ring-1 ring-amber-200/40 text-amber-50' : 'bg-white/15 ring-1 ring-white/25' }}">
                {{ $stateLabel }}
            </p>
        </div>
    </section>

    @if ($needsReconfirm)
        <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">{{ __('borrower.guarantor_invite.terms_changed_title') }}</p>
            <p class="mt-1 opacity-90">{{ __('borrower.guarantor_invite.terms_changed_body') }}</p>
        </div>

        @if (! empty($quoteComparison['previous']))
            <div class="mb-4 glass-card overflow-hidden text-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.previous_terms') }}</p>
                </div>
                <div class="grid sm:grid-cols-3 gap-4 px-5 py-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.amount_label') }}</p>
                        <p class="font-semibold mt-1 line-through text-gray-500">{{ $quoteComparison['previous']['amount_label'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.duration_label') }}</p>
                        <p class="font-semibold mt-1 line-through text-gray-500">{{ $quoteComparison['previous']['duration_label'] ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.installment_label') }}</p>
                        <p class="font-semibold mt-1 line-through text-gray-500">{{ $quoteComparison['previous']['installment_label'] ?? '—' }}</p>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="mb-6 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">{{ __('borrower.guarantor.your_decision') }}</p>
            <p class="mt-1 opacity-90">{{ __('borrower.guarantor.awaiting_your_decision') }}</p>
        </div>
    @endif

    <div class="glass-card p-5 mb-6 ring-1 ring-brand/15">
        <div class="mb-4">
            <h2 class="font-semibold">{{ $needsReconfirm ? __('borrower.guarantor_invite.revised_terms') : __('borrower.loan_profile.summary_title') }}</h2>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.borrower') }}</p>
                <p class="font-semibold mt-1">{{ $borrowerName }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.product_label') }}</p>
                <p class="font-semibold mt-1">{{ $productName }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.amount_label') }}</p>
                <p class="font-semibold mt-1">{{ $loanContext['amount_label'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.duration_label') }}</p>
                <p class="font-semibold mt-1">{{ $loanContext['duration_label'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.frequency_label') }}</p>
                <p class="font-semibold mt-1">{{ $loanContext['repayment_frequency_label'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.installment_label') }}</p>
                <p class="font-semibold mt-1 text-brand">{{ $loanContext['installment_label'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.reference') }}</p>
                <p class="font-semibold mt-1 font-mono">{{ $reference }}</p>
            </div>
        </div>
        @if (! empty($guarantorExposure))
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/10 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_actions.guarantee_exposure') }}</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-gray-900">{{ $guarantorExposure['count'] }}/{{ $guarantorExposure['max'] }}</p>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/10 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_actions.guarantee_total') }}</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-gray-900">{{ format_money($guarantorExposure['exposure']) }}</p>
                </div>
            </div>
        @endif
    </div>

    @unless ($profileMet || $needsReconfirm)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="px-5 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                    <div class="flex items-center gap-3 mt-3 max-w-md">
                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $profilePercent }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-gray-900">{{ $profilePercent }}%</span>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 mt-2">{{ __('borrower.guarantor.profile_after_accept_title') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.guarantor.profile_after_accept_body', ['percent' => $profilePercent]) }}</p>
                </div>
                <a href="{{ route('site.borrower.profile') }}"
                   class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand">
                    {{ __('borrower.loan_profile.complete_profile') }}
                </a>
            </div>
        </div>
    @endunless

    <div class="mb-2 glass-card overflow-hidden ring-1 ring-brand/15">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guarantor.disclaimer_eyebrow') }}</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1">{{ __('borrower.guarantor.your_decision') }}</h2>
            <p class="mt-2 text-sm text-gray-700 leading-relaxed">
                {{ $needsReconfirm
                    ? __('borrower.guarantor_invite.reconfirm_message')
                    : __('borrower.guarantor.disclaimer_body') }}
            </p>

            <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3 sm:items-center sm:justify-end">
                <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}"
                      @submit.prevent="window.confirmForm($el, { title: @js($needsReconfirm ? __('borrower.guarantor_invite.reconfirm_decline_title') : __('borrower.guarantor.decline_title')), message: @js($needsReconfirm ? __('borrower.guarantor_invite.reconfirm_decline_message') : __('borrower.guarantor.decline_message')), confirmLabel: @js(__('borrower.loans_page.decline')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                    @csrf
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="w-full sm:w-auto bg-white ring-1 ring-gray-300 hover:bg-gray-50 text-gray-800 font-semibold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('borrower.loans_page.decline') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}"
                      @submit.prevent="window.confirmForm($el, { title: @js($needsReconfirm ? __('borrower.guarantor_invite.reconfirm_title') : __('borrower.guarantor.approve_title')), message: @js($needsReconfirm ? __('borrower.guarantor_invite.reconfirm_message') : __('borrower.guarantor.approve_message')), confirmLabel: @js($needsReconfirm ? __('borrower.guarantor_invite.reconfirm_cta') : __('borrower.guarantor.approve_cta')), confirmClass: 'bg-brand hover:bg-brand-light text-white' })">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full sm:w-auto bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-3.5 rounded-xl text-sm shadow-sm">
                        {{ $needsReconfirm ? __('borrower.guarantor_invite.reconfirm_cta') : __('borrower.guarantor.approve_cta') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

</x-site.borrower-layout>
