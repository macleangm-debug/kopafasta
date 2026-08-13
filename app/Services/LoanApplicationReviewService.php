<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerGuarantor;
use App\Models\GuarantorInvitation;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Collection;

class LoanApplicationReviewService
{
    public function __construct(
        private readonly ProfileCompletionService $profile,
        private readonly NidaVerificationService $nida,
        private readonly FaceVerificationService $face,
        private readonly AffordabilityService $affordability,
        private readonly GuarantorInvitationService $guarantors,
    ) {}

    /** @return array<string, mixed> */
    public function dossier(LoanApplication $application): array
    {
        $application->loadMissing([
            'customer.kyc',
            'customer.loans',
            'product.requirements',
            'customerGuarantors.guarantor',
            'assetReservation.asset',
            'recommendedByUser',
        ]);

        $customer = $application->customer;
        abort_unless($customer, 404);

        $profile = $this->profile->calculate($customer);
        $requirements = collect($application->product?->requirements ?? [])
            ->reject(function ($req) {
                $name = (string) ($req->name ?? '');

                // Dormant group evidence (product checkbox off) stays out of the checklist until enabled.
                return in_array($name, ['Group constitution', 'Group member roster'], true)
                    && ! (bool) $req->is_required;
            })
            ->values();
        $allUploads = CustomerDocument::query()
            ->where('loan_application_id', $application->id)
            ->whereNotNull('loan_product_requirement_id')
            ->with('documentType')
            ->latest()
            ->get();

        $uploadHistories = $allUploads->groupBy('loan_product_requirement_id');
        $uploads = $allUploads
            ->unique('loan_product_requirement_id')
            ->keyBy('loan_product_requirement_id');

        $docReview = app(ApplicationDocumentReviewService::class);
        $requirementGuidance = $requirements->mapWithKeys(
            fn ($req) => [$req->id => $docReview->guidanceForRequirement($req)]
        );

        $requiredCount = $requirements->where('is_required', true)->count();
        $satisfiedCount = $requirements
            ->where('is_required', true)
            ->filter(function ($req) use ($uploads) {
                $doc = $uploads->get($req->id);

                return $doc && in_array($doc->status, ['verified', 'approved'], true);
            })
            ->count();
        $missingDocuments = $requirements
            ->where('is_required', true)
            ->filter(function ($req) use ($uploads) {
                $doc = $uploads->get($req->id);

                return ! $doc || ! in_array($doc->status, ['verified', 'approved'], true);
            })
            ->map(fn ($req) => (string) ($req->name ?: 'Required document'))
            ->values()
            ->all();

        $uploadedCount = $requirements
            ->where('is_required', true)
            ->filter(fn ($req) => $uploads->has($req->id))
            ->count();

        $documentProgress = $requiredCount > 0
            ? (int) round(($satisfiedCount / $requiredCount) * 100)
            : 100;

        $affordability = $application->credit_appraisal_payload['affordability'] ?? null;
        if (! $affordability) {
            $affordability = $this->affordability->evaluate($application);
        }

        $crb = $this->crbSummary($customer, $application);

        $guarantorRows = $this->guarantorRows($application);
        $guarantorSuggestion = $this->guarantorSuggestion($guarantorRows, $application);

        $risk = $this->riskAssessment(
            $application,
            $customer,
            $profile,
            $documentProgress,
            $affordability,
            $crb,
            $guarantorSuggestion,
        );

        $facePhotos = $this->face->latestByAngle($customer, true);
        $faceProgress = $this->face->progress($customer);
        $nidaPhotoPath = $customer->kyc?->payload['nida_verification']['photo_path'] ?? null;

        $idDocs = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->whereHas('documentType', fn ($q) => $q->whereIn('code', [
                'national_id_front',
                'national_id_back',
                'passport',
                'voter_id',
                'driving_license',
                'other_id',
            ]))
            ->with('documentType')
            ->latest()
            ->get()
            ->unique(fn (CustomerDocument $doc) => $doc->documentType?->code ?: $doc->id)
            ->keyBy(fn (CustomerDocument $doc) => $doc->documentType?->code ?: ('doc-'.$doc->id));

        $asset = $this->assetSummary($application);

        $checklist = $this->checklist(
            $customer,
            $profile,
            $documentProgress,
            $guarantorRows,
            $application,
        );

        $kycDocuments = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->with('documentType')
            ->latest()
            ->get()
            ->unique(fn (CustomerDocument $doc) => $doc->document_type_id ?: $doc->id);

        $profileDocuments = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->where(function ($query) use ($application) {
                $query->whereNull('loan_application_id')
                    ->orWhere('loan_application_id', $application->id);
            })
            ->with('documentType')
            ->latest()
            ->get();

        return [
            'customer'           => $customer,
            'product'            => $application->product,
            'profile'            => $profile,
            'requirements'       => $requirements,
            'uploads'            => $uploads,
            'upload_histories'   => $uploadHistories,
            'requirement_guidance' => $requirementGuidance,
            'document_progress'  => $documentProgress,
            'required_docs'      => $requiredCount,
            'satisfied_docs'     => $satisfiedCount,
            'uploaded_docs'      => $uploadedCount,
            'missing_documents'  => $missingDocuments,
            'affordability'      => $affordability,
            'counter_offer'      => app(ApplicationOfferService::class)->maxCounterOffer($application),
            'recommendation'     => [
                'type'        => $application->recommendation_type,
                'amount'      => $application->recommended_amount,
                'offered'     => $application->offered_amount,
                'offer_status'=> $application->offer_status,
                'remarks'     => $application->committee_recommendation,
                'rationale'   => data_get($application->screening_payload, 'recommendation_meta.rationale'),
                'rationale_label' => data_get($application->screening_payload, 'recommendation_meta.rationale_label'),
                'decision_reason' => data_get($application->screening_payload, 'recommendation_meta.decision_reason'),
                'additional_notes' => data_get($application->screening_payload, 'recommendation_meta.additional_notes'),
                'differs_from_crb' => (bool) data_get($application->screening_payload, 'recommendation_meta.differs_from_crb'),
                'crb_at_recommend' => data_get($application->screening_payload, 'recommendation_meta.crb_recommendation'),
                'recommended_by' => $application->recommendedByUser,
                'recommended_at' => $application->recommended_at,
            ],
            'screening_checklist' => app(ScreeningChecklistService::class)->viewModel($application),
            'risk'               => $risk,
            'guarantor_suggestion' => $guarantorSuggestion,
            'face_photos'        => $facePhotos,
            'face_progress'      => $faceProgress,
            'face_angles'        => config('face_verification.angles', []),
            'nida_photo_path'    => $nidaPhotoPath,
            'id_documents'       => $idDocs,
            'alternate_id_types' => (array) ($customer->alternate_id_types ?? []),
            'alternate_id_notes' => $customer->alternate_id_notes,
            'crb'                => $crb,
            'crb_cross_check'    => data_get($application->credit_appraisal_payload, 'crb_cross_check'),
            'guarantors'         => $guarantorRows,
            'asset'              => $asset,
            'checklist'          => $checklist,
            'kyc_documents'      => $kycDocuments,
            'profile_documents'  => $profileDocuments,
            'customer_assets'    => app(CustomerAssetService::class)->forCustomer($customer),
            'activity_label'     => display_label($customer->activity_type, 'activity_type')
                ?: activity_type_label($customer->activity_type) ?? $customer->activity_type,
            'income_label'       => income_range_label($customer->income_range) ?? $customer->income_range,
            'business_name'      => $this->activityValue($customer, 'business_name')
                ?: $this->activityValue($customer, 'employer_name')
                ?: $this->activityValue($customer, 'trade_type'),
        ];
    }

