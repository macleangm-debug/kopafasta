@php
    $customer = $review['customer'];
    $product = $review['product'];
    $stage = $record->current_stage ?? 'submitted';
    $isScreeningStage = in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true);
    $isCommitteeStage = $stage === 'pre_approval';
    $isManagementStage = in_array($stage, [
        'approval',
        'post_approval_fees',
        'awaiting_disbursement_details',
        'contract_generation',
    ], true);
    $isDisbursementStage = $stage === 'disbursement' || $record->status === 'disbursed';
    $isOpsStage = $isManagementStage || $isDisbursementStage;
    $isCreditWorkspace = $isScreeningStage || $isCommitteeStage;
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
                    <div class="shrink-0 rounded-xl bg-white/10 ring-1 ring-white/20 p-2.5">
                        <x-site.brand-mark size="sm" variant="light" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ brand_name() }} · Credit file</p>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 truncate">{{ $record->application_number }}</h1>
                        <p class="text-sm text-white/75 mt-1 truncate">
                            {{ $customer->full_name }}
                            @if ($customer->member_no)
                                <span class="text-white/50">·</span> Member {{ $customer->member_no }}
                            @endif
                            @if ($product)
                                <span class="text-white/50">·</span> {{ $product->name }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
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
                    @if (! in_array(auth()->user()?->role, ['credit_analyst'], true) && auth()->user()?->hasPermission('applications.edit'))
                    <a href="{{ route('admin.loan-applications.edit', $record) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">
                        Edit application
                    </a>
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

    @if ($isCreditWorkspace)
        @include('admin.loan-applications.review._credit_workspace')
    @elseif ($isOpsStage)
        @include('admin.loan-applications.review._header')
        @include('admin.loan-applications.review._ops')

        <div x-data="{ tab: 'borrower' }" class="mt-6 space-y-4">
            <nav class="grid grid-cols-2 sm:grid-cols-3 gap-2" aria-label="Supporting sections">
                @foreach ([['borrower', 'Borrower'], ['documents', 'Documents'], ['guarantor', 'Guarantor']] as [$key, $label])
                    <button type="button"
                            @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'bg-brand text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-brand-muted/40'"
                            class="rounded-xl px-3 py-2.5 text-xs font-semibold transition text-left">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
            <div x-show="tab === 'borrower'" x-cloak class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
                @include('admin.loan-applications.review._borrower')
            </div>
            <div x-show="tab === 'documents'" x-cloak class="space-y-5">
                @include('admin.loan-applications.review._documents')
                @include('admin.loan-applications.review._document-requests')
                @include('admin.loan-applications._asset-backed')
                @include('admin.loan-applications._asset-lending')
            </div>
            <div x-show="tab === 'guarantor'" x-cloak>
                @include('admin.loan-applications.review._guarantors')
            </div>
        </div>
    @else
        @include('admin.loan-applications.review._header')
        @include('admin.loan-applications.review._recommendation')
        @include('admin.loan-applications.review._borrower')
    @endif

</x-admin.layout>
