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
        $editGuarantorUrl = $profile['edit_guarantor_url'] ?? null;
        $guarantorSupplementOpen = $application
            ? app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($application)
            : false;
        if (! $isDraft && $application && ! $editGuarantorUrl && $guarantorSupplementOpen) {
            $editGuarantorUrl = app(\App\Services\GuarantorSupplementService::class)->borrowerWizardUrl($application);
        }
        if (! $isDraft && ! $guarantorSupplementOpen) {
            $editGuarantorUrl = $guarantorSupplementOpen ? $editGuarantorUrl : null;
        }

        $inviteSvc = app(\App\Services\GuarantorInvitationService::class);
        $customer = $application?->customer ?? ($profile['draft']?->customer ?? null);

        $rows = collect();
        foreach ($guarantorInvitations as $invite) {
            $status = $inviteSvc->borrowerInvitationStatus($invite);
            $rows->push((object) [
                'name'   => $invite->invitee_name ?? $invite->contact ?? '—',
                'type'   => $invite->type === 'internal'
                    ? __('borrower.application.guarantor_member')
                    : __('borrower.application.guarantor_external'),
                'status' => $status,
                'share'  => $inviteSvc->sharePayload($invite, $customer),
            ]);
        }
        foreach ($guarantorLinks as $link) {
            if ($guarantorInvitations->contains('customer_guarantor_id', $link->id)) {
                continue;
            }
            $status = $inviteSvc->workflowStatus($link);
            $rows->push((object) [
                'name'   => $link->displayName(),
                'type'   => __('borrower.application.guarantor_internal'),
                'status' => array_merge($status, [
                    'profile_percent' => null,
                    'accepted' => in_array($status['code'] ?? '', ['ready', 'pending_profile'], true),
                    'ready' => ($status['code'] ?? '') === 'ready',
                    'steps' => [],
                ]),
                'share'  => null,
            ]);
        }

        $readyCount = $rows->filter(fn ($row) => ($row->status['ready'] ?? false) || ($row->status['code'] ?? '') === 'ready')->count();
        $allReady = $rows->isNotEmpty() && $readyCount >= $rows->count();
        $primary = $rows->first();
        $share = $primary?->share;
        $showChangeGuarantor = ($isDraft && $editGuarantorUrl) || ($guarantorSupplementOpen && $editGuarantorUrl);
        $canChangeWhileHeld = ! $isDraft && ! $showChangeGuarantor && (bool) ($profile['can_change_guarantor_while_held'] ?? false);
        $isHeld = $application && (
            ($application->status ?? '') === 'awaiting_guarantor'
            || ($application->current_stage ?? '') === 'awaiting_guarantor'
        );
        $pending = $rows->isEmpty() || ! $allReady;
        $deadline = $application
            ? app(\App\Services\GuarantorDeadlineService::class)->progress($application)
            : null;
        // Hold / nudge copy only while UW is blocked on the guarantor (awaiting_guarantor),
        // or while the draft still needs a ready guarantor. Once submitted + ready, under review.
        $showWaitingCopy = $isHeld || ($pending && $isDraft);
        $showReadyBeforeSubmit = $allReady && $isDraft && ! $isHeld;
    @endphp

    <div id="guarantor-progress" class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15"
         x-data="{ copied: false }">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.application.guarantor_section') }}</p>
                    <div class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <h2 class="text-lg font-bold text-gray-900">
                            @if ($rows->isEmpty())
                                {{ __('borrower.loan_profile.guarantor_not_added') }}
                            @elseif ($allReady)
                                {{ __('borrower.loan_profile.guarantor_ready_title_short') }}
                            @else
                                {{ __('borrower.loan_profile.guarantor_waiting_title') }}
                            @endif
                        </h2>
                        @if ($primary)
                            <span class="text-sm font-semibold text-gray-500 truncate max-w-full">· {{ $primary->name }}</span>
                        @endif
                    </div>
                    @if ($rows->isEmpty())
                        <p class="text-sm text-gray-600 mt-1">{{ __('borrower.loan_profile.guarantor_not_added_hint') }}</p>
                    @elseif ($showWaitingCopy)
                        <p class="text-sm text-gray-600 mt-1">{{ __('borrower.loan_profile.guarantor_hold_body') }}</p>
                    @elseif ($showReadyBeforeSubmit)
                        <p class="text-sm text-emerald-800 mt-1 font-semibold">{{ __('borrower.loan_profile.guarantor_ready_banner') }}</p>
                    @endif
                </div>
                @if ($showChangeGuarantor)
                    <a href="{{ $editGuarantorUrl }}"
                       class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                        {{ $guarantorSupplementOpen
                            ? __('borrower.guarantor_supplement.cta')
                            : __('borrower.loan_profile.actions.edit_guarantor') }}
                    </a>
                @elseif ($canChangeWhileHeld && $application)
                    <form method="POST" action="{{ route('site.borrower.application.change-guarantor', $application) }}"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js(__('borrower.guarantor_supplement.borrower_change_confirm_title')),
                              message: @js(__('borrower.guarantor_supplement.borrower_change_confirm_body')),
                              confirmLabel: @js(__('borrower.loan_profile.actions.edit_guarantor')),
                              confirmClass: 'bg-brand-gold hover:bg-yellow-400 text-brand'
                          })">
                        @csrf
                        <button type="submit"
                                class="inline-flex bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-bold px-4 py-2.5 rounded-xl text-sm shrink-0 shadow-sm">
                            {{ __('borrower.loan_profile.actions.edit_guarantor') }}
                        </button>
                    </form>
                @endif
            </div>

            @if ($isHeld && (! empty($deadline['label']) || isset($deadline['days_left'])))
                <x-site.deadline-badge
                    :label="$deadline['label'] ?? null"
                    :days-left="$deadline['days_left'] ?? null"
                    :date="$deadline['date'] ?? null"
                    :purpose="__('borrower.loan_profile.deadline_purpose_guarantor_profile')"
                    :urgent="($deadline['days_left'] ?? 99) <= 2"
                    :expired="(bool) ($deadline['expired'] ?? false)"
                />
            @endif

            @if ($guarantorSupplementOpen)
                <p class="text-xs text-amber-800">{{ __('borrower.guarantor_supplement.borrower_banner') }}</p>
            @elseif ($canChangeWhileHeld)
                <p class="text-xs text-gray-500">{{ __('borrower.guarantor_supplement.borrower_change_hint') }}</p>
            @endif

            @if ($pending && $share && empty($share['ready']))
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
                </div>
            @endif
        </div>

        @if ($rows->isNotEmpty())
            <div class="px-5 sm:px-6 py-5 border-t border-gray-100/80 space-y-4">
                @foreach ($rows as $row)
                    @php
                        $code = $row->status['code'] ?? '';
                        $done = ($row->status['ready'] ?? false) || $code === 'ready';
                        $steps = $row->status['steps'] ?? [];
                        $percent = $row->status['profile_percent'] ?? null;
                    @endphp
                    <div class="rounded-2xl bg-gradient-to-br from-white to-brand-muted/20 ring-1 ring-brand/10 px-4 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 truncate">{{ $row->name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $row->type }} · {{ $row->status['label'] ?? '—' }}</p>
                            </div>
                            <span @class([
                                'shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1',
                                'bg-emerald-100 text-emerald-800 ring-emerald-200' => $done,
                                'bg-amber-100 text-amber-900 ring-amber-200' => ! $done && $code === 'pending_profile',
                                'bg-sky-100 text-sky-800 ring-sky-200' => ! $done && $code !== 'pending_profile',
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

                        {{-- Steps only while the guarantor is still finishing; ready = details card only. --}}
                        @if (! $done && ! empty($steps))
                            <ol class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                @foreach ($steps as $step)
                                    <li @class([
                                        'rounded-xl px-3 py-2.5 ring-1',
                                        'bg-brand text-white ring-brand' => $step['complete'] ?? false,
                                        'bg-brand-gold/30 text-brand ring-brand-gold/50' => ! ($step['complete'] ?? false) && ($step['current'] ?? false),
                                        'bg-white text-gray-500 ring-gray-200' => ! ($step['complete'] ?? false) && ! ($step['current'] ?? false),
                                    ])>
                                        <p class="text-[10px] font-bold uppercase tracking-wider opacity-80">
                                            {{ ($step['complete'] ?? false) ? '✓' : (($step['current'] ?? false) ? '·' : '○') }}
                                        </p>
                                        <p class="text-xs font-semibold mt-0.5 leading-snug">{{ $step['label'] }}</p>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif ($isDraft && $editGuarantorUrl)
            <div class="px-5 sm:px-6 py-4 border-t border-gray-100">
                <a href="{{ $editGuarantorUrl }}"
                   class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.loan_profile.actions.complete_guarantor') }}
                </a>
            </div>
        @endif
    </div>
@endif
