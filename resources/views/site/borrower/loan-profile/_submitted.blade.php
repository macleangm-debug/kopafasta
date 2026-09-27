@php
    $requirements = $profile['product_requirements'] ?? collect();
    $uploads = $profile['requirement_uploads'] ?? collect();
    $documentRequests = $profile['document_requests'] ?? collect();
    $guarantorInvitations = $profile['guarantor_invitations'] ?? collect();
    $offer = $profile['offer'] ?? null;
    $loan = $profile['loan'] ?? null;
    $nextDue = $profile['next_due'] ?? null;
    $requiredCount = $requirements->where('is_required', true)->count();
    $satisfiedCount = $requirements->where('is_required', true)
        ->filter(fn ($r) => ($uploads[$r->id] ?? collect())->contains(fn ($u) => in_array($u->status, ['verified', 'approved', 'pending_review', 'pending'], true)))
        ->count();
    $docProgress = $requiredCount > 0 ? round(($satisfiedCount / $requiredCount) * 100) : 100;
@endphp

@php
    $intake = app(\App\Services\ApplicationIntakeReadinessService::class)->resolve($application);
    $intakeState = $intake['state'] ?? '';
@endphp
@if (in_array($intakeState, ['submitted_initial_check', 'initial_decision_hold', 'awaiting_guarantor', 'guarantor_required_not_added', 'ready_for_screening'], true))
    <div class="mb-6 rounded-2xl ring-1 ring-brand/15 bg-white p-5 space-y-2">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.intake.part_submitted') }}</p>
        <h2 class="text-lg font-bold text-gray-900">
            @if (in_array($intakeState, ['submitted_initial_check', 'initial_decision_hold'], true))
                {{ __('borrower.intake.received_title') }}
            @elseif ($intakeState === 'awaiting_guarantor')
                {{ __('borrower.intake.passed_title') }}
            @elseif ($intakeState === 'ready_for_screening')
                {{ __('borrower.intake.ready_title') }}
            @else
                {{ __('borrower.intake.received_title') }}
            @endif
        </h2>
        <p class="text-sm text-gray-600">
            @if (in_array($intakeState, ['submitted_initial_check', 'initial_decision_hold'], true))
                {{ __('borrower.intake.received_pending_body') }}
            @elseif ($intakeState === 'awaiting_guarantor')
                {{ __('borrower.intake.passed_guarantor_body') }}
            @elseif ($intakeState === 'ready_for_screening')
                {{ __('borrower.intake.ready_body') }}
            @else
                {{ __('borrower.intake.add_guarantor_body') }}
            @endif
        </p>
        @if (($intake['guarantor']['nominated'] ?? false) && $intakeState === 'awaiting_guarantor')
            <dl class="mt-3 grid sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.guarantor') }}</dt>
                    <dd class="font-semibold">{{ $intake['guarantor']['name'] ?? '—' }} {{ $intake['guarantor']['phone'] ? '· '.$intake['guarantor']['phone'] : '' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('admin.intake.next_action') }}</dt>
                    <dd class="font-semibold">{{ $intake['guarantor']['progress'] ?? '—' }}</dd>
                </div>
            </dl>
        @endif
    </div>
@endif

