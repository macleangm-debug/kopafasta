@props(['profile'])

@php
    $status = $profile['status'] ?? [];
    $progress = $profile['progress'] ?? [];
    $next = $profile['next_action'] ?? [];
    $isDraft = (bool) ($profile['is_draft'] ?? false);
    $application = $profile['application'] ?? null;
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

    $guarantorInvitations = $profile['guarantor_invitations'] ?? collect();
    $guarantorLinks = $application?->customerGuarantors ?? collect();
    $guarantorTotal = $guarantorInvitations->count() + $guarantorLinks->reject(
        fn ($link) => $guarantorInvitations->contains('customer_guarantor_id', $link->id)
    )->count();
    $guarantorAccepted = 0;
    if ($application && ($application->product?->requires_guarantor ?? false)) {
        $inviteSvc = app(\App\Services\GuarantorInvitationService::class);
        foreach ($guarantorInvitations as $invite) {
            $label = strtolower($inviteSvc->invitationWorkflowStatusLabel($invite));
            if (str_contains($label, 'accepted') || str_contains($label, 'approved')) {
                $guarantorAccepted++;
            }
        }
        foreach ($guarantorLinks as $link) {
            if ($guarantorInvitations->contains('customer_guarantor_id', $link->id)) {
                continue;
            }
            $label = strtolower($inviteSvc->guarantorLinkStatusLabel($link));
            if (str_contains($label, 'accepted') || str_contains($label, 'approved')) {
                $guarantorAccepted++;
            }
        }
    }
    $needsGuarantor = $application && ($application->product?->requires_guarantor ?? false);
    $showChangeGuarantor = $needsGuarantor && $guarantorSupplementOpen;
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
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($editQuoteUrl)
                        <a href="{{ $editQuoteUrl }}"
                           class="inline-flex text-xs font-semibold text-brand bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 px-3 py-2 rounded-lg">
                            {{ __('borrower.loan_profile.actions.edit_quote') }}
                        </a>
                    @endif
                    @if ($editGuarantorUrl)
                        <a href="{{ $editGuarantorUrl }}"
                           class="inline-flex text-xs font-semibold text-brand bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 px-3 py-2 rounded-lg">
                            {{ __('borrower.loan_profile.actions.edit_guarantor') }}
                        </a>
                    @endif
                </div>
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
                <p class="text-sm text-red-700 mt-2">{{ $status['detail'] }}</p>
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

        <div class="px-5 sm:px-6 py-5 space-y-4">
            @if ($needsGuarantor)
                <div class="rounded-xl ring-1 ring-amber-200 bg-gradient-to-br from-amber-50 to-white px-4 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">{{ __('borrower.application.guarantor_section') }}</p>
                            <p class="text-sm font-semibold text-amber-950 mt-1">
                                {{ __('borrower.loan_profile.guarantor_progress', [
                                    'accepted' => $guarantorAccepted,
                                    'total' => max(1, $guarantorTotal),
                                ]) }}
                            </p>
                            @if ($guarantorSupplementOpen)
                                <p class="text-xs text-amber-800 mt-1">{{ __('borrower.guarantor_supplement.borrower_banner') }}</p>
                            @endif
                        </div>
                        @if ($showChangeGuarantor && $editGuarantorUrl)
                            <a href="{{ $editGuarantorUrl }}"
                               class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0">
                                {{ $guarantorSupplementOpen
                                    ? __('borrower.guarantor_supplement.cta')
                                    : __('borrower.apply.change_guarantor') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif

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
        </div>
    </div>
@endif