    /** @return list<array{key: string, label: string, status: string, tone: string, detail: string}> */
    private function checklist(
        Customer $customer,
        array $profile,
        int $documentProgress,
        Collection $guarantorRows,
        LoanApplication $application,
    ): array {
        $items = [
            [
                'key'    => 'profile',
                'label'  => 'Profile complete',
                'status' => $this->profile->meetsThreshold($customer) ? 'complete' : 'pending',
                'tone'   => $this->profile->meetsThreshold($customer) ? 'emerald' : 'amber',
                'detail' => $profile['percent'].'% (min '.$profile['threshold'].'%)',
            ],
            [
                'key'    => 'documents',
                'label'  => 'Required documents present',
                'status' => $documentProgress >= 100 ? 'complete' : ($documentProgress > 0 ? 'review' : 'pending'),
                'tone'   => $documentProgress >= 100 ? 'emerald' : ($documentProgress > 0 ? 'amber' : 'gray'),
                'detail' => $documentProgress.'% satisfied',
            ],
            [
                'key'    => 'nida',
                'label'  => 'Identity verified',
                'status' => $this->nida->isVerified($customer) ? 'complete' : 'pending',
                'tone'   => $this->nida->isVerified($customer) ? 'emerald' : 'amber',
                'detail' => display_label($customer->nida_verification_status, 'nida_verification_status')
                    ?: 'Not verified',
            ],
            [
                'key'    => 'face',
                'label'  => 'Face verification',
                'status' => match ($customer->face_verification_status) {
                    'verified' => 'complete',
                    'pending'  => 'review',
                    'rejected' => 'blocked',
                    default    => 'pending',
                },
                'tone'   => match ($customer->face_verification_status) {
                    'verified' => 'emerald',
                    'pending'  => 'amber',
                    'rejected' => 'red',
                    default    => 'gray',
                },
                'detail' => display_label($customer->face_verification_status, 'face_verification_status')
                    ?: 'Not started',
            ],
            [
                'key'    => 'residence',
                'label'  => 'Residence verified',
                'status' => app(ProfileValidationService::class)->hasResidenceLetter($customer) ? 'complete' : 'pending',
                'tone'   => app(ProfileValidationService::class)->hasResidenceLetter($customer) ? 'emerald' : 'amber',
                'detail' => app(ProfileValidationService::class)->hasResidenceLetter($customer) ? 'On file' : 'Missing',
            ],
            [
                'key'    => 'income',
                'label'  => 'Proof of income present',
                'status' => app(IncomeProofService::class)->satisfiesRequirement($customer) ? 'complete' : 'pending',
                'tone'   => app(IncomeProofService::class)->satisfiesRequirement($customer) ? 'emerald' : 'amber',
                'detail' => app(IncomeProofService::class)->satisfiesRequirement($customer) ? 'On file' : 'Missing',
            ],
        ];

        if ($application->product?->requires_guarantor) {
            $approved = $guarantorRows->contains(fn (array $row) => $row['status'] === 'approved');
            $assigned = $guarantorRows->isNotEmpty();
            $items[] = [
                'key'    => 'guarantor',
                'label'  => 'Guarantor assigned',
                'status' => $assigned ? ($approved ? 'complete' : 'review') : 'pending',
                'tone'   => $approved ? 'emerald' : ($assigned ? 'amber' : 'gray'),
                'detail' => $approved
                    ? __('borrower.apply.guarantor_status.accepted')
                    : ($guarantorRows->first()['status_label'] ?? __('borrower.apply.guarantor_status.invitation_sent')),
            ];
        }

        $asset = $this->assetSummary($application);
        $lending = app(\App\Services\AssetLendingService::class);
        $needsInsurance = $asset && (
            $lending->isAssetLendingApplication($application)
            || (bool) ($lending->categoryRequirements($asset['category'])['insurance_required'] ?? false)
        );

        if ($needsInsurance) {
            $insurance = $asset['insurance_status'];
            $items[] = [
                'key'    => 'asset_insurance',
                'label'  => 'Asset insurance',
                'status' => match ($insurance['status']) {
                    'valid'    => 'complete',
                    'expiring' => 'review',
                    'expired', 'missing' => 'blocked',
                    default    => 'pending',
                },
                'tone'   => $insurance['tone'],
                'detail' => $insurance['detail'],
            ];
        }

        return $items;
    }

