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
                        <div class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <h2 class="text-lg font-bold text-gray-900">
                                @if ($uiState === 'needs_replacement')
                                    {{ __('borrower.loan_profile.guarantor_declined_title') }}
                                @elseif ($uiState === 'required_empty')
                                    {{ __('borrower.loan_profile.guarantor_not_added') }}
                                @elseif ($uiState === 'quote_reconfirm')
                                    {{ __('borrower.loan_profile.guarantor_reconfirm_title') }}
                                @elseif ($uiState === 'accepted_incomplete')
                                    {{ __('borrower.loan_profile.guarantor_completing_title') }}
                                @elseif ($uiState === 'ready_before_submit')
                                    {{ $primary->name }}
                                @elseif ($uiState === 'pending')
                                    {{ __('borrower.loan_profile.guarantor_pending_acceptance_title') }}
                                @else
                                    {{ __('borrower.loan_profile.guarantor_waiting_title') }}
                                @endif
                            </h2>
                        </div>
                        @if ($primary && in_array($uiState, ['pending', 'accepted_incomplete', 'quote_reconfirm'], true))
                            <p class="text-base font-bold text-gray-900 mt-2">
                                {{ $primary->name }}
                                @if (! empty($primary->phone))
                                    <span class="font-semibold text-gray-600">· {{ $primary->phone }}</span>
                                @endif
                            </p>
                        @endif
                        <p class="text-sm text-gray-600 mt-1">
                            @if ($uiState === 'needs_replacement')
                                {{ __('borrower.loan_profile.guarantor_required_body') }}
                            @elseif ($uiState === 'required_empty')
                                {{ __('borrower.loan_profile.guarantor_not_added_hint') }}
                            @elseif ($uiState === 'quote_reconfirm')
                                {{ __('borrower.loan_profile.guarantor_reconfirm_body') }}
                            @elseif ($uiState === 'accepted_incomplete')
                                {{ __('borrower.loan_profile.guarantor_completing_body') }}
                            @elseif ($uiState === 'ready_before_submit')
                                {{ $primary->type }}
                            @elseif ($uiState === 'pending')
                                {{ __('borrower.loan_profile.guarantor_pending_acceptance_body') }}
                            @else
                                {{ __('borrower.loan_profile.guarantor_hold_body') }}
                            @endif
                        </p>
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
                    <div class="flex flex-wrap gap-2">
                        @if (! empty($share['whatsapp_url']))
                            <a href="{{ $share['whatsapp_url'] }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                {{ __('borrower.loan_profile.guarantor_nudge_whatsapp') }}
                            </a>
                        @endif
                        @if (! empty($share['invitation_url']) || ! empty($share['short_url']))
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js($share['short_url'] ?? $share['invitation_url'])); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="inline-flex items-center gap-2 bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                                <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.loan_profile.guarantor_nudge_copy'))"></span>
                            </button>
                        @endif
                        @if ($application && $primary?->invite && in_array((string) ($primary->invite->status ?? ''), ['pending', 'accepted'], true)
                            && ($primary->invite->type ?? '') === 'external')
                            <div class="w-full" x-data="{ editOpen: false }">
                                <button type="button" @click="editOpen = true"
                                        class="inline-flex items-center gap-2 bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm">
                                    {{ __('borrower.loan_profile.actions.edit_guarantor') }}
                                </button>
                                <x-site.action-panel :title="__('borrower.loan_profile.actions.edit_guarantor')" open="editOpen">
                                    <p class="text-xs text-gray-500 mb-3">{{ __('borrower.loan_profile.actions.edit_guarantor_hint') }}</p>
                                    <form method="POST" action="{{ route('site.borrower.application.edit-guarantor', $application) }}" class="space-y-3"
                                          @submit.prevent="window.confirmForm($el, {
                                              title: @js(__('borrower.loan_profile.actions.edit_guarantor')),
                                              message: @js(__('borrower.loan_profile.actions.edit_guarantor_confirm')),
                                              confirmLabel: @js(__('borrower.loan_profile.actions.edit_guarantor_save')),
                                              confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand'
                                          })">
                                        @csrf
                                        <input type="hidden" name="invitation_id" value="{{ $primary->invite->id }}">
                                        @php
                                            $nameParts = preg_split('/\s+/', trim((string) ($primary->invite->invitee_name ?? '')), 2) ?: ['', ''];
                                        @endphp
                                        <label class="block text-xs font-semibold text-gray-700">{{ __('borrower.apply.guarantor_fields.first_name') }}
                                            <input name="first_name" required maxlength="60" value="{{ old('first_name', $nameParts[0] ?? '') }}"
                                                   class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                                        </label>
                                        <label class="block text-xs font-semibold text-gray-700">{{ __('borrower.apply.guarantor_fields.last_name') }}
                                            <input name="last_name" required maxlength="60" value="{{ old('last_name', $nameParts[1] ?? '') }}"
                                                   class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                                        </label>
                                        <label class="block text-xs font-semibold text-gray-700">{{ __('borrower.apply.guarantor_fields.phone') }}
                                            <input name="phone" required maxlength="20" value="{{ old('phone', $primary->invite->contact) }}"
                                                   class="mt-1 w-full rounded-xl border-gray-200 text-sm" inputmode="tel">
                                        </label>
                                        <button type="submit" class="w-full inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                                            {{ __('borrower.loan_profile.actions.edit_guarantor_save') }}
                                        </button>
                                    </form>
                                </x-site.action-panel>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Current guarantor progress (Profile-style step ticks) --}}
            @if ($currentRows->isNotEmpty() && ! $allReady)
                <div class="px-5 sm:px-6 py-5 border-t border-gray-100/80 space-y-4">
                    @foreach ($currentRows as $row)
                        @php
                            $code = $row->status['code'] ?? '';
                            $done = ($row->status['ready'] ?? false) || $code === 'ready';
                            $steps = $row->status['steps'] ?? [];
                            $percent = $row->status['profile_percent'] ?? null;
                        @endphp
                        <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
                            <div class="h-1 w-full bg-gradient-to-r from-brand via-brand to-brand-gold/80" aria-hidden="true"></div>
                            <div class="px-4 py-4 space-y-3">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 truncate">{{ $row->name }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $row->type }}</p>
                                    </div>
                                    <span @class([
                                        'shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1',
                                        'bg-emerald-50 text-emerald-800 ring-emerald-200' => $done,
                                        'bg-amber-50 text-amber-900 ring-amber-200' => ! $done && $code === 'pending_profile',
                                        'bg-sky-50 text-sky-800 ring-sky-200' => ! $done && $code !== 'pending_profile',
                                    ])>
                                        @if ($done)
                                            {{ __('borrower.apply.guarantor_status.ready') }}
                                        @elseif ($code === 'pending_profile' && $percent !== null)
                                            {{ __('borrower.apply.guarantor_progress.profile_pct', ['percent' => $percent]) }}
                                        @else
                                            {{ $row->status['label'] ?? '—' }}
                                        @endif
                                    </span>
                                </div>
                                @if (! empty($steps))
                                    <ol class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        @foreach ($steps as $step)
                                            <li @class([
                                                'rounded-xl px-3 py-2.5 ring-1',
                                                'bg-emerald-50 ring-emerald-200' => $step['complete'] ?? false,
                                                'bg-brand-muted/60 ring-brand/30' => ! ($step['complete'] ?? false) && ($step['current'] ?? false),
                                                'bg-white ring-gray-200' => ! ($step['complete'] ?? false) && ! ($step['current'] ?? false),
                                            ])>
                                                <p @class([
                                                    'text-[10px] uppercase tracking-widest font-bold',
                                                    'text-emerald-700' => $step['complete'] ?? false,
                                                    'text-brand' => ! ($step['complete'] ?? false) && ($step['current'] ?? false),
                                                    'text-gray-400' => ! ($step['complete'] ?? false) && ! ($step['current'] ?? false),
                                                ])>
                                                    {{ ($step['complete'] ?? false) ? '✓' : (($step['current'] ?? false) ? '●' : '○') }}
                                                </p>
                                                <p @class([
                                                    'text-xs font-semibold mt-0.5 leading-snug',
                                                    'text-gray-900' => ($step['current'] ?? false) || ($step['complete'] ?? false),
                                                    'text-gray-500' => ! ($step['current'] ?? false) && ! ($step['complete'] ?? false),
                                                ])>{{ $step['label'] ?? '' }}</p>
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        </div>
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
