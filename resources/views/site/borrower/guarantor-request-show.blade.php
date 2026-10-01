{{-- Focused task: layout-level focused shell + same max-w-3xl as the loan wizard. --}}
<x-site.borrower-layout
    :title="brand_title(__('borrower.guarantor.detail_title'))"
    active="loans"
    content-width="focused">

    <div class="w-full min-w-0">

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
        $supportBits = collect([
            $borrowerName,
            $productName,
            $loanContext['duration_label'] ?? null,
        ])->filter(fn ($v) => filled($v))->values();
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

    {{-- Premium loan overview (reuse loan-profile hero pattern): amount first, one status --}}
    <section class="mb-6 rounded-2xl p-4 sm:p-5 relative overflow-hidden kf-premium-panel"
             style="view-transition-name: kf-gtr-{{ $customerGuarantor->id }}">
        <div class="absolute -right-16 -top-16 h-44 w-44 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
        <div class="relative space-y-3">
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">
                    {{ $needsReconfirm ? __('borrower.guarantor_invite.revised_terms') : __('borrower.guarantor.request_overview') }}
                </p>
                <p class="text-2xl sm:text-3xl font-extrabold tracking-tight tabular-nums text-white mt-2">
                    {{ $loanContext['amount_label'] }}
                </p>
                <p class="text-sm text-white/85 mt-2">
                    {{ $supportBits->implode(' · ') }}
                </p>
                <p class="text-xs font-mono text-white/65 mt-1">{{ $reference }}</p>
            </div>
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                @if ($badge)
                    <p class="text-xs font-semibold text-white/80">{{ $badge }}</p>
                @endif
                @if ($hasCollateral && filled($collateralLabel))
                    <p class="text-xs text-white/70">{{ $collateralLabel }}</p>
                @endif
            </div>
            @if (filled($loanContext['installment_label'] ?? null) || filled($loanContext['repayment_frequency_label'] ?? null))
                <p class="text-xs text-white/70">
                    @if (filled($loanContext['installment_label'] ?? null))
                        {{ $loanContext['installment_label'] }}
                    @endif
                    @if (filled($loanContext['installment_label'] ?? null) && filled($loanContext['repayment_frequency_label'] ?? null))
                        <span class="text-white/40 mx-1">·</span>
                    @endif
                    @if (filled($loanContext['repayment_frequency_label'] ?? null))
                        {{ $loanContext['repayment_frequency_label'] }}
                    @endif
                </p>
            @endif
        </div>
    </section>

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

    </div>
</x-site.borrower-layout>
