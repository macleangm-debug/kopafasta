@props(['profile'])

@php
    $status = $profile['status'] ?? [];
    $progress = $profile['progress'] ?? [];
    $next = $profile['next_action'] ?? [];
    $isDraft = (bool) ($profile['is_draft'] ?? false);
    $application = $profile['application'] ?? null;
    $underwritingActions = collect($profile['underwriting_actions'] ?? []);
    $toneClasses = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-amber-100 text-amber-700',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
    ];
    $statusBadge = $toneClasses[$status['tone'] ?? 'gray'] ?? $toneClasses['gray'];
    $profilePercent = (int) ($progress['profile_percent'] ?? $progress['percent'] ?? 0);
    $profileComplete = (bool) ($progress['profile_complete'] ?? $profilePercent >= 100);
    $applicationLabel = $progress['application_status_label'] ?? ($status['label'] ?? '—');
    $applicationPercent = (int) ($progress['application_percent'] ?? $progress['percent'] ?? 0);
    $continueUrl = $next['url'] ?? ($profile['wizard_url'] ?? null);
    $continueLabel = $next['button_label'] ?? __('borrower.loan_profile.actions.continue_to_form');
    $canWithdraw = $application
        && ! in_array((string) $application->status, ['disbursed', 'withdrawn'], true)
        && ! $application->loan;

    // Prefer service-built URLs (product-aware quote step). Do not hardcode quote/guarantor.
    $editQuoteUrl = $profile['edit_quote_url'] ?? null;
    $editGuarantorUrl = $profile['edit_guarantor_url'] ?? null;
    // Change/add guarantor CTA only when underwriting opened a supplement request —
    // never reopen the full apply wizard (which re-triggers application fee).
    $guarantorSupplementOpen = $application
        ? app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($application)
        : false;
    if (! $isDraft && $application && ! $editGuarantorUrl && $guarantorSupplementOpen) {
        $editGuarantorUrl = app(\App\Services\GuarantorSupplementService::class)->borrowerWizardUrl($application);
    }
@endphp

