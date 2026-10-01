<x-site.borrower-layout
    :title="brand_title(__('borrower.guarantor.detail_title'))"
    active="loans"
    content-width="narrow">

    @php
        $loanContext = $loanContext ?? app(\App\Services\GuarantorInvitationService::class)->invitationLoanContext($invitation);
        $quoteComparison = $quoteComparison ?? app(\App\Services\GuarantorInvitationService::class)->quoteComparison($invitation);
        $needsReconfirm = $invitation->needsQuoteReconfirmation();
        $decisionRequired = (bool) ($decisionRequired ?? false);
        $inviteStatus = $inviteStatus ?? app(\App\Services\GuarantorInvitationService::class)->borrowerInvitationStatus($invitation);
        $borrowerName = $loanContext['borrower_name'] ?? (trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? '')) ?: '—');
        $productName = $loanContext['product_name'] ?? __('borrower.guarantor.loan');
        $reference = $loanContext['application_reference'] ?? '—';
        $profileMet = (bool) ($profileStatus['met'] ?? false);
        $profilePercent = (int) ($profileStatus['percent'] ?? 0);
        $steps = $inviteStatus['steps'] ?? [];
        $badge = $inviteStatus['label'] ?? null;
        $badgeTone = match (true) {
            (bool) ($inviteStatus['ready'] ?? false) => 'emerald',
            $profilePercent > 0 || ($inviteStatus['account_opened'] ?? false) => 'amber',
            default => 'sky',
        };
        $hasCollateral = (bool) ($loanContext['has_collateral'] ?? false);
        $collateralLabel = $loanContext['collateral_label'] ?? null;
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
            @if ($badge)
                <p class="mt-4 inline-flex text-xs font-semibold rounded-full px-3 py-1.5 bg-white/15 ring-1 ring-white/25">
                    {{ $badge }}
                </p>
            @endif
        </div>
    </section>

    <div class="glass-card p-5 mb-6 ring-1 ring-brand/15">
        <h2 class="font-semibold mb-4">{{ $needsReconfirm ? __('borrower.guarantor_invite.revised_terms') : __('borrower.guarantor.request_overview') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
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
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.borrower') }}</p>
                <p class="font-semibold mt-1">{{ $borrowerName }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.reference') }}</p>
                <p class="font-semibold mt-1 font-mono">{{ $reference }}</p>
            </div>
        </div>
    </div>

    <div class="mb-6">
        <x-site.invitee-progress
            :name="''"
            :badge="$badge"
            :badge-tone="$badgeTone"
            :steps="$steps"
        />
    </div>

    <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
        <div class="px-5 sm:px-6 py-5 space-y-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guarantor.liability_eyebrow') }}</p>
                <h2 class="text-lg font-bold text-gray-900 mt-1">{{ __('borrower.guarantor.liability_title') }}</h2>
                <p class="mt-2 text-sm text-gray-700 leading-relaxed">{{ __('borrower.guarantor.liability_body') }}</p>
            </div>
            @if ($hasCollateral && filled($collateralLabel))
                <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-3 text-sm">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guarantor.loan_collateral_title') }}</p>
                    <p class="mt-1 text-gray-800 font-medium">{{ $collateralLabel }}</p>
                </div>
            @endif
        </div>
    </div>

    @if ($decisionRequired)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15"
             x-data="{ submitting: false, action: null }">
            <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guarantor.your_decision') }}</p>
                <p class="mt-2 text-sm text-gray-700 leading-relaxed">
                    {{ $needsReconfirm
                        ? __('borrower.guarantor_invite.reconfirm_message')
                        : __('borrower.guarantor.decision_short') }}
                </p>

                <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3 sm:items-center sm:justify-end">
                    <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}"
                          @submit="if (submitting) { $event.preventDefault(); return false } submitting = true; action = 'reject'">
                        @csrf
                        <input type="hidden" name="action" value="reject">
                        <button type="submit"
                                :disabled="submitting"
                                class="w-full sm:w-auto bg-white ring-1 ring-gray-300 hover:bg-gray-50 text-gray-800 font-semibold px-5 py-2.5 rounded-xl text-sm disabled:opacity-60 disabled:cursor-wait">
                            <span x-show="!(submitting && action === 'reject')">{{ __('borrower.loans_page.decline') }}</span>
                            <span x-cloak x-show="submitting && action === 'reject'">{{ __('borrower.guarantor.saving') }}…</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}"
                          @submit="if (submitting) { $event.preventDefault(); return false } submitting = true; action = 'approve'">
                        @csrf
                        <input type="hidden" name="action" value="approve">
                        <button type="submit"
                                :disabled="submitting"
                                class="w-full sm:w-auto bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-3.5 rounded-xl text-sm shadow-sm disabled:opacity-60 disabled:cursor-wait">
                            <span x-show="!(submitting && action === 'approve')">
                                {{ $needsReconfirm ? __('borrower.guarantor_invite.reconfirm_cta') : __('borrower.guarantor.accept_request_cta') }}
                            </span>
                            <span x-cloak x-show="submitting && action === 'approve'">{{ __('borrower.guarantor.saving') }}…</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @elseif (! $profileMet)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="px-5 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-2">
                        {{ __('borrower.guarantor.profile_next_short', ['percent' => $profilePercent]) }}
                    </p>
                    <div class="flex items-center gap-3 mt-3 max-w-md">
                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ max(0, min(100, $profilePercent)) }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-gray-900">{{ $profilePercent }}%</span>
                    </div>
                </div>
                <a href="{{ route('site.borrower.profile') }}"
                   class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand">
                    {{ __('borrower.loan_profile.complete_profile') }}
                </a>
            </div>
        </div>
    @else
        <div class="mb-6 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
            {{ __('borrower.guarantor.ready_waiting_review') }}
        </div>
    @endif

</x-site.borrower-layout>