@if ($loan && in_array((string) $loan->status, ['active', 'disbursed', 'arrears'], true))
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-6">
        <h2 class="font-semibold text-emerald-900 mb-2">{{ __('borrower.loan_profile.disbursed_title') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_profile.loan_number') }}</p>
                <p class="font-mono font-semibold text-emerald-900">{{ $loan->loan_number }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_profile.outstanding') }}</p>
                @php
                    $owed = app(\App\Services\ActiveLoanServicingService::class)->forLoan($loan);
                    $bd = $owed['balance_breakdown'] ?? [];
                    $rec = $owed['recovery_charges']['total'] ?? ($bd['recovery_costs'] ?? 0);
                @endphp
                <p class="font-semibold text-emerald-900">{{ format_money($bd['total_outstanding'] ?? $loan->outstanding_balance ?? $loan->principal_amount) }}</p>
                @if (((float) ($bd['penalty_outstanding'] ?? 0)) > 0 || (float) $rec > 0)
                    <p class="text-[11px] text-emerald-800/80 mt-1">
                        @if (((float) ($bd['penalty_outstanding'] ?? 0)) > 0)
                            {{ __('borrower.loan_servicing.penalty_outstanding') }}: {{ format_money($bd['penalty_outstanding']) }}
                        @endif
                        @if (((float) ($bd['penalty_outstanding'] ?? 0)) > 0 && (float) $rec > 0) · @endif
                        @if ((float) $rec > 0)
                            {{ __('borrower.loan_servicing.recovery_total') }}: {{ format_money($rec) }}
                        @endif
                    </p>
                @endif
            </div>
            @php $repayment = $profile['repayment_summary'] ?? null; @endphp
            @if (! empty($repayment['disbursed_at']))
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_progress.disbursed_on') }}</p>
                    <p class="font-semibold text-emerald-900">{{ optional($repayment['disbursed_at'])->format('d M Y') }}</p>
                </div>
            @endif
            @if (! empty($repayment['first_repayment_at']))
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_progress.first_repayment') }}</p>
                    <p class="font-semibold text-emerald-900">{{ optional($repayment['first_repayment_at'])->format('d M Y') }}</p>
                </div>
            @endif
            @if (! empty($repayment['frequency']))
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loan_progress.repayment_frequency') }}</p>
                    <p class="font-semibold text-emerald-900">{{ __('borrower.loan_progress.frequencies.'.$repayment['frequency']) }}</p>
                </div>
            @endif
            @if ($nextDue)
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700">{{ __('borrower.loans_page.next_payment') }}</p>
                    <p class="font-semibold text-emerald-900">{{ optional($nextDue->due_date)->format('d M Y') }} · {{ format_money($nextDue->total_due) }}</p>
                </div>
            @endif
        </div>
        @if ($loan)
            <div class="mt-4">
                <a href="{{ route('site.borrower.loans.show', $loan->id) }}"
                   class="inline-flex items-center text-sm font-semibold text-emerald-800 hover:text-emerald-900">
                    {{ __('borrower.loan_profile.actions.view_active_loan') }} &rarr;
                </a>
            </div>
        @endif
    </div>
@endif

@if ($offer)
    @php
        $offerDeclined = ($application->offer_status ?? '') === 'declined' || $offer->isCancelled();
        $offerSigned = $offer->isSigned();
    @endphp
    <div @class([
        'mb-6 rounded-2xl p-4 flex items-center justify-between gap-3 flex-wrap border',
        'bg-amber-50 border-amber-200' => ! $offerDeclined,
        'bg-gray-50 border-gray-200' => $offerDeclined,
    ])>
        <div>
            <p @class([
                'text-sm font-semibold',
                'text-amber-900' => ! $offerDeclined,
                'text-gray-700' => $offerDeclined,
            ])>
                @if ($offerDeclined)
                    {{ __('borrower.applications_list.statuses.offer_declined') }}
                @elseif ($offerSigned)
                    {{ __('borrower.application.offer_signed') }}
                @else
                    {{ __('borrower.application.offer_ready') }}
                @endif
            </p>
            <p class="text-xs text-gray-600 mt-0.5">Reference: <span class="font-mono">{{ $offer->reference }}</span></p>
        </div>
        @unless ($offerDeclined)
            <a href="{{ route('site.borrower.application.agreement', $application) }}"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">
                {{ __('borrower.application.view_offer') }} →
            </a>
        @endunless
    </div>
@endif

@if ($showSchedule ?? true)
    @include('site.borrower.loan-profile._schedule_preview', ['profile' => $profile])
@endif

{{-- Product document checklist: only while post-approval / draft gaps — hide after plain submit --}}
@if (($isPostApproval ?? false) && $requirements->isNotEmpty())
    <details class="glass-card overflow-hidden mb-6 group">
        <summary class="cursor-pointer list-none px-5 py-4 flex items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
            <div>
                <h2 class="font-semibold">{{ __('borrower.application.required_documents') }}</h2>
                <p class="text-xs text-gray-500">{{ __('borrower.application.required_progress', ['satisfied' => $satisfiedCount, 'total' => $requiredCount]) }}</p>
            </div>
            <svg class="size-4 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
        </summary>
        <ul class="divide-y divide-gray-100 border-t border-gray-100">
            @foreach ($requirements as $req)
                @php
                    $myUploads = $uploads[$req->id] ?? collect();
                    $latest = $myUploads->first();
                    $isApproved = $latest && in_array($latest->status, ['verified','approved']);
                    $isRejected = $latest && $latest->status === 'rejected';
                    $badge = match (true) {
                        $isApproved => 'bg-emerald-100 text-emerald-700',
                        $isRejected => 'bg-red-100 text-red-700',
                        $latest      => 'bg-amber-100 text-amber-700',
                        ! $req->is_required => 'bg-gray-100 text-gray-500',
                        default => 'bg-gray-100 text-gray-600',
                    };
                    $label = $latest
                        ? (__('borrower.application.upload_statuses.'.$latest->status) !== 'borrower.application.upload_statuses.'.$latest->status
                            ? __('borrower.application.upload_statuses.'.$latest->status)
                            : display_label($latest->status, 'record_status'))
                        : ($req->is_required ? __('borrower.application.status_required') : __('borrower.application.status_optional'));
                @endphp
                <li id="requirement-{{ $req->id }}" class="p-5">
                    <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $req->name }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $label }}</span>
                    </div>
                    @if ($latest && $latest->file_path)
                        <div class="mb-3">
                            <x-site.document-thumb :url="asset('storage/'.$latest->file_path)" />
                        </div>
                    @endif
                    @if (! $isApproved)
                        <x-site.document-upload
                            :action="route('site.borrower.application.documents.store', $application->id)"
                            :multiple="false"
                        >
                            <input type="hidden" name="loan_product_requirement_id" value="{{ $req->id }}">
                        </x-site.document-upload>
                    @endif
                </li>
            @endforeach
        </ul>
    </details>
@endif