    /** @return Collection<int, array<string, mixed>> */
    /** @return array<string, mixed>|null */
    private function assetSummary(LoanApplication $application): ?array
    {
        $reservation = $application->assetReservation;
        if (! $reservation) {
            return null;
        }

        $asset = $reservation->asset;
        if (! $asset) {
            return null;
        }

        $photos = collect($asset->photos ?? [])
            ->filter()
            ->values()
            ->map(fn ($path, $index) => [
                'url'   => marketplace_photo_url($path),
                'label' => 'Photo '.($index + 1),
            ])
            ->all();

        return [
            'title'                => $asset->title,
            'category'             => $asset->category,
            'supplier'             => $asset->supplier_name,
            'asset_value'          => (float) $asset->asset_value,
            'customer_deposit'     => (float) ($reservation->deposit_amount ?: $asset->customer_deposit),
            'reservation_status'   => $reservation->status,
            'availability_status'  => $asset->availability_status ?? 'available',
            'viewing_date'         => $reservation->viewing_date,
            'deposit_status'       => $reservation->deposit_status,
            'reservation_fee_status' => $reservation->reservation_fee_status,
            'serial_number'        => $asset->serial_number,
            'chassis_number'       => $asset->chassis_number,
            'engine_number'        => $asset->engine_number,
            'insurance_policy_number' => $asset->insurance_policy_number,
            'insurance_expires_at'    => $asset->insurance_expires_at,
            'waiting_period_days'     => $asset->waiting_period_days,
            'insurance_status'        => app(\App\Services\AssetLendingService::class)->insuranceStatus($asset->insurance_expires_at),
            'photos'                  => $photos,
        ];
    }

    private function guarantorRows(LoanApplication $application): Collection
    {
        $crbService = app(CrbCreditCheckService::class);
        $onboarding = app(GuarantorOnboardingService::class);

        return CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->with(['guarantor'])
            ->get()
            ->map(function (CustomerGuarantor $link) use ($application, $crbService, $onboarding) {
                $guarantor = $link->guarantor;
                $invitation = GuarantorInvitation::query()
                    ->where('customer_guarantor_id', $link->id)
                    ->latest()
                    ->first();

                $member = $invitation?->guarantor_customer_id
                    ? Customer::with('kyc')->find($invitation->guarantor_customer_id)
                    : null;

                $profileStatus = $member
                    ? $onboarding->guarantorProfileStatus($member)
                    : ['met' => false, 'percent' => 0, 'checklist' => [], 'next_url' => null];
                $profileComplete = (bool) ($profileStatus['met'] ?? false);

                $activeLoans = $member
                    ? Loan::query()->where('customer_id', $member->id)->whereIn('status', ['active', 'disbursed'])->count()
                    : 0;

                $guaranteedLoans = $member
                    ? CustomerGuarantor::query()
                        ->whereHas('guarantor', fn ($q) => $q->where('phone', $guarantor?->phone))
                        ->where('status', 'approved')
                        ->count()
                    : 0;

                $riskScore = max(0, 100 - ($activeLoans * 15) - ($guaranteedLoans * 10));
                $riskBand = $riskScore >= 75 ? 'low' : ($riskScore >= 50 ? 'medium' : 'high');
                $exposureSummary = $member
                    ? app(LoanPolicyService::class)->guarantorExposureSummary($member)
                    : ['count' => 0, 'exposure' => 0.0, 'max' => 0];
                $affordability = $member
                    ? app(AffordabilityService::class)->evaluateForGuarantor(
                        $member,
                        round((float) ($invitation?->requested_amount ?? $application->requested_amount ?? 0) / 12, 2),
                    )
                    : null;

                $crb = null;
                $crbExplain = null;
                if ($member && $profileComplete && $link->status !== 'rejected') {
                    $pull = $crbService->ensureGuarantorCrb($member, $application);
                    $crb = $pull['summary'];
                    $crbExplain = $crbService->recommendationExplanation($crb);
                }

                $file = null;
                $profile = null;
                if ($member && $profileComplete) {
                    $file = $this->subjectFile($member);
                    $profile = [
                        'full_name' => $member->full_name,
                        'date_of_birth' => optional($member->date_of_birth)->format('d M Y'),
                        'gender' => $member->gender ? ucfirst((string) $member->gender) : null,
                        'national_id' => $member->national_id,
                        'phone' => $member->phone,
                        'email' => $member->email,
                        'region' => $member->region,
                        'district' => $member->district,
                        'ward' => $member->ward,
                        'street' => $member->street ?: $member->address,
                        'nida_status' => display_label($member->nida_verification_status, 'nida_verification_status') ?: 'Not verified',
                        'face_status' => display_label($member->face_verification_status, 'face_verification_status') ?: 'Not started',
                        'activity' => display_label($member->activity_type, 'activity_type')
                            ?: (activity_type_label($member->activity_type) ?? $member->activity_type),
                        'income_range' => income_range_label($member->income_range) ?? $member->income_range,
                    ];
                }

                return [
                    'link_id'          => $link->id,
                    'invitation_id'    => $invitation?->id,
                    'customer_id'      => $member?->id,
                    'name'             => trim(($guarantor?->first_name ?? '').' '.($guarantor?->last_name ?? ''))
                        ?: ($member?->full_name ?? '—'),
                    'membership_no'    => $member?->member_no ?? $invitation?->membership_id,
                    'phone'            => $guarantor?->phone ?? $member?->phone,
                    'status'           => $link->status,
                    'status_label'     => app(GuarantorInvitationService::class)->underwritingGuarantorStatusLabel($link),
                    'relationship'     => $guarantor?->relationship,
                    'active_loans'     => $activeLoans,
                    'guaranteed_loans' => $guaranteedLoans,
                    'guarantee_count'  => $exposureSummary['count'],
                    'guarantee_exposure' => $exposureSummary['exposure'],
                    'guarantee_max'    => $exposureSummary['max'],
                    'affordability'    => $affordability,
                    'risk_band'        => $riskBand,
                    'risk_label'       => $this->riskBandLabel($riskBand),
                    'profile_complete' => $profileComplete,
                    'profile_percent'  => (int) ($profileStatus['percent'] ?? 0),
                    'profile'          => $profile,
                    'file'             => $file,
                    'crb'              => $crb,
                    'crb_explanation'  => $crbExplain,
                    'can_change'       => in_array($link->status, ['pending', 'approved'], true),
                ];
            });
    }

    /**
     * Borrower-equivalent dossier slice for a guarantor / group member Customer.
     *
     * @return array<string, mixed>
     */
    public function subjectFileForCustomer(Customer $customer): array
    {
        return $this->subjectFile($customer);
    }

    /**
     * Borrower-equivalent dossier slice for a guarantor Customer (profile sections + face + docs).
     *
     * @return array<string, mixed>
     */
    private function subjectFile(Customer $customer): array
    {
        $profile = $this->profile->calculate($customer);
        $facePhotos = $this->face->latestByAngle($customer, true);
        $faceProgress = $this->face->progress($customer);
        $nidaPhotoPath = $customer->kyc?->payload['nida_verification']['photo_path'] ?? null;

        $idDocs = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->whereHas('documentType', fn ($q) => $q->whereIn('code', [
                'national_id_front',
                'national_id_back',
                'passport',
                'voter_id',
                'driving_license',
                'other_id',
            ]))
            ->with('documentType')
            ->latest()
            ->get()
            ->unique(fn (CustomerDocument $doc) => $doc->documentType?->code ?: $doc->id)
            ->keyBy(fn (CustomerDocument $doc) => $doc->documentType?->code ?: ('doc-'.$doc->id));

        $kycDocuments = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->with('documentType')
            ->latest()
            ->get()
            ->unique(fn (CustomerDocument $doc) => $doc->document_type_id ?: $doc->id);

        $profileDocuments = CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->with('documentType')
            ->latest()
            ->get();

        return [
            'customer'           => $customer,
            'profile'            => $profile,
            'face_photos'        => $facePhotos,
            'face_progress'      => $faceProgress,
            'face_angles'        => config('face_verification.angles', []),
            'nida_photo_path'    => $nidaPhotoPath,
            'id_documents'       => $idDocs,
            'alternate_id_types' => (array) ($customer->alternate_id_types ?? []),
            'alternate_id_notes' => $customer->alternate_id_notes,
            'kyc_documents'      => $kycDocuments,
            'profile_documents'  => $profileDocuments,
            'customer_assets'    => app(CustomerAssetService::class)->forCustomer($customer),
            'activity_label'     => display_label($customer->activity_type, 'activity_type')
                ?: activity_type_label($customer->activity_type) ?? $customer->activity_type,
            'income_label'       => income_range_label($customer->income_range) ?? $customer->income_range,
            'business_name'      => $this->activityValue($customer, 'business_name')
                ?: $this->activityValue($customer, 'employer_name')
                ?: $this->activityValue($customer, 'trade_type'),
        ];
    }

    /**
     * Top-of-desk guarantor suggestion for screening + committee.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function guarantorSuggestion(Collection $rows, LoanApplication $application): array
    {
        if (! $application->product?->requires_guarantor) {
            return [
                'required' => false,
                'recommendation' => 'not_required',
                'label' => 'Not required',
                'summary' => 'This product does not require a guarantor.',
                'name' => null,
                'score' => null,
                'profile_complete' => false,
            ];
        }

        if ($rows->isEmpty()) {
            return [
                'required' => true,
                'recommendation' => 'missing',
                'label' => 'Awaiting',
                'summary' => 'Unusual — files normally reach screening only after a guarantor completes. Check invitation status.',
                'name' => null,
                'score' => null,
                'profile_complete' => false,
            ];
        }

        $primary = $rows->first(fn (array $row) => ($row['status'] ?? '') === 'approved' && ($row['profile_complete'] ?? false))
            ?? $rows->first(fn (array $row) => ($row['profile_complete'] ?? false) && ($row['status'] ?? '') !== 'rejected')
            ?? $rows->first(fn (array $row) => ($row['status'] ?? '') !== 'rejected')
            ?? $rows->first();

        if (! ($primary['profile_complete'] ?? false)) {
            return [
                'required' => true,
                'recommendation' => 'pending_profile',
                'label' => 'Profile incomplete',
                'summary' => ($primary['name'] ?? 'Guarantor').' has not finished their profile yet — unusual at screening; open their file to check.',
                'name' => $primary['name'] ?? null,
                'score' => null,
                'profile_complete' => false,
                'status_label' => $primary['status_label'] ?? null,
                'profile_percent' => $primary['profile_percent'] ?? 0,
                'link_id' => $primary['link_id'] ?? null,
            ];
        }

        $crb = $primary['crb'] ?? [];
        $rec = strtolower((string) ($crb['recommendation'] ?? ''));
        $explain = $primary['crb_explanation'] ?? [];

        return [
            'required' => true,
            'recommendation' => $rec !== '' ? $rec : 'refer',
            'label' => $rec !== '' ? ucfirst($rec) : 'Review',
            'summary' => $explain['summary'] ?? 'Guarantor profile complete — review their file sections.',
            'reasons' => $explain['reasons'] ?? [],
            'name' => $primary['name'] ?? null,
            'score' => $crb['score'] ?? null,
            'profile_complete' => true,
            'status_label' => $primary['status_label'] ?? null,
            'existing_loans' => (int) ($crb['existing_loans'] ?? 0),
            'outstanding_balance' => (float) ($crb['outstanding_balance'] ?? 0),
            'delinquencies' => (int) ($crb['delinquencies'] ?? 0),
            'freshness_label' => $crb['freshness_label'] ?? null,
            'loan_history' => $crb['loan_history'] ?? [],
            'affordability' => $primary['affordability'] ?? null,
            'link_id' => $primary['link_id'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function crbSummary(Customer $customer, ?LoanApplication $application = null): array
    {
        return app(CrbCreditCheckService::class)->summaryForCustomer($customer, $application);
    }

    /** @param array{percent: int, threshold: int} $profile */
    private function riskAssessment(
        LoanApplication $application,
        Customer $customer,
        array $profile,
        int $documentProgress,
        array $affordability,
        array $crb = [],
        array $guarantorSuggestion = [],
    ): array {
        $score = 100;
        $factors = [];

        if ($profile['percent'] < $profile['threshold']) {
            $score -= 15;
            $factors[] = 'Profile below minimum completion (−15)';
        } elseif ($profile['percent'] < 90) {
            $score -= 5;
            $factors[] = 'Profile incomplete under 90% (−5)';
        }

        // Identity photos are reviewed on the screening desk (compare + re-upload) —
        // do not score NIDA/face "verification procedure" status here.

        $affordDeduction = match ($affordability['verdict'] ?? 'pass') {
            'fail' => 30,
            'warn' => 12,
            default => 0,
        };
        $score -= $affordDeduction;
        if (($affordability['verdict'] ?? '') === 'fail') {
            $factors[] = 'Affordability check failed (−30)';
        } elseif (($affordability['verdict'] ?? '') === 'warn') {
            $factors[] = 'Affordability near limit (−12)';
        }

        if ($documentProgress < 100) {
            $score -= 10;
            $factors[] = 'Required documents incomplete (−10)';
        }

        if ($application->product?->requires_guarantor && ! $this->guarantors->hasApprovedGuarantor($application)) {
            $score -= 12;
            $factors[] = 'Guarantor not approved (−12)';
        }

        $gRec = strtolower((string) ($guarantorSuggestion['recommendation'] ?? ''));
        $gScore = $guarantorSuggestion['score'] ?? null;
        if (($guarantorSuggestion['required'] ?? false) === true) {
            $gHit = match ($gRec) {
                'reject' => 15,
                'refer' => 6,
                'missing', 'pending_profile' => 8,
                'approve' => 0,
                'not_required' => 0,
                default => 0,
            };
            if ($gHit > 0) {
                $score -= $gHit;
                if ($gRec === 'reject') {
                    $factors[] = 'Guarantor CRB suggests reject'.($gScore !== null ? ' (score '.$gScore.')' : '').' (−15)';
                } elseif ($gRec === 'refer') {
                    $factors[] = 'Guarantor CRB suggests refer'.($gScore !== null ? ' (score '.$gScore.')' : '').' (−6)';
                } elseif ($gRec === 'missing') {
                    $factors[] = 'Guarantor missing (−8)';
                } else {
                    $factors[] = 'Guarantor profile incomplete (−8)';
                }
            } elseif ($gRec === 'approve') {
                $factors[] = 'Guarantor CRB suggests approve'.($gScore !== null ? ' (score '.$gScore.')' : '').' (no deduction)';
            }
        }

        $overdue = RepaymentSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('status', 'overdue')
            ->count();

        if ($overdue > 0) {
            $overdueHit = min(25, $overdue * 8);
            $score -= $overdueHit;
            $factors[] = $overdue.' overdue instalment(s) (−'.$overdueHit.')';
        }

        // Bureau signal — keep application risk coherent with CRB suggestion.
        $crbRec = strtolower((string) ($crb['recommendation'] ?? ''));
        $crbScore = $crb['score'] ?? null;
        $crbHit = match ($crbRec) {
            'reject' => 20,
            'refer' => 8,
            'approve' => 0,
            default => ($crbScore === null ? 5 : 0),
        };
        if ($crbHit > 0) {
            $score -= $crbHit;
            if ($crbRec === 'reject') {
                $factors[] = 'CRB suggests reject'.($crbScore !== null ? ' (score '.$crbScore.')' : '').' (−20)';
            } elseif ($crbRec === 'refer') {
                $factors[] = 'CRB suggests refer'.($crbScore !== null ? ' (score '.$crbScore.')' : '').' (−8)';
            } else {
                $factors[] = 'CRB score missing (−5)';
            }
        } elseif ($crbRec === 'approve') {
            $factors[] = 'CRB suggests approve'.($crbScore !== null ? ' (score '.$crbScore.')' : '').' (no deduction)';
        }

        $score = max(0, min(100, $score));
        $band = $score >= 75 ? 'low' : ($score >= 50 ? 'medium' : 'high');
        $recommendation = match ($band) {
            'low'    => 'approve',
            'medium' => 'refer',
            default  => 'reject',
        };

        $explanation = $this->riskExplanation($score, $band, $recommendation, $factors, $crbRec, $crbScore);

        return [
            'score'          => $score,
            'band'           => $band,
            'label'          => $this->riskBandLabel($band),
            'factors'        => $factors,
            'explanation'    => $explanation,
            'recommendation' => $recommendation,
            'crb_recommendation' => $crbRec !== '' ? $crbRec : null,
            'crb_score'      => $crbScore,
        ];
    }

