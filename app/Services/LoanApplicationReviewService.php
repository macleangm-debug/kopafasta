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
        $requirements = $application->product?->requirements ?? collect();
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

        $risk = $this->riskAssessment(
            $application,
            $customer,
            $profile,
            $documentProgress,
            $affordability,
        );

        $facePhotos = $this->face->latestByAngle($customer);
        $faceProgress = $this->face->progress($customer);
        $nidaPhotoPath = $customer->kyc?->payload['nida_verification']['photo_path'] ?? null;

        $crb = $this->crbSummary($customer, $application);

        $guarantorRows = $this->guarantorRows($application);
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
            'affordability'      => $affordability,
            'counter_offer'      => app(ApplicationOfferService::class)->maxCounterOffer($application),
            'recommendation'     => [
                'type'        => $application->recommendation_type,
                'amount'      => $application->recommended_amount,
                'offered'     => $application->offered_amount,
                'offer_status'=> $application->offer_status,
                'remarks'     => $application->committee_recommendation,
                'recommended_by' => $application->recommendedByUser,
                'recommended_at' => $application->recommended_at,
            ],
            'risk'               => $risk,
            'face_photos'        => $facePhotos,
            'face_progress'      => $faceProgress,
            'face_angles'        => config('face_verification.angles', []),
            'nida_photo_path'    => $nidaPhotoPath,
            'crb'                => $crb,
            'guarantors'         => $guarantorRows,
            'asset'              => $asset,
            'checklist'          => $checklist,
            'kyc_documents'      => $kycDocuments,
            'profile_documents'  => $profileDocuments,
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
        ];
    }

    private function guarantorRows(LoanApplication $application): Collection
    {
        return CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->with(['guarantor'])
            ->get()
            ->map(function (CustomerGuarantor $link) use ($application) {
                $guarantor = $link->guarantor;
                $invitation = GuarantorInvitation::query()
                    ->where('customer_guarantor_id', $link->id)
                    ->latest()
                    ->first();

                $member = $invitation?->guarantor_customer_id
                    ? Customer::find($invitation->guarantor_customer_id)
                    : null;

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

                return [
                    'name'             => trim(($guarantor?->first_name ?? '').' '.($guarantor?->last_name ?? '')),
                    'membership_no'    => $member?->member_no ?? $invitation?->membership_id,
                    'phone'            => $guarantor?->phone,
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
                ];
            });
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
    ): array {
        $score = 100;
        $factors = [];

        if ($profile['percent'] < $profile['threshold']) {
            $score -= 15;
            $factors[] = 'Profile below minimum completion';
        } elseif ($profile['percent'] < 90) {
            $score -= 5;
        }

        if (! $this->nida->isVerified($customer)) {
            $score -= 20;
            $factors[] = 'NIDA not verified';
        }

        $score -= match ($customer->face_verification_status) {
            'verified' => 0,
            'pending'  => 10,
            'rejected' => 25,
            default    => 15,
        };

        if (($customer->face_verification_status ?? '') === 'rejected') {
            $factors[] = 'Face verification rejected';
        } elseif (($customer->face_verification_status ?? '') === 'pending') {
            $factors[] = 'Face verification awaiting review';
        }

        $score -= match ($affordability['verdict'] ?? 'pass') {
            'fail' => 30,
            'warn' => 12,
            default => 0,
        };

        if (($affordability['verdict'] ?? '') === 'fail') {
            $factors[] = 'Affordability check failed';
        }

        if ($documentProgress < 100) {
            $score -= 10;
            $factors[] = 'Required documents incomplete';
        }

        if ($application->product?->requires_guarantor && ! $this->guarantors->hasApprovedGuarantor($application)) {
            $score -= 12;
            $factors[] = 'Guarantor not approved';
        }

        $overdue = RepaymentSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('status', 'overdue')
            ->count();

        if ($overdue > 0) {
            $score -= min(25, $overdue * 8);
            $factors[] = $overdue.' overdue instalment(s)';
        }

        $score = max(0, min(100, $score));
        $band = $score >= 75 ? 'low' : ($score >= 50 ? 'medium' : 'high');

        return [
            'score'          => $score,
            'band'           => $band,
            'label'          => $this->riskBandLabel($band),
            'factors'        => $factors,
            'recommendation' => match ($band) {
                'low'    => 'approve',
                'medium' => 'refer',
                default  => 'reject',
            },
        ];
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
