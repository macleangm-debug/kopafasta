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

        // One progress card per current guarantor identity (never duplicate the same invitee).
        $rank = static function (object $row): int {
            $code = (string) ($row->status['code'] ?? '');

            return match ($code) {
                'ready' => 50,
                'pending_profile', 'kyc_in_progress', 'guarantee_pending', 'pending_reconfirmation' => 40,
                'registration_in_progress', 'accepted' => 30,
                'pending_acceptance' => 20,
                'invitation_sent' => 10,
                default => 0,
            };
        };
        $identityKey = static function (object $row): string {
            $invite = $row->invite ?? null;
            if ($invite?->guarantor_customer_id) {
                return 'c:'.(int) $invite->guarantor_customer_id;
            }
            if ($invite?->customer_guarantor_id) {
                return 'l:'.(int) $invite->customer_guarantor_id;
            }
            $phone = preg_replace('/\D+/', '', (string) ($row->phone ?? $invite?->contact ?? ''));
            if (strlen($phone) >= 9) {
                return 'p:'.substr($phone, -9);
            }
            $name = mb_strtolower(trim((string) ($row->name ?? '')));

            return $name !== '' && $name !== '—' ? 'n:'.$name : 'id:'.spl_object_id($row);
        };
        $currentRows = $currentRows
            ->groupBy(fn ($row) => $identityKey($row))
            ->map(function ($group) use ($rank) {
                return $group->sortByDesc(fn ($row) => [
                    $rank($row),
                    (int) ($row->status['profile_percent'] ?? 0),
                    (int) ($row->invite?->id ?? 0),
                ])->first();
            })
            ->values();

        // During an open replacement (before Finish), Application View keeps showing only the
        // previous current guarantor — never two progress trackers side by side.
        $deferredReplacement = $application && $supplementSvc->deferredReplacementPending($application);
        if ($deferredReplacement) {
            $previousLinkId = (int) ($application->screening_payload['guarantor_deferred_replacement']['previous_link_id'] ?? 0);
            if ($previousLinkId > 0) {
                $onlyPrevious = $currentRows->filter(function ($row) use ($previousLinkId) {
                    $linkId = (int) ($row->invite?->customer_guarantor_id ?? 0);

                    return $linkId === $previousLinkId;
                })->values();
                if ($onlyPrevious->isNotEmpty()) {
                    $currentRows = $onlyPrevious;
                } else {
                    // Finish already replaced previous — keep newest single current only.
                    $currentRows = $currentRows->sortByDesc(fn ($row) => (int) ($row->invite?->id ?? 0))->take(1)->values();
                }
            }
        }

        // Hard rule: one current guarantor progress tracker on Application View.
        if ($currentRows->count() > 1) {
            $currentRows = $currentRows
                ->sortByDesc(fn ($row) => [
                    $rank($row),
                    (int) ($row->invite?->id ?? 0),
                ])
                ->take(1)
                ->values();
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
        $changeConfirmBody = __('borrower.guarantor_supplement.borrower_change_confirm_body');
        $changeCtaLabel = __('borrower.guarantor_supplement.borrower_change_cta');
        if ($changeCtaLabel === 'borrower.guarantor_supplement.borrower_change_cta') {
            $changeCtaLabel = __('borrower.guarantor_supplement.change_cta');
        }
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

        $showInviteActions = $uiState === 'pending'
            && $share
            && empty($share['ready'])
            && ! ($primary?->status['accepted'] ?? false)
            && ! in_array($primaryCode, [
                'pending_profile', 'guarantee_pending', 'registration_in_progress',
                'kyc_in_progress', 'ready', 'accepted', 'account_opened', 'invitation_accepted',
            ], true);
        $showCountdown = $isHeld && $showInviteActions && (! empty($deadline['label']) || isset($deadline['days_left']));
        $showChangeSecondary = in_array($uiState, ['pending', 'accepted_incomplete', 'completed', 'ready_before_submit'], true)
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
                        @elseif ($canChangeWhileHeld && $application)
                            <form method="POST" action="{{ route('site.borrower.application.change-guarantor', $application) }}"
                                  @submit.prevent="window.confirmForm($el, {
                                      title: @js(__('borrower.guarantor_supplement.borrower_change_confirm_title')),
                                      message: @js($changeConfirmBody),
                                      confirmLabel: @js($changeCtaLabel),
                                      confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand'
                                  })">
                                @csrf
                                <button type="submit"
                                        class="inline-flex bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                    {{ $changeCtaLabel }}
                                </button>
                            </form>
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
                            {{-- Name prominent; phone muted underneath — progress is the status source. --}}
                            <p class="text-lg sm:text-xl font-bold text-gray-900 mt-1 truncate">{{ $primary->name }}</p>
                            @if (! empty($primary->phone) && $uiState !== 'ready_before_submit')
                                <p class="text-sm text-gray-500 mt-0.5 tabular-nums">{{ $primary->phone }}</p>
                            @endif
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
                                      message: @js($changeConfirmBody),
                                      confirmLabel: @js($changeCtaLabel),
                                      confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand'
                                  })">
                                @csrf
                                <button type="submit"
                                        class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                    {{ $changeCtaLabel }}
                                </button>
                            </form>
                        @endif
                    @elseif ($showChangeSecondary)
                        @if ($showChangeGuarantor && $editGuarantorUrl)
                            <a href="{{ $editGuarantorUrl }}"
                               class="inline-flex bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                {{ $isAdditionalSupplement
                                    ? __('borrower.guarantor_supplement.cta')
                                    : $changeCtaLabel }}
                            </a>
                        @elseif ($canChangeWhileHeld && $application)
                            <form method="POST" action="{{ route('site.borrower.application.change-guarantor', $application) }}"
                                  @submit.prevent="window.confirmForm($el, {
                                      title: @js(__('borrower.guarantor_supplement.borrower_change_confirm_title')),
                                      message: @js($changeConfirmBody),
                                      confirmLabel: @js($changeCtaLabel),
                                      confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand'
                                  })">
                                @csrf
                                <button type="submit"
                                        class="inline-flex bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                                    {{ $changeCtaLabel }}
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
                @endif
            </div>

            {{-- Current guarantor progress — journey first, then state-aware actions. --}}
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
                        {{-- Journey/status only — name lives in the header above. --}}
                        <x-site.invitee-progress
                            :name="''"
                            :badge="$row->status['label'] ?? null"
                            :badge-tone="$badgeTone"
                            :steps="$steps"
                        />
                    @endforeach

                    @if ($showInviteActions)
                        <div class="flex flex-nowrap items-center gap-2 overflow-x-auto pb-0.5 -mx-0.5 px-0.5 scrollbar-none pt-1">
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
                            @if ($application && $primary?->invite
                                && (string) ($primary->invite->status ?? '') === 'pending'
                                && ($primary->invite->type ?? '') === 'external')
                                <a href="{{ app(\App\Services\GuarantorSupplementService::class)->borrowerEditGuarantorUrl($application) }}"
                                   class="inline-flex shrink-0 items-center gap-1.5 bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-3 sm:px-4 py-2.5 rounded-xl text-xs sm:text-sm whitespace-nowrap">
                                    {{ __('borrower.loan_profile.actions.edit_guarantor') }}
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            @elseif ($showInviteActions)
                <div class="px-5 sm:px-6 py-4 border-t border-gray-100/80">
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
                        @if ($application && $primary?->invite
                            && (string) ($primary->invite->status ?? '') === 'pending'
                            && ($primary->invite->type ?? '') === 'external')
                            <a href="{{ app(\App\Services\GuarantorSupplementService::class)->borrowerEditGuarantorUrl($application) }}"
                               class="inline-flex shrink-0 items-center gap-1.5 bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-3 sm:px-4 py-2.5 rounded-xl text-xs sm:text-sm whitespace-nowrap">
                                {{ __('borrower.loan_profile.actions.edit_guarantor') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Previous guarantors stay in Admin/audit only — not borrower Application View. --}}
        @endif
    </div>
@endif
