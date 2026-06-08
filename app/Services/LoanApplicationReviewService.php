<?php

namespace App\Services;

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
        ]);

        $customer = $application->customer;
        abort_unless($customer, 404);

        $profile = $this->profile->calculate($customer);
        $requirements = $application->product?->requirements ?? collect();
        $uploads = CustomerDocument::query()
            ->where('loan_application_id', $application->id)
            ->whereNotNull('loan_product_requirement_id')
            ->latest()
            ->get()
            ->unique('loan_product_requirement_id')
            ->keyBy('loan_product_requirement_id');

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

        $checklist = $this->checklist(
            $customer,
            $profile,
            $documentProgress,
            $guarantorRows,
            $application,
        );

        return [
            'customer'           => $customer,
            'product'            => $application->product,
            'profile'            => $profile,
            'requirements'       => $requirements,
            'uploads'            => $uploads,
            'document_progress'  => $documentProgress,
            'required_docs'      => $requiredCount,
            'satisfied_docs'     => $satisfiedCount,
            'uploaded_docs'      => $uploadedCount,
            'affordability'      => $affordability,
            'risk'               => $risk,
            'face_photos'        => $facePhotos,
            'face_progress'      => $faceProgress,
            'face_angles'        => config('face_verification.angles', []),
            'nida_photo_path'    => $nidaPhotoPath,
            'crb'                => $crb,
            'guarantors'         => $guarantorRows,
            'checklist'          => $checklist,
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
                'key'    => 'nida',
                'label'  => 'NIDA verified',
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
                'key'    => 'profile',
                'label'  => 'Profile completion',
                'status' => $this->profile->meetsThreshold($customer) ? 'complete' : 'pending',
                'tone'   => $this->profile->meetsThreshold($customer) ? 'emerald' : 'amber',
                'detail' => $profile['percent'].'% (min '.$profile['threshold'].'%)',
            ],
            [
                'key'    => 'documents',
                'label'  => 'Required documents',
                'status' => $documentProgress >= 100 ? 'complete' : ($documentProgress > 0 ? 'review' : 'pending'),
                'tone'   => $documentProgress >= 100 ? 'emerald' : ($documentProgress > 0 ? 'amber' : 'gray'),
                'detail' => $documentProgress.'% satisfied',
            ],
        ];

        if ($application->product?->requires_guarantor) {
            $approved = $guarantorRows->contains(fn (array $row) => $row['status'] === 'approved');
            $items[] = [
                'key'    => 'guarantor',
                'label'  => 'Guarantor approval',
                'status' => $approved ? 'complete' : 'pending',
                'tone'   => $approved ? 'emerald' : 'amber',
                'detail' => $approved ? 'Guarantor approved' : 'Awaiting guarantor',
            ];
        }

        return $items;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function guarantorRows(LoanApplication $application): Collection
    {
        return CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->with(['guarantor'])
            ->get()
            ->map(function (CustomerGuarantor $link) {
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

                return [
                    'name'             => trim(($guarantor?->first_name ?? '').' '.($guarantor?->last_name ?? '')),
                    'membership_no'    => $member?->member_no ?? $invitation?->membership_id,
                    'phone'            => $guarantor?->phone,
                    'status'           => $link->status,
                    'relationship'     => $guarantor?->relationship,
                    'active_loans'     => $activeLoans,
                    'guaranteed_loans' => $guaranteedLoans,
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
