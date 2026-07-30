@props(['profile'])

@php
    $application = $profile['application'] ?? null;
    $needsGuarantor = $application && ($application->product?->requires_guarantor ?? false);
@endphp

@if ($needsGuarantor && ! ($profile['is_draft'] ?? false))
    @php
        $editGuarantorUrl = $profile['edit_guarantor_url'] ?? null;
        $guarantorSupplementOpen = app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($application);
        if (! $editGuarantorUrl && $guarantorSupplementOpen) {
            $editGuarantorUrl = app(\App\Services\GuarantorSupplementService::class)->borrowerWizardUrl($application);
        }

        $guarantorInvitations = $profile['guarantor_invitations'] ?? collect();
        $guarantorLinks = $application?->customerGuarantors ?? collect();
        $guarantorTotal = $guarantorInvitations->count() + $guarantorLinks->reject(
            fn ($link) => $guarantorInvitations->contains('customer_guarantor_id', $link->id)
        )->count();
        $guarantorAccepted = 0;
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
        $showChangeGuarantor = $guarantorSupplementOpen && $editGuarantorUrl;
    @endphp

    <div id="guarantor-progress" class="mb-6 glass-card overflow-hidden ring-1 ring-amber-200/80">
        <div class="bg-gradient-to-br from-amber-50 to-white px-5 py-4">
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
                @if ($showChangeGuarantor)
                    <a href="{{ $editGuarantorUrl }}"
                       class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0">
                        {{ __('borrower.guarantor_supplement.cta') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif
