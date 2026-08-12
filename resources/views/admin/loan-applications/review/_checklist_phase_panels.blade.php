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
        default => $review['customer']->full_name ?? 'Borrower / leader',
    };
@endphp

@if ($phaseKey === 'capacity' && ($section === null || $section === 'documents'))
    <div id="checklist-documents" class="scroll-mt-24 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">Evidence</p>
                <h4 class="text-sm font-bold text-gray-900 mt-0.5">Documents · {{ $subjectLabel }}</h4>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if ($panelPerson === 'member')
                        Profile documents for this group member only. Loan product uploads stay on the leader / application subject.
                    @elseif ($panelPerson === 'guarantor')
                        Profile documents for this guarantor. Application product uploads stay on the borrower file.
                    @elseif ($isGroupFile)
                        Application uploads for this group loan, then the leader’s profile library.
                    @else
                        Application uploads for this loan first, then the borrower profile library.
                    @endif
                </p>
            </div>
            <span class="inline-flex rounded-full bg-brand-muted text-brand ring-1 ring-brand/15 px-2.5 py-1 text-[11px] font-bold">
                {{ ucfirst($panelPerson) }}
            </span>
        </div>

        @if (in_array($panelPerson, ['guarantor', 'member'], true))
            @include('admin.loan-applications.review._subject_documents', ['review' => $panelSubjectReview])
        @else
            @include('admin.loan-applications.review._documents', ['review' => $panelSubjectReview])
        @endif

        @include('admin.loan-applications.review._document-requests')
    </div>
@endif

@if ($phaseKey === 'capacity' && ($section === null || $section === 'affordability'))
    <div id="checklist-affordability" class="scroll-mt-24 space-y-4">
        @include('admin.loan-applications.review._subject_affordability', [
            'review' => $panelSubjectReview,
            'affordability' => $panelSubjectReview['affordability'] ?? ($affordability ?? ($review['affordability'] ?? null)),
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
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 sm:p-5 space-y-3">
            <div>
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">Group loan</p>
                <h4 class="text-sm font-bold text-gray-900 mt-0.5">Group review</h4>
                <p class="text-xs text-gray-500 mt-0.5">Roster, scoring, and feedback — reviewed once on the leader subject.</p>
            </div>
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
    @endphp
    <div id="checklist-wrap-up" class="scroll-mt-24 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white">
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">Close the file</p>
            <h4 class="text-base font-bold text-gray-900 mt-0.5">{{ $wrapTitle }} · {{ $subjectLabel }}</h4>
            <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                Snapshot before Decision. Mark wrap-up Pass / Fail under the Checks sub-tab. Full CRB detail stays in Capacity → CRB.
            </p>
        </div>
        <div class="p-4 sm:p-5 space-y-3">
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
        <p class="text-[11px] text-gray-500">
            High-risk Fail on wrap-up CRB or identity pushes readiness to <span class="font-semibold text-gray-700">Lean Reject</span> once the checklist is complete.
        </p>
        </div>
    </div>
@endif
