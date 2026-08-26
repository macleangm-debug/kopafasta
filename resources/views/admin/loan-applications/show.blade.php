@php
    $customer = $review['customer'] ?? null;
    $product = $review['product'] ?? null;
    $stage = $record->current_stage ?? 'submitted';
    $isManagementStage = in_array($stage, [
        'approval',
        'post_approval_fees',
        'awaiting_disbursement_details',
        'contract_generation',
    ], true);
    $isManagementApprovalStage = $stage === 'awaiting_management';
    $isDisbursementStage = $stage === 'disbursement' || $record->status === 'disbursed';
    $linkedLoan = $record->loan;
    $isServicingFile = $record->hasActiveFacility();
    $isOpsStage = ($isManagementStage || $isDisbursementStage) && ! $isServicingFile;
    $fileIsClosed = $record->isClosed();
    $closedStatus = $fileIsClosed ? $record->closedStatus() : null;
    $isRejectedArchive = $fileIsClosed && $closedStatus === 'rejected';
    $writeOffService = app(\App\Services\WriteOffRequestService::class);
    $writeOffApprovalRequired = (bool) \App\Models\Setting::get('finance.write_off_approval_required');
    $canRecommendWriteOff = $isServicingFile
        && $linkedLoan
        && $writeOffApprovalRequired
        && $writeOffService->canRecommend(auth()->user())
        && $writeOffService->loanEligibleForRecommendation($linkedLoan)
        && ! $writeOffService->hasOpenRequest($linkedLoan);
    $canSeeWriteOffQueue = $isServicingFile && $writeOffService->canSeeWriteOffActions(auth()->user());
    $canDirectWriteOff = $isServicingFile
        && $linkedLoan
        && ! $writeOffApprovalRequired
        && $writeOffService->canFinanceApprove(auth()->user())
        && $writeOffService->loanEligibleForRecommendation($linkedLoan);
@endphp

