@props(['profile'])

@php
    $application = $profile['application'] ?? null;
    $isDraft = (bool) ($profile['is_draft'] ?? false);
    $guarantorInvitations = $profile['guarantor_invitations'] ?? collect();
    $guarantorLinks = $application?->customerGuarantors ?? collect();
    $needsGuarantor = (bool) ($profile['requires_guarantor'] ?? false)
        || ($application?->product?->requires_guarantor ?? false)
        || $guarantorInvitations->isNotEmpty()
        || $guarantorLinks->isNotEmpty();
@endphp

@if ($needsGuarantor)
    @php
        $supplementSvc = app(\App\Services\GuarantorSupplementService::class);
        $inviteSvc = app(\App\Services\GuarantorInvitationService::class);
        $customer = $application?->customer ?? ($profile['draft']?->customer ?? null);

        $editGuarantorUrl = $profile['edit_guarantor_url'] ?? null;
        $guarantorSupplementOpen = $application ? $supplementSvc->hasOpenRequest($application) : false;
        $isAdditionalSupplement = $application ? $supplementSvc->hasOpenAdditionalRequest($application) : false;
        $isChangeSupplement = $application ? $supplementSvc->hasOpenChangeRequest($application) : false;
        $awaitingQuoteReconfirm = $application ? $supplementSvc->awaitingQuoteReconfirm($application) : false;
        if (! $isDraft && $application && ! $editGuarantorUrl && $guarantorSupplementOpen) {
            $editGuarantorUrl = $supplementSvc->borrowerWizardUrl($application);
        }
        if (! $isDraft && ! $guarantorSupplementOpen) {
            $editGuarantorUrl = null;
        }

        $historyCodes = ['rejected', 'declined', 'expired', 'cancelled', 'replaced'];
        $activeInviteStatuses = ['pending', 'accepted', 'opened', 'sent'];
        $activeLinkStatuses = ['pending', 'approved'];

        $currentRows = collect();
        $historyRows = collect();

        foreach ($guarantorInvitations as $invite) {
            $status = $inviteSvc->borrowerInvitationStatus($invite);
            $gCustomer = $invite->guarantorCustomer
                ?? ($invite->guarantor_customer_id
                    ? \App\Models\Customer::query()->find($invite->guarantor_customer_id)
                    : null);
            $memberNo = $gCustomer?->member_no
                ?? $invite->membership_id
                ?? null;
            $row = (object) [
                'name'      => $invite->invitee_name
                    ?? $gCustomer?->legalDisplayName()
                    ?? $invite->contact
                    ?? '—',
                'type'      => __('borrower.application.guarantor_role'),
                'member_no' => $memberNo,
                'phone'     => $invite->contact ?? $gCustomer?->phone,
                'status'    => $status,
                'share'     => $inviteSvc->sharePayload($invite, $customer),
                'invite'    => $invite,
            ];
            $inviteStatus = (string) ($invite->status ?? '');
            $code = (string) ($status['code'] ?? '');
            if (in_array($inviteStatus, $historyCodes, true) || in_array($code, ['rejected', 'expired'], true)) {
                $historyRows->push($row);
            } else {
                $currentRows->push($row);
            }
        }

        foreach ($guarantorLinks as $link) {
            if ($guarantorInvitations->contains('customer_guarantor_id', $link->id)) {
                continue;
            }
            $status = $inviteSvc->workflowStatus($link);
            $gCustomer = app(\App\Services\GuarantorAccessService::class)->guarantorCustomerForLink($link);
            $code = (string) ($status['code'] ?? '');
            $linkStatus = (string) ($link->status ?? '');
            $row = (object) [
                'name'      => $link->displayName(),
                'type'      => __('borrower.application.guarantor_role'),
                'member_no' => $gCustomer?->member_no,
                'phone'     => $gCustomer?->phone,
                'status'    => array_merge($status, [
                    'profile_percent' => null,
                    'accepted' => in_array($code, ['ready', 'pending_profile'], true),
                    'ready' => $code === 'ready',
                    'steps' => [],
                ]),
                'share'     => null,
                'invite'    => null,
            ];
            if (in_array($linkStatus, $historyCodes, true) || in_array($code, ['rejected', 'expired'], true)) {
                $historyRows->push($row);
            } elseif (in_array($linkStatus, $activeLinkStatuses, true)) {
                $currentRows->push($row);
            } else {
                $historyRows->push($row);
            }
        }

        $readyCount = $currentRows->filter(fn ($row) => ($row->status['ready'] ?? false) || ($row->status['code'] ?? '') === 'ready')->count();
        $allReady = $currentRows->isNotEmpty() && $readyCount >= $currentRows->count();
        $primary = $currentRows->first();
        $share = $primary?->share;
        $primaryCode = (string) ($primary?->status['code'] ?? '');
        $primaryPendingInvite = in_array($primaryCode, ['pending_acceptance', 'invitation_sent'], true);
        $primaryIncomplete = in_array($primaryCode, ['pending_profile', 'guarantee_pending', 'registration_in_progress', 'kyc_in_progress'], true);
        $primaryReady = ($primary?->status['ready'] ?? false) || $primaryCode === 'ready';

        $showChangeGuarantor = ($isDraft && $editGuarantorUrl) || ($guarantorSupplementOpen && $editGuarantorUrl);
        $canChangeWhileHeld = ! $isDraft && ! $showChangeGuarantor && (bool) ($profile['can_change_guarantor_while_held'] ?? false);
        $isHeld = $application && (
            ($application->status ?? '') === 'awaiting_guarantor'
            || ($application->current_stage ?? '') === 'awaiting_guarantor'
        );
        $deadline = $application
            ? app(\App\Services\GuarantorDeadlineService::class)->progress($application)
            : null;

        // State machine for Guarantor Details (Section P).
        $uiState = match (true) {
            $awaitingQuoteReconfirm => 'quote_reconfirm',
            $allReady && ! $isDraft => 'completed',
            $allReady && $isDraft => 'ready_before_submit',
            $currentRows->isEmpty() && ($historyRows->isNotEmpty() || $isChangeSupplement || $canChangeWhileHeld || $guarantorSupplementOpen) => 'needs_replacement',
            $currentRows->isEmpty() => 'required_empty',
            $primaryIncomplete => 'accepted_incomplete',
            $primaryPendingInvite => 'pending',
            default => 'pending',
        };

        $showInviteActions = in_array($uiState, ['pending', 'accepted_incomplete'], true)
            && $share
            && empty($share['ready']);
        $showCountdown = $isHeld && $showInviteActions && (! empty($deadline['label']) || isset($deadline['days_left']));
        $showChangeSecondary = in_array($uiState, ['pending', 'accepted_incomplete'], true)
            && ($showChangeGuarantor || $canChangeWhileHeld);
        $showPrimaryChoose = in_array($uiState, ['needs_replacement', 'required_empty'], true)
            && ($showChangeGuarantor || $canChangeWhileHeld || ($isDraft && $editGuarantorUrl));
        // UW instruction only while borrower still must nominate — never after a replacement is invited.
        $supplementBanner = null;
        if ($isAdditionalSupplement && $uiState !== 'pending' && $uiState !== 'accepted_incomplete') {
            $supplementBanner = $supplementSvc->borrowerBanner($application);
        } elseif ($isChangeSupplement && $uiState === 'needs_replacement') {
            $supplementBanner = $supplementSvc->borrowerBanner($application);
        }
        $primaryCtaLabel = $isAdditionalSupplement
            ? __('borrower.guarantor_supplement.cta')
            : __('borrower.guarantor_supplement.change_cta');
        if ($isDraft && $uiState === 'required_empty') {
            $primaryCtaLabel = __('borrower.loan_profile.actions.complete_guarantor');
        }
    @endphp

    <div id="guarantor-progress" class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15"
         x-data="{ copied: false }">
        @if ($uiState === 'completed')
            {{-- Submitted + ready: premium guarantor details card. --}}
            <div class="relative overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-light text-white">
                <div class="absolute -right-10 -top-10 size-40 rounded-full bg-white/10 pointer-events-none"></div>
                <div class="absolute -left-8 -bottom-12 size-32 rounded-full bg-white/10 pointer-events-none"></div>
                <div class="relative px-5 sm:px-6 py-5 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold/90 font-semibold">{{ __('borrower.application.guarantor_section') }}</p>
                            @foreach ($currentRows as $row)
                                @php
                                    $memberDisplay = \App\Support\MemberNumberFormatter::display($row->member_no ?? null);
                                @endphp
                                <p class="text-xl sm:text-2xl font-bold tracking-tight mt-2 truncate">{{ $row->name }}</p>
                                <p class="text-sm text-white/75 mt-1">{{ $row->type }}</p>
                                @if ($memberDisplay !== '—')
                                    <div class="mt-4 rounded-xl bg-black/20 ring-1 ring-white/20 px-4 py-3">
                                        <p class="text-[10px] uppercase tracking-[0.18em] text-white/60 mb-1.5">{{ __('borrower.apply.guarantor_fields.membership_no') }}</p>
                                        <p class="font-mono text-base sm:text-lg font-bold tracking-[0.12em] break-all">{{ $memberDisplay }}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @if ($showChangeGuarantor && $isAdditionalSupplement)
                            <a href="{{ $editGuarantorUrl }}"
                               class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                {{ __('borrower.guarantor_supplement.cta') }}
                            </a>
                        @endif
                    </div>
                    @if ($isAdditionalSupplement && $supplementBanner)
                        <p class="text-xs text-amber-100">{{ $supplementBanner }}</p>
                    @endif
                </div>
            </div>
        @else
            <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.application.guarantor_section') }}</p>
                        @if (in_array($uiState, ['needs_replacement', 'required_empty', 'quote_reconfirm'], true))
                            <h2 class="text-lg font-bold text-gray-900 mt-1">
                                @if ($uiState === 'needs_replacement')
                                    {{ __('borrower.loan_profile.guarantor_declined_title') }}
                                @elseif ($uiState === 'required_empty')
                                    {{ __('borrower.loan_profile.guarantor_not_added') }}
                                @else
                                    {{ __('borrower.loan_profile.guarantor_reconfirm_title') }}
                                @endif
                            </h2>
                            <p class="text-sm text-gray-600 mt-1">
                                @if ($uiState === 'needs_replacement')
                                    {{ __('borrower.loan_profile.guarantor_required_body') }}
                                @elseif ($uiState === 'required_empty')
                                    {{ __('borrower.loan_profile.guarantor_not_added_hint') }}
                                @else
                                    {{ __('borrower.loan_profile.guarantor_reconfirm_body') }}
                                @endif
                            </p>
                        @elseif ($primary)
                            {{-- Progress component is the single status source of truth — name/phone only here. --}}
                            <p class="text-lg sm:text-xl font-bold text-gray-900 mt-1 truncate">
                                {{ $primary->name }}
                                @if (! empty($primary->phone) && $uiState !== 'ready_before_submit')
                                    <span class="font-semibold text-gray-600">· {{ $primary->phone }}</span>
                                @endif
                            </p>
                            @if ($uiState === 'ready_before_submit')
                                <p class="text-sm text-gray-600 mt-1">{{ $primary->type }}</p>
                            @endif
                        @endif
                    </div>

                    @if ($showPrimaryChoose)
                        @if ($showChangeGuarantor && $editGuarantorUrl)
                            <a href="{{ $editGuarantorUrl }}"
                               class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                {{ $primaryCtaLabel }}
                            </a>
                        @elseif ($isDraft && $editGuarantorUrl)
                            <a href="{{ $editGuarantorUrl }}"
                               class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                {{ $primaryCtaLabel }}
                            </a>
                        @elseif ($canChangeWhileHeld && $application)
                            <form method="POST" action="{{ route('site.borrower.application.change-guarantor', $application) }}"
                                  @submit.prevent="window.confirmForm($el, {
                                      title: @js(__('borrower.guarantor_supplement.borrower_change_confirm_title')),
                                      message: @js(__('borrower.guarantor_supplement.borrower_change_confirm_body')),
                                      confirmLabel: @js(__('borrower.guarantor_supplement.change_cta')),
                                      confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand'
                                  })">
                                @csrf
                                <button type="submit"
                                        class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                    {{ __('borrower.guarantor_supplement.change_cta') }}
                                </button>
                            </form>
                        @endif
                    @elseif ($showChangeSecondary)
                        @if ($showChangeGuarantor && $editGuarantorUrl)
                            <a href="{{ $editGuarantorUrl }}"
                               class="inline-flex bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                {{ $isAdditionalSupplement
                                    ? __('borrower.guarantor_supplement.cta')
                                    : __('borrower.guarantor_supplement.change_cta') }}
                            </a>
                        @elseif ($canChangeWhileHeld && $application)
                            <form method="POST" action="{{ route('site.borrower.application.change-guarantor', $application) }}"
                                  @submit.prevent="window.confirmForm($el, {
                                      title: @js(__('borrower.guarantor_supplement.borrower_change_confirm_title')),
                                      message: @js(__('borrower.guarantor_supplement.borrower_change_confirm_body')),
                                      confirmLabel: @js(__('borrower.guarantor_supplement.change_cta')),
                                      confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand'
                                  })">
                                @csrf
                                <button type="submit"
                                        class="inline-flex bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                    {{ __('borrower.guarantor_supplement.change_cta') }}
                                </button>
                            </form>
                        @endif
                    @endif
                </div>

                @if ($showCountdown)
                    <x-site.deadline-badge
                        :label="$deadline['label'] ?? null"
                        :days-left="$deadline['days_left'] ?? null"
                        :date="$deadline['date'] ?? null"
                        :purpose="__('borrower.loan_profile.deadline_purpose_guarantor_profile')"
                        :urgent="($deadline['days_left'] ?? 99) <= 2"
                        :expired="(bool) ($deadline['expired'] ?? false)"
                    />
                @endif

                {{-- Additional-only banner. Replacement never uses “Underwriting needs another…” once nominated. --}}
                @if ($isAdditionalSupplement && $supplementBanner)
                    <p class="text-xs text-amber-800">{{ $supplementBanner }}</p>
                @elseif ($isChangeSupplement && $supplementBanner && $uiState === 'needs_replacement')
                    <p class="text-xs text-amber-800">{{ $supplementBanner }}</p>
                @elseif ($canChangeWhileHeld && $uiState === 'pending')
                    <p class="text-xs text-gray-500">{{ __('borrower.guarantor_supplement.borrower_change_hint') }}</p>
                @endif

                @if ($showInviteActions)
                    <div class="flex flex-nowrap items-center gap-2 overflow-x-auto pb-0.5 -mx-0.5 px-0.5 scrollbar-none">
                        @if (! empty($share['whatsapp_url']))
                            <a href="{{ $share['whatsapp_url'] }}" target="_blank" rel="noopener"
                               class="inline-flex shrink-0 items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-3 sm:px-4 py-2.5 rounded-xl text-xs sm:text-sm whitespace-nowrap">
                                {{ __('borrower.loan_profile.guarantor_nudge_whatsapp') }}
                            </a>
                        @endif
                        @if (! empty($share['invitation_url']) || ! empty($share['short_url']))
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js($share['short_url'] ?? $share['invitation_url'])); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="inline-flex shrink-0 items-center gap-1.5 bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-3 sm:px-4 py-2.5 rounded-xl text-xs sm:text-sm whitespace-nowrap">
                                <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.loan_profile.guarantor_nudge_copy'))"></span>
                            </button>
                        @endif
                        @if ($application && $primary?->invite && in_array((string) ($primary->invite->status ?? ''), ['pending', 'accepted'], true)
                            && ($primary->invite->type ?? '') === 'external')
                            <a href="{{ app(\App\Services\GuarantorSupplementService::class)->borrowerEditGuarantorUrl($application) }}"
                               class="inline-flex shrink-0 items-center gap-1.5 bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-3 sm:px-4 py-2.5 rounded-xl text-xs sm:text-sm whitespace-nowrap">
                                {{ __('borrower.loan_profile.actions.edit_guarantor') }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Current guarantor progress — single visual source of truth --}}
            @if ($currentRows->isNotEmpty() && ! $allReady)
                <div class="px-5 sm:px-6 py-5 border-t border-gray-100/80 space-y-4">
                    @foreach ($currentRows as $row)
                        @php
                            $code = (string) ($row->status['code'] ?? '');
                            $done = ($row->status['ready'] ?? false) || $code === 'ready';
                            $steps = $row->status['steps'] ?? [];
                            $badgeTone = match (true) {
                                $done => 'emerald',
                                in_array($code, ['pending_profile', 'registration_in_progress', 'kyc_in_progress', 'guarantee_pending', 'pending_reconfirmation'], true) => 'amber',
                                default => 'sky',
                            };
                        @endphp
                        <x-site.invitee-progress
                            :name="$row->name"
                            :badge="$row->status['label'] ?? null"
                            :badge-tone="$badgeTone"
                            :steps="$steps"
                        >
                            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">{{ __('borrower.application.guarantor_section') }}</p>
                            <p class="text-lg sm:text-xl font-extrabold text-gray-900 tracking-tight mt-1 truncate">{{ $row->name }}</p>
                        </x-site.invitee-progress>
                    @endforeach
                </div>
            @endif

            {{-- Previous guarantors — compact history only --}}
            @if ($historyRows->isNotEmpty())
                <div class="px-5 sm:px-6 py-4 border-t border-gray-100/80 space-y-2">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.guarantor_history_title') }}</p>
                    @foreach ($historyRows as $row)
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2.5">
                            <p class="text-sm text-gray-700 truncate">{{ $row->name }}</p>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-gray-500">
                                {{ $row->status['label'] ?? __('borrower.apply.guarantor_status.rejected') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endif
