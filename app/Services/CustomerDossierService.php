<?php

namespace App\Services;

use App\Models\CreditHistory;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\LoanApplicationDraft;

class CustomerDossierService
{
    public function __construct(
        private readonly ProfileCompletionService $profile,
        private readonly ApplicationRequirementsService $requirements,
        private readonly NidaVerificationService $nida,
        private readonly FaceVerificationService $face,
        private readonly MemberEngagementService $engagement,
        private readonly CrbCreditCheckService $crb,
        private readonly CrbFreshnessService $crbFreshness,
    ) {}

    /** @return array<string, mixed> */
    public function dossier(Customer $customer): array
    {
        $customer->loadMissing([
            'branch',
            'kyc',
            'documents.documentType',
            'documents.verifier',
            'applications.product',
            'loans.product',
            'payments.loan',
            'notificationLogs',
            'guarantorInvitations.application',
        ]);

        $profile = $this->profile->calculate($customer);
        $eligibility = $this->requirements->checklist($customer);
        $faceProgress = $this->face->progress($customer);
        $faceSteps = $this->face->wizardSteps($customer);
        $engagement = $this->engagement->summary($customer);

        $documents = $customer->documents->sortByDesc('created_at')->values();
        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get();

        $checklist = collect($profile['sections'])->map(function (array $section) {
            $tone = $section['complete'] ? 'emerald' : 'amber';

            return [
                'key'    => $section['key'],
                'label'  => $section['label'],
                'detail' => $section['complete'] ? 'Complete' : 'Needs update',
                'tone'   => $tone,
            ];
        })->values()->all();

        $checklist[] = [
            'key'    => 'membership',
            'label'  => 'Membership',
            'detail' => $customer->isMembershipActive() || $customer->isMembershipInGrace()
                ? 'Active'
                : 'Inactive or expired',
            'tone'   => ($customer->isMembershipActive() || $customer->isMembershipInGrace()) ? 'emerald' : 'amber',
        ];

        $incompleteSections = collect($profile['sections'] ?? [])
            ->reject(fn (array $s) => (bool) ($s['complete'] ?? false))
            ->values()
            ->all();

        $hasApplications = $customer->applications->isNotEmpty();
        $crbHistory = $hasApplications ? $this->crb->latest($customer) : null;
        $crbFresh = $crbHistory ? $this->crbFreshness->isFresh($crbHistory) : false;

        $frontFace = collect($faceSteps)->firstWhere('key', 'front')
            ?? collect($faceSteps)->first();
        $facePhotoUrl = $frontFace['previewUrl'] ?? null;

        $idDoc = $documents->first(function ($doc) {
            $code = (string) ($doc->documentType?->code ?? '');

            return in_array($code, ['national_id_front', 'passport', 'voter_id', 'driving_license'], true);
        });

        return [
            'customer'       => $customer,
            'profile'          => $profile,
            'eligibility'      => $eligibility,
            'engagement'       => $engagement,
            'checklist'        => $checklist,
            'incomplete_sections' => $incompleteSections,
            'profile_incomplete'  => (int) ($profile['percent'] ?? 0) < 100,
            'face_progress'    => $faceProgress,
            'face_photo_url'   => $facePhotoUrl,
            'id_photo_url'     => $idDoc?->file_path ? asset('storage/'.$idDoc->file_path) : null,
            'nida_verified'    => $this->nida->isVerified($customer),
            'face_verified'    => $this->face->isVerified($customer),
            'documents'        => $documents,
            'document_types'   => $documentTypes,
            'applications'          => $customer->applications->sortByDesc('created_at')->values(),
            'application_drafts'  => LoanApplicationDraft::query()
                ->where('customer_id', $customer->id)
                ->whereIn('phase', ['details', 'application'])
                ->with('product')
                ->orderByDesc('saved_at')
                ->get(),
            'loans'            => $customer->loans->sortByDesc('created_at')->values(),
            'payments'         => $customer->payments()->with('loan')->latest()->limit(20)->get(),
            'notifications'    => $customer->notificationLogs()->latest()->limit(20)->get(),
            'guarantor_invitations' => $customer->guarantorInvitations()
                ->with(['application.product', 'guarantorCustomer', 'customerGuarantor.guarantor'])
                ->latest()
                ->limit(20)
                ->get(),
            'activity_label'   => activity_type_label($customer->activity_type) ?? $customer->activity_type,
            'income_label'     => income_range_label($customer->income_range) ?? $customer->income_range,
            'pending_documents'=> $documents->whereIn('status', ['pending', 'pending_review'])->count(),
            'has_applications' => $hasApplications,
            'crb'              => $this->crbSnapshot($crbHistory, $crbFresh, $hasApplications),
            'repayment_standing' => $this->repaymentStanding($engagement),
            'read_only'        => true,
        ];
    }

    /** @return array<string, mixed> */
    private function crbSnapshot(?CreditHistory $history, bool $fresh, bool $hasApplications): array
    {
        if (! $hasApplications) {
            return [
                'available' => false,
                'message'   => 'CRB is pulled when the borrower pays the application fee on a loan application.',
            ];
        }

        if (! $history) {
            return [
                'available' => false,
                'message'   => 'No CRB pull on file yet. It refreshes with the next application fee payment.',
            ];
        }

        return [
            'available'   => true,
            'fresh'       => $fresh,
            'score'       => $history->score,
            'risk_grade'  => $history->risk_grade,
            'checked_at'  => $history->checked_at,
            'source'      => $history->source,
            'message'     => $fresh
                ? 'Showing the latest bureau pull from an application.'
                : 'Bureau data is stale. It refreshes only when the borrower applies again and pays the application fee.',
        ];
    }

    /** @param  array<string, mixed>  $engagement */
    private function repaymentStanding(array $engagement): array
    {
        $trust = $engagement['trust_score'] ?? [];
        $streak = $engagement['repayment_streak'] ?? [];
        $percent = (int) ($trust['percent'] ?? 0);

        $label = match (true) {
            $percent >= 80 => 'Strong payer',
            $percent >= 60 => 'Reliable',
            $percent >= 40 => 'Developing',
            $percent > 0 => 'Needs attention',
            default => 'Not enough history',
        };

        return [
            'label'          => $label,
            'trust_percent'  => $percent,
            'trust_stars'    => $trust['filled'] ?? 0,
            'trust_max'      => $trust['max'] ?? 5,
            'streak'         => (int) ($streak['count'] ?? 0),
            'loyalty_points' => (int) ($engagement['loyalty_points'] ?? 0),
            'factors'        => $trust['factors'] ?? [],
        ];
    }
}
