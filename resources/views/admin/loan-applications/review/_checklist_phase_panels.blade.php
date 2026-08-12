{{-- Review surfaces that used to live under Profiles — kept on the checklist to avoid duplication. --}}
@php
    $panelPerson = $deskPerson ?? 'borrower';
    $panelG = $deskG ?? null;
    $panelM = $deskM ?? null;
    $panelSubjectReview = $review;
    $panelGuarantor = null;
    $panelMember = null;

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
@endphp

@if ($phaseKey === 'capacity')
    <div id="checklist-documents" class="scroll-mt-24 space-y-4 rounded-2xl bg-white ring-1 ring-brand/10 p-4 sm:p-5">
        <div>
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">Evidence library</p>
            <h4 class="text-sm font-bold text-gray-900 mt-0.5">Documents to review</h4>
            <p class="text-xs text-gray-500 mt-0.5">
                Application uploads for this loan first, then the profile document library. Request follow-ups here — not under Profiles.
            </p>
        </div>

        @if (in_array($panelPerson, ['guarantor', 'member'], true))
            @include('admin.loan-applications.review._subject_documents', ['review' => $panelSubjectReview])
        @else
            @include('admin.loan-applications.review._documents')
        @endif

        @include('admin.loan-applications.review._document-requests')
    </div>

    <div class="space-y-4">
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
        @include('admin.loan-applications.review._subject_crb', ['review' => $panelSubjectReview])
    </div>
@endif

@if ($phaseKey === 'security')
    @if ($panelPerson === 'borrower' && isset($partnerAvailability))
        <div class="space-y-4 rounded-2xl bg-white ring-1 ring-brand/10 p-4 sm:p-5">
            <div>
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">Field coverage</p>
                <h4 class="text-sm font-bold text-gray-900 mt-0.5">Partners for this file</h4>
                <p class="text-xs text-gray-500 mt-0.5">Available vs unavailable partners for collateral / field work in the borrower region.</p>
            </div>
            @include('admin.loan-applications.review._partner_availability', [
                'partnerAvailability' => $partnerAvailability,
                'mode' => 'available',
            ])
            @include('admin.loan-applications.review._partner_availability', [
                'partnerAvailability' => $partnerAvailability,
                'mode' => 'unavailable',
            ])
        </div>
    @endif

    @if ($panelPerson === 'borrower' && ! empty($groupReview['members'] ?? null))
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 sm:p-5 space-y-3">
            <div>
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-semibold">Group loan</p>
                <h4 class="text-sm font-bold text-gray-900 mt-0.5">Group review</h4>
                <p class="text-xs text-gray-500 mt-0.5">Roster, scoring, and feedback — reviewed once on the leader/borrower subject.</p>
            </div>
            @include('admin.loan-applications.review._group')
        </div>
    @endif
@endif