    /**
     * @param  list<string>  $factors
     */
    private function riskExplanation(
        int $score,
        string $band,
        string $recommendation,
        array $factors,
        string $crbRec,
        mixed $crbScore,
    ): string {
        $bandSentence = match ($band) {
            'low' => 'Score '.$score.'/100 is in the low-risk band (≥75), so the system suggests APPROVE.',
            'medium' => 'Score '.$score.'/100 is in the medium-risk band (50–74), so the system suggests REFER for manual review.',
            default => 'Score '.$score.'/100 is in the high-risk band (<50), so the system suggests REJECT.',
        };

        $drivers = $factors === []
            ? 'No deductions were applied.'
            : 'Main drivers: '.implode('; ', array_slice($factors, 0, 4)).'.';

        $crbSentence = match ($crbRec) {
            'approve' => 'CRB also leans approve'.($crbScore !== null ? ' (bureau score '.$crbScore.')' : '').'.',
            'refer' => 'CRB leans refer'.($crbScore !== null ? ' (bureau score '.$crbScore.')' : '').', which pulled the application score down.',
            'reject' => 'CRB leans reject'.($crbScore !== null ? ' (bureau score '.$crbScore.')' : '').', which strongly pulled the application score down.',
            default => 'No CRB recommendation was available to weight the score.',
        };

        return $bandSentence.' '.$drivers.' '.$crbSentence.' Staff may still override after reviewing the file.';
    }

    private function riskBandLabel(string $band): string
    {
        return match ($band) {
            'low'    => 'Low risk',
            'medium' => 'Medium risk',
            default  => 'High risk',
        };
    }

    private function activityValue(Customer $customer, string $key): ?string
    {
        $details = $customer->activity_details ?? [];

        return filled($details[$key] ?? null) ? (string) $details[$key] : null;
    }
}
