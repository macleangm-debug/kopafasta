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
        $inviteSvc = app(\App\Services\GuarantorInvitationService::class);

        $rows = collect();
        foreach ($guarantorInvitations as $invite) {
            $status = $inviteSvc->borrowerInvitationStatus($invite);
            $rows->push((object) [
                'name'   => $invite->invitee_name ?? $invite->contact ?? '—',
                'type'   => $invite->type === 'internal'
                    ? __('borrower.application.guarantor_member')
                    : __('borrower.application.guarantor_external'),
                'status' => $status,
                'share'  => $inviteSvc->sharePayload($invite, $application?->customer),
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
                    'accepted' => ($status['code'] ?? '') === 'ready' || ($status['code'] ?? '') === 'pending_profile',
                    'ready' => ($status['code'] ?? '') === 'ready',
                    'steps' => [],
                ]),
                'share'  => null,
            ]);
        }

        $readyCount = $rows->filter(fn ($row) => ($row->status['ready'] ?? false) || ($row->status['code'] ?? '') === 'ready')->count();
        $total = max(1, $rows->count());
        $allReady = $rows->isNotEmpty() && $readyCount >= $rows->count();
        $primary = $rows->first();
        $share = $primary?->share;
        $showChangeGuarantor = $guarantorSupplementOpen && $editGuarantorUrl;
        $isHeld = ($application->status ?? '') === 'awaiting_guarantor'
            || ($application->current_stage ?? '') === 'awaiting_guarantor';
        $pending = ! $allReady;
        $deadline = $application
            ? app(\App\Services\GuarantorDeadlineService::class)->progress($application)
            : null;
        $toneHeld = $isHeld || $pending;
    @endphp

    <div id="guarantor-progress" class="mb-6 glass-card overflow-hidden ring-1 {{ $toneHeld ? 'ring-amber-200/80' : 'ring-emerald-200/80' }}"
         x-data="{ copied: false }">
        <div class="bg-gradient-to-br {{ $toneHeld ? 'from-amber-50 to-white' : 'from-emerald-50 to-white' }} px-5 py-4 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-widest {{ $toneHeld ? 'text-amber-800' : 'text-emerald-800' }} font-semibold">{{ __('borrower.application.guarantor_section') }}</p>
                    <p class="text-sm font-semibold {{ $toneHeld ? 'text-amber-950' : 'text-emerald-950' }} mt-1">
                        {{ __('borrower.loan_profile.guarantor_ready_progress', [
                            'ready' => $readyCount,
                            'total' => $total,
                        ]) }}
                    </p>
                    @if ($isHeld || $pending)
                        <p class="text-xs font-semibold text-amber-900 mt-2">{{ __('borrower.loan_profile.guarantor_hold_banner') }}</p>
                        <p class="text-xs text-amber-800 mt-1">{{ __('borrower.loan_profile.guarantor_hold_body') }}</p>
                    @else
                        <p class="text-xs font-semibold text-emerald-900 mt-2">{{ __('borrower.loan_profile.guarantor_ready_banner') }}</p>
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

            @if (($isHeld || $pending) && $share && empty($share['ready']))
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

            @if ($rows->isNotEmpty())
                <ul class="divide-y divide-amber-100/80 rounded-2xl bg-white/70 ring-1 ring-amber-100 overflow-hidden">
                    @foreach ($rows as $row)
                        @php
                            $code = $row->status['code'] ?? '';
                            $done = ($row->status['ready'] ?? false) || $code === 'ready';
                            $steps = $row->status['steps'] ?? [];
                        @endphp
                        <li class="px-4 py-3 space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $row->name }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $row->type }} · {{ $row->status['label'] ?? '—' }}</p>
                                    @if (($row->status['code'] ?? '') === 'pending_profile' && ($row->status['profile_percent'] ?? null) !== null)
                                        <p class="text-xs font-semibold text-amber-800 mt-1">
                                            {{ __('borrower.apply.guarantor_progress.profile_pct', ['percent' => $row->status['profile_percent']]) }}
                                        </p>
                                    @endif
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
                            </div>
                            @if (! empty($steps))
                                <ol class="grid sm:grid-cols-4 gap-2">
                                    @foreach ($steps as $step)
                                        <li @class([
                                            'rounded-xl ring-1 px-3 py-2',
                                            'bg-emerald-50 ring-emerald-200' => $step['complete'] ?? false,
                                            'bg-amber-50 ring-amber-300' => ! ($step['complete'] ?? false) && ($step['current'] ?? false),
                                            'bg-white ring-gray-200' => ! ($step['complete'] ?? false) && ! ($step['current'] ?? false),
                                        ])>
                                            <p class="text-[10px] font-semibold {{ ($step['complete'] ?? false) ? 'text-emerald-700' : (($step['current'] ?? false) ? 'text-amber-800' : 'text-gray-400') }}">
                                                {{ ($step['complete'] ?? false) ? '✓' : (($step['current'] ?? false) ? '·' : '○') }}
                                            </p>
                                            <p class="text-xs font-semibold mt-0.5 text-gray-700">{{ $step['label'] }}</p>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
