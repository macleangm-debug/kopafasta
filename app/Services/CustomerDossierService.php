<?php

namespace App\Services;

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

        return [
            'customer'       => $customer,
            'profile'          => $profile,
            'eligibility'      => $eligibility,
            'checklist'        => $checklist,
            'face_progress'    => $faceProgress,
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
            'guarantor_invitations' => $customer->guarantorInvitations()->with('application')->latest()->limit(20)->get(),
            'activity_label'   => activity_type_label($customer->activity_type) ?? $customer->activity_type,
            'income_label'     => income_range_label($customer->income_range) ?? $customer->income_range,
            'pending_documents'=> $documents->whereIn('status', ['pending', 'pending_review'])->count(),
        ];
    }
}