<x-admin.layout
    :title="$record->application_number"
    heading=""
    :backUrl="route('admin.loan-applications.index')"
    backLabel="Back to applications">

    {{-- Compact credit file letterhead --}}
    <div class="mb-5 -mt-2 rounded-2xl overflow-hidden ring-1 ring-brand/20 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 sm:px-6 py-5 text-white">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="flex items-start gap-4 min-w-0">
                    @php
                        $letterheadAvatar = $customer
                            ? app(\App\Services\FaceVerificationService::class)->avatarUrl($customer)
                            : null;
                    @endphp
                    <div class="shrink-0 size-16 sm:size-20 rounded-2xl overflow-hidden ring-2 ring-white/25 bg-white/10 grid place-items-center">
                        @if ($letterheadAvatar)
                            <img src="{{ $letterheadAvatar }}" alt="{{ $record->partyLabel() }}" class="size-full object-cover">
                        @else
                            <div class="rounded-xl bg-white/10 ring-1 ring-white/20 p-2.5">
                                <x-site.brand-mark size="sm" variant="light" />
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">
                            {{ brand_name() }} · {{ $isServicingFile || $isOpsStage ? 'Credit management' : ($isRejectedArchive ? 'Rejected file' : 'Credit file') }}
                        </p>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $record->application_number }}</h1>
                        <p class="text-sm text-white/75 mt-1 truncate">
                            {{ $record->partyLabel() }}
                            @if ($customer?->member_no)
                                <span class="text-white/50">·</span> Member {{ $customer->member_no }}
                            @endif
                            @if ($product)
                                <span class="text-white/50">·</span> {{ $product->name }}
                            @endif
                            @if ($linkedLoan)
                                <span class="text-white/50">·</span> {{ $linkedLoan->loan_number }}
                            @endif
                        </p>
                        <p class="text-xs text-white/70 mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                            <span>DOB {{ optional($customer?->date_of_birth)->format('d M Y') ?? '—' }}</span>
                            <span>Gender {{ ucfirst($customer?->gender ?? '—') }}</span>
                            @if ($customer?->phone)
                                <span>{{ $customer->phone }}</span>
                            @endif
                            <span>Purpose {{ format_loan_purpose_display($record->purpose, data_get($record->screening_payload, 'purpose_other'), $record->screening_payload) }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if ($fileIsClosed)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                            {{ display_label($closedStatus, 'application_status') }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/80 ring-1 ring-white/15">
                            View only
                        </span>
                    @elseif ($isServicingFile)
                        @if ($linkedLoan)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                                {{ display_label($linkedLoan->status, 'loan_status') }}
                            </span>
                        @endif
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-gold/20 text-brand-gold ring-1 ring-brand-gold/40">
                            Credit management
                        </span>
                        @if ($canRecommendWriteOff)
                            <a href="{{ route('admin.loans.write-off-form', $linkedLoan) }}"
                               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-500 hover:bg-rose-400 text-white">
                                Recommend write-off
                            </a>
                        @endif
                        @if ($canDirectWriteOff)
                            <a href="{{ route('admin.loans.write-off-form', $linkedLoan) }}"
                               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-500 hover:bg-rose-400 text-white">
                                Write off
                            </a>
                        @endif
                        @if ($canSeeWriteOffQueue && $linkedLoan && in_array($linkedLoan->status, ['arrears', 'defaulted', 'written_off'], true))
                            <a href="{{ route('admin.write-off-requests.index') }}"
                               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-white/10 hover:bg-white/20 text-white ring-1 ring-white/20">
                                Write-off queue
                            </a>
                        @endif
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                            {{ display_label($record->status, 'application_status') }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-brand-gold/20 text-brand-gold ring-1 ring-brand-gold/40">
                            {{ $workflow->stageLabel($record->current_stage ?? 'submitted') }}
                        </span>
                        @if ($record->assignedAnalyst)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white ring-1 ring-white/20">
                                Analyst: {{ $record->assignedAnalyst->name }}
                            </span>
                        @endif
                    @endif
                </div>
            </div>
            @if ($record->status === 'pending_documents')
                <p class="mt-3 text-xs font-semibold text-white/85">Awaiting borrower documents</p>
            @elseif ($record->status === 'awaiting_offer' || $record->offer_status === 'pending_borrower')
                <p class="mt-3 text-xs font-semibold text-brand-gold">Awaiting borrower on offer</p>
            @elseif (app(\App\Services\ApplicationOfferService::class)->offerDeclinedByBorrower($record))
                <p class="mt-3 text-xs font-semibold text-rose-200">Offer declined by borrower</p>
            @endif
        </div>
    </div>

    @php
        $capacityAutoReject = app(\App\Services\CapacityAutoRejectService::class);
        $capacityPending = $capacityAutoReject->isPending($record);
        $capacityHours = $capacityPending ? $capacityAutoReject->hoursRemaining($record) : null;
        $capacityState = $capacityPending ? ($capacityAutoReject->state($record) ?? []) : [];
    @endphp
    @if ($capacityPending && ! $fileIsClosed)
        <div class="mb-5 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-widest text-amber-800 font-bold">System sorted</p>
                <p class="text-sm font-semibold text-amber-950 mt-1">
                    @if ($capacityHours === 0)
                        {{ __('borrower.loan_profile.capacity_auto_reject_pending_admin_due') }}
                    @else
                        {{ __('borrower.loan_profile.capacity_auto_reject_pending_admin', ['hours' => $capacityHours ?? '—']) }}
                    @endif
                </p>
                <p class="text-xs text-amber-900/80 mt-1">
                    Ask {{ format_money((float) ($capacityState['requested_amount'] ?? $record->requested_amount ?? 0)) }}
                    · installment {{ format_money((float) ($capacityState['proposed_installment'] ?? 0)) }}
                    · capacity {{ format_money((float) ($capacityState['available_capacity'] ?? 0)) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                @php
                    $canManageCapacity = $capacityAutoReject->canAct(auth()->user());
                @endphp
                @if ($canManageCapacity)
                    <form method="POST" action="{{ route('admin.loan-applications.capacity-auto-reject.fire', $record) }}">
                        @csrf
                        <button type="submit" class="inline-flex text-xs font-bold px-3 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                            Send rejection now
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.loan-applications.capacity-auto-reject.cancel', $record) }}">
                        @csrf
                        <button type="submit" class="inline-flex text-xs font-bold px-3 py-2 rounded-lg bg-white ring-1 ring-amber-300 text-amber-950 hover:bg-amber-100">
                            Keep in screening
                        </button>
                    </form>
                @else
                    <p class="text-xs font-semibold text-amber-900/80 self-center">View only — credit committee confirms Send now / Keep in screening.</p>
                @endif
            </div>
        </div>
    @endif

    @if ($fileIsClosed)
        @include('admin.loan-applications.review._closed_file_workspace')
    @elseif ($isServicingFile)
        @include('admin.loan-applications.review._management_workspace')
    @elseif ($isOpsStage)
        @include('admin.loan-applications.review._ops_workspace')
    @elseif ($isManagementApprovalStage)
        @include('admin.loan-applications.review._management_approval_workspace')
    @else
        @include('admin.loan-applications.review._credit_workspace')
    @endif

</x-admin.layout>
