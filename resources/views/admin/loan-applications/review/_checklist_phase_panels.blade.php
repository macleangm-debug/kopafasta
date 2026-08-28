{{-- Review surfaces that used to live under Profiles — kept on the checklist to avoid duplication.
     Expects: $phase (person|capacity|security), optional $section (documents|affordability|crb|group|wrapup). --}}
@php
    $panelPerson = $deskPerson ?? 'borrower';
    $panelG = $deskG ?? null;
    $panelM = $deskM ?? null;
    $panelSubjectReview = $review;
    $panelGuarantor = null;
    $panelMember = null;
    $isGroupFile = collect($groupReview['members'] ?? [])->isNotEmpty();
    $section = $section ?? null;

    if ($panelPerson === 'guarantor' && $panelG) {
        $panelGuarantor = collect($review['guarantors'] ?? [])->first(fn ($row) => (int) ($row['link_id'] ?? 0) === (int) $panelG);
        if ($panelGuarantor && ! empty($panelGuarantor['file'])) {
            $panelSubjectReview = array_merge($review, $panelGuarantor['file']);
            $panelSubjectReview['is_guarantor_subject'] = true;
            $panelSubjectReview['guarantor_row'] = $panelGuarantor;
        }
    }

    if ($panelPerson === 'member' && $panelM) {
        $panelMember = collect($groupReview['members'] ?? [])->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $panelM);
        if ($panelMember) {
            if (! empty($panelMember['file'])) {
                $panelSubjectReview = array_merge($review, $panelMember['file']);
            } elseif (! empty($panelMember['customer_id'])) {
                $memberCustomer = \App\Models\Customer::query()->find($panelMember['customer_id']);
                if ($memberCustomer) {
                    $panelSubjectReview = array_merge(
                        $review,
                        app(\App\Services\LoanApplicationReviewService::class)->subjectFileForCustomer($memberCustomer)
                    );
                }
            }
            $panelSubjectReview['is_member_subject'] = true;
            $panelSubjectReview['member_row'] = $panelMember;
            $panelSubjectReview['crb'] = [
                'score' => $panelMember['crb_score'] ?? null,
                'recommendation' => $panelMember['crb_recommendation'] ?? null,
                'existing_loans' => $panelMember['crb_existing_loans'] ?? null,
                'outstanding_balance' => $panelMember['crb_outstanding'] ?? null,
                'delinquencies' => $panelMember['crb_delinquencies'] ?? null,
                'summary' => $panelMember['crb_summary'] ?? null,
                'checked_at' => $panelMember['crb_checked_at'] ?? null,
            ];
        }
    }

    $phaseKey = $phase ?? '';
    $subjectLabel = match ($panelPerson) {
        'guarantor' => $panelGuarantor['name'] ?? 'Guarantor',
        'member' => $panelMember['name'] ?? 'Group member',
        default => data_get($review, 'customer.full_name') ?: 'Borrower / leader',
    };
@endphp

@if ($phaseKey === 'capacity' && ($section === null || $section === 'documents'))
    <div id="checklist-documents" class="scroll-mt-24 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-bold text-gray-900">Documents · {{ $subjectLabel }}</h4>
            <span class="inline-flex rounded-full bg-brand-muted text-brand ring-1 ring-brand/15 px-2.5 py-1 text-[11px] font-bold">
                {{ ucfirst($panelPerson) }}
            </span>
        </div>

        {{-- Outstanding requests only — full Checklist / Library lives on Profiles → Documents. --}}
        @include('admin.loan-applications.review._documents', [
            'review' => $panelSubjectReview,
            'documentsMode' => 'outstanding',
        ])
    </div>
@endif

@if ($phaseKey === 'capacity' && ($section === null || $section === 'affordability'))
    <div id="checklist-affordability" class="scroll-mt-24 space-y-4">
        @include('admin.loan-applications.review._subject_affordability', [
            'review' => $panelSubjectReview,
            'affordability' => $panelPerson === 'member' && ! empty($panelMember['affordability'])
                ? $panelMember['affordability']
                : ($panelSubjectReview['affordability'] ?? ($affordability ?? ($review['affordability'] ?? null))),
            'counterOffer' => $counterOffer ?? ($review['counter_offer'] ?? null),
        ])
        @if ($panelPerson === 'guarantor' && $panelGuarantor)
            @include('admin.loan-applications.review._guarantor_overview', [
                'guarantor' => $panelGuarantor,
                'single' => true,
            ])
        @endif
    </div>
@endif

@if ($phaseKey === 'capacity' && ($section === null || $section === 'crb'))
    <div id="checklist-crb" class="scroll-mt-24 space-y-4">
        @include('admin.loan-applications.review._subject_crb', ['review' => $panelSubjectReview])
    </div>
@endif

@if ($phaseKey === 'security' && ($section === null || $section === 'group'))
    @if ($panelPerson === 'borrower' && $isGroupFile)
        <div class="space-y-4">
            @include('admin.loan-applications.review._group')
        </div>
    @endif
@endif

@if ($phaseKey === 'security' && ($section === null || $section === 'wrapup'))
    @php
        $wrapCrb = $panelSubjectReview['crb'] ?? ($review['crb'] ?? []);
        if ($panelPerson === 'guarantor' && $panelGuarantor) {
            $wrapCrb = $panelGuarantor['crb'] ?? $wrapCrb;
        }
        $wrapRec = strtoupper((string) ($wrapCrb['recommendation'] ?? '—'));
        $wrapScore = $wrapCrb['score'] ?? '—';
        $wrapLoans = (int) ($wrapCrb['existing_loans'] ?? 0);
        $wrapOut = (float) ($wrapCrb['outstanding_balance'] ?? 0);
        $wrapTitle = match ($panelPerson) {
            'guarantor' => 'Guarantor wrap-up',
            'member' => 'Member wrap-up',
            default => 'Credit file wrap-up',
        };
        $wrapGroupKey = match ($panelPerson) {
            'guarantor' => 'guarantor_wrap',
            'member' => 'member_wrap',
            default => 'credit_file',
        };
    @endphp
    <div id="checklist-wrap-up" class="scroll-mt-24 space-y-4">
        <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3 border-b border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-bold text-gray-900">{{ $wrapTitle }} · {{ $subjectLabel }}</h4>
                </div>
                <button type="button"
                        @click="securityTab = 'checks'; openGroup = @js($wrapGroupKey)"
                        class="shrink-0 inline-flex rounded-xl bg-brand text-white text-xs font-bold px-3.5 py-2 hover:bg-brand-light">
                    Mark wrap-up →
                </button>
            </div>
            <div class="p-4 sm:p-5">
                <div class="grid sm:grid-cols-4 gap-2">
                    <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">CRB</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5 uppercase">{{ $wrapRec }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Score</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5 tabular-nums">{{ $wrapScore }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Other loans</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5 tabular-nums">{{ $wrapLoans }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Outstanding</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5 tabular-nums">
                            {{ $wrapOut > 0 ? format_money($wrapOut) : '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