@if ($isDraft)
    <div class="mb-6 glass-card overflow-hidden">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 border-b border-gray-100/80">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.draft_summary_eyebrow') }}</p>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ __('borrower.loan_profile.draft_summary_title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $next['label'] ?? __('borrower.loan_profile.next_actions.continue_form') }}</p>
                </div>
                @if ($continueUrl)
                    <a href="{{ $continueUrl }}"
                       class="inline-flex items-center justify-center font-bold px-8 py-3.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                        {{ $continueLabel }}
                    </a>
                @endif
            </div>

            <div class="mt-5 rounded-xl bg-white ring-1 ring-gray-200/80 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.application_progress') }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $applicationPercent }}%"></div>
                    </div>
                    <span class="text-sm font-bold tabular-nums text-gray-900">{{ $applicationPercent }}%</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ $applicationLabel }}</p>
            </div>

            @if ($editQuoteUrl || $editGuarantorUrl)
                <div class="mt-4 space-y-3">
                    @if ($isDraft && $draft = ($profile['draft'] ?? null))
                        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 via-white to-white"
                             x-data="{ open: {{ $errors->has('requested_amount') || $errors->has('requested_tenure_months') ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open"
                                    class="w-full px-4 py-3 flex items-center justify-between gap-3 text-left">
                                <div>
                                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.actions.edit_quote') }}</p>
                                    <p class="text-sm text-gray-700 mt-0.5">
                                        {{ format_money((float) ($profile['summary']['requested_amount'] ?? 0)) }}
                                        · {{ __('borrower.applications_list.tenure_months', ['count' => (int) ($profile['summary']['requested_tenure'] ?? 0)]) }}
                                    </p>
                                </div>
                                <span class="text-xs font-bold text-brand bg-brand-gold/80 px-3 py-1.5 rounded-lg"
                                      x-text="open ? @js(__('borrower.apply.complete_editing')) : @js(__('borrower.apply.edit'))"></span>
                            </button>
                            <div x-show="open" x-cloak class="px-4 pb-4 border-t border-brand/10 pt-3">
                                <form method="POST" action="{{ route('site.borrower.draft.amount', $draft) }}" class="space-y-3">
                                    @csrf
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        <x-site.numeric-input
                                            name="requested_amount"
                                            :label="__('borrower.applications_list.amount')"
                                            :value="old('requested_amount', $profile['summary']['requested_amount'] ?? '')"
                                            :money="true"
                                            :decimals="0"
                                            :required="true"
                                            class="w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-sm px-3.5 py-2.5"
                                        />
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.applications_list.tenure') }}</label>
                                            <input type="number" name="requested_tenure_months" required min="1" max="120"
                                                   value="{{ old('requested_tenure_months', $profile['summary']['requested_tenure'] ?? '') }}"
                                                   class="w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-sm px-3.5 py-2.5 tabular-nums">
                                        </div>
                                    </div>
                                    <button type="submit" class="inline-flex text-xs font-bold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl shadow-sm">
                                        {{ __('borrower.apply.complete_editing') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif ($editQuoteUrl && $isDraft)
                        <a href="{{ $editQuoteUrl }}"
                           class="inline-flex text-xs font-semibold text-brand bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 px-3 py-2 rounded-lg">
                            {{ __('borrower.loan_profile.actions.edit_quote') }}
                        </a>
                    @endif
                    @if ($editGuarantorUrl && $isDraft)
                        <a href="{{ $editGuarantorUrl }}"
                           class="inline-flex text-xs font-semibold text-brand bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 px-3 py-2 rounded-lg">
                            {{ __('borrower.loan_profile.actions.edit_guarantor') }}
                        </a>
                    @endif
                </div>
            @endif

            @if ($canWithdraw)
                <form method="POST" action="{{ route('site.borrower.application.withdraw', $application) }}" class="mt-4"
                      onsubmit="event.preventDefault(); confirmForm(this, {
                          title: @js(__('borrower.policy.withdraw_confirm_title')),
                          message: @js(__('borrower.policy.withdraw_confirm_body')),
                          confirmLabel: @js(__('borrower.policy.withdraw_confirm_action')),
                          tone: 'warning',
                          confirmClass: 'bg-red-600 hover:bg-red-700 text-white'
                      }); return false;">
                    @csrf
                    <button type="submit" class="inline-flex text-xs font-semibold text-red-700 bg-white ring-1 ring-red-200 hover:bg-red-50 px-3 py-2 rounded-lg">
                        {{ __('borrower.loan_profile.actions.withdraw') }}
                    </button>
                </form>
            @endif
        </div>

            @unless ($profileComplete)
            <div class="px-5 sm:px-6 py-4 border-t border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                        <p class="text-sm text-gray-700 mt-1">
                            <span class="tabular-nums font-bold text-gray-900">{{ $profilePercent }}%</span>
                            — {{ __('borrower.loan_profile.profile_completion_hint_short') }}
                        </p>
                    </div>
                    <a href="{{ route('site.borrower.profile') }}"
                       class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand">
                        {{ __('borrower.loan_profile.complete_profile') }}
                    </a>
                </div>
            </div>
            @elseif (! empty($next['ready']))
            <div class="px-5 sm:px-6 py-4 bg-emerald-50/80 border-t border-emerald-100">
                <p class="text-sm font-semibold text-emerald-800">{{ __('borrower.loan_profile.application_ready') }}</p>
            </div>
            @endif
    </div>
@elseif (($status['code'] ?? '') === 'rejected')
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand-muted/60 to-white ring-1 ring-brand/10 p-4 sm:p-5">
        <div id="rejection" class="rounded-xl bg-white ring-1 ring-red-100 px-4 py-4">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.current_status') }}</p>
            <p class="text-lg font-bold text-red-800 mt-1">{{ __('borrower.applications_list.statuses.not_approved') }}</p>
            @if (! empty($status['detail']))
                @php
                    $detailLines = preg_split("/\n+/", trim((string) $status['detail'])) ?: [];
                    $reasonLine = $detailLines[0] ?? null;
                    $adviceLine = $detailLines[1] ?? null;
                @endphp
                @if ($reasonLine)
                    <p class="text-sm text-red-700 mt-2">{{ $reasonLine }}</p>
                @endif
                @if ($adviceLine)
                    <p class="text-sm text-gray-700 mt-2 rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2">{{ $adviceLine }}</p>
                @elseif (! empty($status['rejection_advice']))
                    <p class="text-sm text-gray-700 mt-2 rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                        {{ __('borrower.loan_profile.rejection_advice', ['advice' => $status['rejection_advice']]) }}
                    </p>
                @endif
            @elseif (! empty($status['rejection_advice']))
                <p class="text-sm text-gray-700 mt-2 rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                    {{ __('borrower.loan_profile.rejection_advice', ['advice' => $status['rejection_advice']]) }}
                </p>
            @endif
            @if (! empty($next['url']))
                <a href="{{ $next['url'] }}"
                   class="inline-flex items-center justify-center font-semibold px-5 py-2.5 rounded-full text-sm mt-4 bg-white ring-1 ring-red-200 hover:bg-red-50 text-red-800">
                    {{ $next['button_label'] ?? __('borrower.loan_profile.actions.view_reason') }}
                </a>
            @endif
        </div>
    </div>
