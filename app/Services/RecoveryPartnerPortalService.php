<?php

namespace App\Services;

use App\Models\CollectionAction;
use App\Models\GuarantorInvitation;
use App\Models\RecoveryAssignment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecoveryPartnerPortalService
{
    public function __construct(
        private readonly RecoveryAssignmentService $assignments,
        private readonly LoanCollectionActionService $collectionActions,
    ) {}

    public function assertVendorOwnsAssignment(RecoveryAssignment $assignment, Vendor $vendor): void
    {
        if ((int) $assignment->vendor_id !== (int) $vendor->id) {
            abort(404);
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function portalActions(string $partnerType): array
    {
        return config("recovery.portal_actions.{$partnerType}", config('recovery.portal_actions.call_center', []));
    }

    /** @return array<string, mixed> */
    public function caseViewData(RecoveryAssignment $assignment): array
    {
        $assignment->loadMissing([
            'arrearCase.loan.customer',
            'arrearCase.loan.product',
            'arrearCase.loan.application',
            'vendorTask.documents',
        ]);

        $loan = $assignment->arrearCase?->loan;
        $customer = $loan?->customer;
        $guarantor = null;

        if ($loan?->loan_application_id) {
            $guarantor = GuarantorInvitation::query()
                ->with('guarantorCustomer')
                ->where('loan_application_id', $loan->loan_application_id)
                ->whereIn('status', ['accepted', 'completed', 'signed'])
                ->latest('id')
                ->first();
        }

        $slaDaysRemaining = null;
        if ($assignment->sla_due_at && $assignment->isOpen()) {
            $slaDaysRemaining = (int) now()->startOfDay()->diffInDays($assignment->sla_due_at->startOfDay(), false);
        }

        $actions = $assignment->arrearCase
            ? CollectionAction::query()
                ->with('performer')
                ->where('arrear_case_id', $assignment->arrear_case_id)
                ->latest('performed_at')
                ->limit(20)
                ->get()
            : collect();

        return [
            'loan'               => $loan,
            'customer'           => $customer,
            'guarantor'          => $guarantor,
            'guarantor_name'     => $guarantor?->guarantorCustomer?->full_name
                ?? $guarantor?->invitee_name,
            'guarantor_phone'    => $guarantor?->contact
                ?? $guarantor?->guarantorCustomer?->phone,
            'sla_days_remaining' => $slaDaysRemaining,
            'portal_actions'     => $this->portalActions($assignment->partner_type),
            'activity'           => $actions,
        ];
    }

    public function startCase(RecoveryAssignment $assignment, Vendor $vendor, User $actor): RecoveryAssignment
    {
        $this->assertVendorOwnsAssignment($assignment, $vendor);

        if ($assignment->status !== RecoveryAssignment::STATUS_ASSIGNED) {
            throw ValidationException::withMessages([
                'status' => 'This case has already been started.',
            ]);
        }

        return $this->assignments->start($assignment, $actor);
    }

    public function recordAction(
        RecoveryAssignment $assignment,
        Vendor $vendor,
        User $actor,
        string $actionKey,
        ?string $notes = null,
        ?UploadedFile $file = null,
    ): RecoveryAssignment {
        $this->assertVendorOwnsAssignment($assignment, $vendor);

        $config = $this->actionConfig($assignment->partner_type, $actionKey);
        if ($config === null) {
            throw ValidationException::withMessages([
                'action' => 'Invalid recovery action.',
            ]);
        }

        if (! $assignment->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'This recovery case is already closed.',
            ]);
        }

        $notes = trim((string) $notes);
        if (($config['notes'] ?? null) === 'required' && $notes === '' && ! ($config['accepts_file'] ?? false)) {
            throw ValidationException::withMessages([
                'notes' => 'Notes are required for this action.',
            ]);
        }

        if (($config['accepts_file'] ?? false) && ! $file) {
            throw ValidationException::withMessages([
                'file' => 'Please upload a photo for this action.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $vendor, $actor, $actionKey, $notes, $file, $config) {
            if ($assignment->status === RecoveryAssignment::STATUS_ASSIGNED) {
                $assignment = $this->assignments->start($assignment, $actor);
            }

            if ($file && ($config['accepts_file'] ?? false)) {
                $this->storeProof($assignment, $vendor, $file, (string) ($config['file_label'] ?? 'Recovery photo'));
            }

            $label = (string) ($config['label'] ?? $actionKey);
            $noteText = $label.($notes !== '' ? ': '.$notes : '');

            if ($assignment->arrearCase) {
                $this->collectionActions->logForCase(
                    $assignment->arrearCase,
                    $actor,
                    (string) ($config['collection_type'] ?? 'other'),
                    '['.$vendor->name.'] '.$noteText,
                    $config['result'] ?? null,
                );
            }

            $assignment->update([
                'notes' => trim(($assignment->notes ? $assignment->notes."\n" : '')
                    .'['.now()->format('d M Y H:i').'] '.$noteText),
            ]);

            if ($config['completes'] ?? false) {
                return $this->assignments->complete(
                    $assignment->fresh(),
                    $actor,
                    (string) ($config['outcome'] ?? $actionKey),
                    $notes !== '' ? $notes : null,
                );
            }

            return $assignment->fresh();
        });
    }

    /** @return array<string, mixed>|null */
    private function actionConfig(string $partnerType, string $actionKey): ?array
    {
        $actions = $this->portalActions($partnerType);

        return $actions[$actionKey] ?? null;
    }

    private function storeProof(
        RecoveryAssignment $assignment,
        Vendor $vendor,
        UploadedFile $file,
        string $label,
    ): void {
        $task = $assignment->vendorTask;
        if (! $task) {
            throw ValidationException::withMessages([
                'file' => 'No linked task found for this recovery case.',
            ]);
        }

        $path = $file->store("vendor/{$vendor->id}/recovery", 'public');

        VendorDocument::create([
            'vendor_id'      => $vendor->id,
            'vendor_task_id' => $task->id,
            'label'          => $label,
            'file_path'      => $path,
            'mime'           => $file->getMimeType(),
            'size_bytes'     => $file->getSize(),
        ]);

        if (! $task->proof_path) {
            $task->update(['proof_path' => $path]);
        }
    }
}
