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
        $primaryInvite = $guarantorInvitations->first();
        $share = $primaryInvite ? $inviteSvc->sharePayload($primaryInvite, $application?->customer) : null;
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
        $isHeld = ($application->status ?? '') === 'awaiting_guarantor'
            || ($application->current_stage ?? '') === 'awaiting_guarantor';
        $pending = $guarantorAccepted < max(1, $guarantorTotal);
        $deadline = $application
            ? app(\App\Services\GuarantorDeadlineService::class)->progress($application)
            : null;
    @endphp

    <div id="guarantor-progress" class="mb-6 glass-card overflow-hidden ring-1 {{ $isHeld || $pending ? 'ring-amber-200/80' : 'ring-brand/15' }}"
         x-data="{ copied: false }">
        <div class="bg-gradient-to-br {{ $isHeld || $pending ? 'from-amber-50 to-white' : 'from-brand-muted/40 to-white' }} px-5 py-4 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-widest {{ $isHeld || $pending ? 'text-amber-800' : 'text-brand' }} font-semibold">{{ __('borrower.application.guarantor_section') }}</p>
                    <p class="text-sm font-semibold {{ $isHeld || $pending ? 'text-amber-950' : 'text-gray-900' }} mt-1">
                        {{ __('borrower.loan_profile.guarantor_progress', [
                            'accepted' => $guarantorAccepted,
                            'total' => max(1, $guarantorTotal),
                        ]) }}
                    </p>
                    @if ($isHeld || $pending)
                        <p class="text-xs font-semibold text-amber-900 mt-2">{{ __('borrower.loan_profile.guarantor_hold_banner') }}</p>
                        <p class="text-xs text-amber-800 mt-1">{{ __('borrower.loan_profile.guarantor_hold_body') }}</p>
                    @endif
                    @if (! empty($deadline['label']))
                        <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold {{ ($deadline['days_left'] ?? 1) <= 2 ? 'text-red-700' : 'text-amber-900' }}">
                            <span aria-hidden="true">⏱</span>
                            {{ $deadline['label'] }}
                        </p>
                    @endif
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

            @if (($isHeld || $pending) && $share)
                <div class="flex flex-wrap gap-2">
                    @if (! empty($share['whatsapp_url']))
                        <a href="{{ $share['whatsapp_url'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                            {{ __('borrower.loan_profile.guarantor_nudge_whatsapp') }}
                        </a>
                    @endif
                    @if (! empty($share['invitation_url']))
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($share['invitation_url'])); copied = true; setTimeout(() => copied = false, 2000)"
                                class="inline-flex items-center gap-2 bg-white ring-1 ring-amber-200 hover:bg-amber-50 text-amber-950 font-semibold px-4 py-2.5 rounded-xl text-sm">
                            <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.loan_profile.guarantor_nudge_copy'))"></span>
                        </button>
                    @endif
                </div>
            @endif

            @if ($guarantorInvitations->isNotEmpty() || $guarantorLinks->isNotEmpty())
                <ul class="divide-y divide-amber-100/80 rounded-2xl bg-white/70 ring-1 ring-amber-100 overflow-hidden">
                    @foreach ($guarantorInvitations as $invite)
                        @php
                            $statusLabel = $inviteSvc->invitationWorkflowStatusLabel($invite);
                            $done = str_contains(strtolower($statusLabel), 'accepted')
                                || str_contains(strtolower($statusLabel), 'approved');
                        @endphp
                        <li class="px-4 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $invite->invitee_name ?? $invite->contact ?? '—' }}</p>
                                <p class="text-xs text-gray-600 mt-0.5">{{ $statusLabel }}</p>
                            </div>
                            <span @class([
                                'shrink-0 size-8 rounded-full grid place-items-center ring-1',
                                'bg-brand text-brand-gold ring-brand-gold/40' => $done,
                                'bg-amber-50 text-amber-700 ring-amber-200' => ! $done,
                            ])>
                                @if ($done)
                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <span class="text-[10px] font-bold">!</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