@else
    <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/10">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">{{ __('borrower.loan_profile.current_status') }}</p>
                <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-white/15 text-white ring-1 ring-white/25">{{ $status['label'] ?? '—' }}</span>
            </div>
            <p class="text-lg sm:text-xl font-bold mt-2">{{ $next['label'] ?? __('borrower.loan_profile.next_actions.under_review', ['time' => '']) }}</p>
            <div class="mt-4 flex items-center gap-3 max-w-md">
                <div class="flex-1 h-2 rounded-full bg-white/20 overflow-hidden">
                    <div class="h-full rounded-full bg-brand-gold" style="width: {{ $applicationPercent }}%"></div>
                </div>
                <span class="text-sm font-bold tabular-nums text-brand-gold">{{ $applicationPercent }}%</span>
            </div>
        </div>

        <div class="px-5 sm:px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-1">{{ __('borrower.loan_profile.next_action_title') }}</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $next['label'] ?? __('borrower.loan_profile.next_actions.continue_form') }}</p>
                </div>
                @if (! empty($next['url']) && ! in_array($next['code'] ?? '', ['under_review', 'view_application'], true))
                    <a href="{{ $next['url'] }}"
                       class="inline-flex items-center justify-center font-semibold px-6 py-3 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand font-bold">
                        {{ $next['button_label'] ?? __('borrower.loan_profile.actions.continue_to_form') }}
                    </a>
                @endif
            </div>

            @if ($underwritingActions->isNotEmpty())
                <div id="underwriting-requests" class="mt-5 rounded-xl bg-amber-50 ring-1 ring-amber-200/80 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">{{ __('borrower.loan_profile.uw_feedback_title') }}</p>
                    <p class="text-xs text-amber-900/80 mt-1">{{ __('borrower.loan_profile.uw_feedback_hint') }}</p>
                    <div class="mt-3 flex flex-col gap-2">
                        @foreach ($underwritingActions as $action)
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-lg bg-white/90 ring-1 ring-amber-100 px-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $action['label'] }}</p>
                                    @if (! empty($action['instructions']))
                                        <p class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ $action['instructions'] }}</p>
                                    @endif
                                    @if (! empty($action['rejected']))
                                        <p class="text-[11px] font-semibold text-red-700 mt-1">{{ __('borrower.loan_profile.uw_rejected_hint') }}</p>
                                    @endif
                                </div>
                                <a href="{{ $action['url'] }}"
                                   class="inline-flex items-center justify-center shrink-0 font-bold px-4 py-2 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand">
                                    {{ $action['cta_label'] }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($editGuarantorUrl && $guarantorSupplementOpen)
                <div class="mt-4">
                    <a href="{{ $editGuarantorUrl }}"
                       class="inline-flex text-xs font-semibold text-brand bg-brand-muted/40 ring-1 ring-brand/20 hover:bg-brand-muted px-3 py-2 rounded-lg">
                        {{ __('borrower.guarantor_supplement.cta') }}
                    </a>
                </div>
            @endif

            @if ($canWithdraw)
                <form method="POST" action="{{ route('site.borrower.application.withdraw', $application) }}" class="mt-5"
                      onsubmit="event.preventDefault(); confirmForm(this, {
                          title: @js(__('borrower.policy.withdraw_confirm_title')),
                          message: @js(__('borrower.policy.withdraw_confirm_body')),
                          confirmLabel: @js(__('borrower.policy.withdraw_confirm_action')),
                          tone: 'warning',
                          confirmClass: 'bg-red-600 hover:bg-red-700 text-white'
                      }); return false;">
                    @csrf
                    <button type="submit" class="inline-flex text-sm font-semibold text-red-700 bg-white ring-1 ring-red-200 hover:bg-red-50 px-4 py-2.5 rounded-xl">
                        {{ __('borrower.loan_profile.actions.withdraw') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
