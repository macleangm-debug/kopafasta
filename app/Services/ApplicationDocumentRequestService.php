<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ApplicationDocumentRequestService
{
    /** @var list<string> */
    public const PRESET_LABELS = [
        'Updated bank statement (last 6 months)',
        'Mobile money statement (last 6 months)',
        'Guarantor residence letter',
        'Additional income proof',
        'Business registration documents',
        'Business photos',
        'Updated employment contract',
        'Latest salary slip',
    ];

    public function __construct(private readonly NotificationService $notifier) {}

    public function create(
        LoanApplication $application,
        User $requester,
        string $label,
        ?string $instructions = null,
        ?\DateTimeInterface $dueAt = null,
        string $type = 'document',
    ): LoanApplicationDocumentRequest {
        $request = LoanApplicationDocumentRequest::create([
            'loan_application_id' => $application->id,
            'requested_by'        => $requester->id,
            'type'                => $type,
            'label'               => $label,
            'instructions'        => $instructions,
            'status'              => 'pending',
            'due_at'              => $dueAt,
        ]);

        $this->syncApplicationStatus($application->fresh());
        $this->notifyBorrower($request);

        return $request;
    }

    /**
     * @param  list<string>  $labels
     * @return Collection<int, LoanApplicationDocumentRequest>
     */
    public function createMany(
        LoanApplication $application,
        User $requester,
        array $labels,
        ?string $instructions = null,
        ?\DateTimeInterface $dueAt = null,
        string $type = 'document',
    ): Collection {
        $labels = collect($labels)
            ->map(fn ($label) => trim((string) $label))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $created = collect();

        foreach ($labels as $label) {
            $request = LoanApplicationDocumentRequest::create([
                'loan_application_id' => $application->id,
                'requested_by'        => $requester->id,
                'type'                => $type,
                'label'               => $label,
                'instructions'        => $instructions,
                'status'              => 'pending',
                'due_at'              => $dueAt,
            ]);
            $created->push($request);
            $this->notifyBorrower($request, inApp: false);
        }

        $application = $application->fresh();
        $this->syncApplicationStatus($application);

        if ($created->isNotEmpty()) {
            $this->notifyBorrowerBatch($application->loadMissing('customer'), $created);
        }

        return $created;
    }

    public function notifyBorrower(LoanApplicationDocumentRequest $request, bool $inApp = true): void
    {
        $application = $request->application()->with('customer')->first();
        $customer = $application?->customer;

        if (! $customer) {
            return;
        }

        $uploadUrl = route('site.borrower.application', $application);
        $instructions = $request->instructions ?: 'Please upload the requested item.';

        $this->notifier->notifyCustomer($customer, 'application_document_request', [
            'name'               => $customer->first_name ?? 'Customer',
            'application_number' => $application->application_number,
            'label'              => $request->label,
            'instructions'       => $instructions,
            'due_date'           => optional($request->due_at)->format('d M Y') ?? 'as soon as possible',
            'upload_url'         => $uploadUrl,
            '_fallback_body'     => "Hi {$customer->first_name}, underwriting needs \"{$request->label}\" for application {$application->application_number}. Log in to KopaFasta to upload.",
            '_fallback_subject'  => 'Document requested for your loan application',
        ]);

        if ($inApp) {
            $this->notifier->notifyInApp(
                $customer,
                "Application {$application->application_number}: please upload {$request->label}.",
                'document_request',
                'application_document_request',
                'Additional documents required',
                $uploadUrl,
                'View application',
            );
        }
    }

    /** @param  Collection<int, LoanApplicationDocumentRequest>  $requests */
    private function notifyBorrowerBatch(LoanApplication $application, Collection $requests): void
    {
        $customer = $application->customer;

        if (! $customer) {
            return;
        }

        $uploadUrl = route('site.borrower.application', $application);
        $count = $requests->count();
        $labels = $requests->pluck('label')->take(3)->implode(', ');
        $suffix = $count > 3 ? '…' : '';

        $this->notifier->notifyInApp(
            $customer,
            "Application {$application->application_number}: {$count} documents requested ({$labels}{$suffix}).",
            'document_request',
            'application_document_request',
            'Additional documents required',
            $uploadUrl,
            'View application',
        );
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function recordUploads(
        LoanApplicationDocumentRequest $request,
        Customer $customer,
        array $files,
    ): Collection {
        $application = $request->application;

        $stored = collect();

        foreach ($files as $file) {
            $path = $file->store(
                "borrower/{$customer->id}/applications/{$application->id}/requests/{$request->id}",
                'public'
            );

            $stored->push(CustomerDocument::create([
                'customer_id'                          => $customer->id,
                'loan_application_id'                  => $application->id,
                'loan_application_document_request_id' => $request->id,
                'document_type_id'                     => null,
                'file_path'                            => $path,
                'status'                               => 'pending_review',
            ]));
        }

        $request->update([
            'status'      => 'uploaded',
            'admin_notes' => null,
        ]);

        $this->syncApplicationStatus($application->fresh());

        return $stored;
    }

    public function recordClarification(
        LoanApplicationDocumentRequest $request,
        string $response,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'borrower_response' => $response,
            'status'            => 'uploaded',
            'admin_notes'       => null,
        ]);

        $this->syncApplicationStatus($request->application->fresh());

        return $request->fresh();
    }

    public function markSatisfied(
        LoanApplicationDocumentRequest $request,
        User $admin,
        ?string $notes = null,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'status'       => 'satisfied',
            'satisfied_by' => $admin->id,
            'satisfied_at' => now(),
            'admin_notes'  => $notes,
        ]);

        $request->uploads()->where('status', 'pending_review')->update([
            'status'      => 'verified',
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $this->syncApplicationStatus($request->application->fresh());

        return $request->fresh();
    }

    public function reject(
        LoanApplicationDocumentRequest $request,
        User $admin,
        string $notes,
    ): LoanApplicationDocumentRequest {
        $request->update([
            'status'      => 'rejected',
            'admin_notes' => $notes,
        ]);

        $request->uploads()->where('status', 'pending_review')->update([
            'status' => 'rejected',
            'notes'  => $notes,
        ]);

        $application = $request->application()->with('customer')->first();
        $customer = $application?->customer;

        if ($customer) {
            $uploadUrl = route('site.borrower.application', $application);

            $this->notifier->notifyCustomer($customer, 'application_document_request', [
                'name'               => $customer->first_name ?? 'Customer',
                'application_number' => $application->application_number,
                'label'              => $request->label,
                'instructions'       => "Please re-upload. Reason: {$notes}",
                'due_date'           => optional($request->due_at)->format('d M Y') ?? 'as soon as possible',
                'upload_url'         => $uploadUrl,
                '_fallback_body'     => "Hi {$customer->first_name}, your upload for \"{$request->label}\" was rejected. {$notes}. Log in to KopaFasta to re-upload.",
                '_fallback_subject'  => 'Document upload rejected — action required',
            ]);

            $this->notifier->notifyInApp(
                $customer,
                "Application {$application->application_number}: please re-upload {$request->label}. {$notes}",
                'document_request',
                'application_document_request',
                'Document upload rejected',
                $uploadUrl,
                'View application',
            );
        }

        $this->syncApplicationStatus($application->fresh());

        return $request->fresh();
    }

    public function syncApplicationStatus(LoanApplication $application): void
    {
        if (in_array($application->status, ['rejected', 'approved', 'disbursed'], true)) {
            return;
        }

        $needsAction = $application->documentRequests()
            ->whereIn('status', ['pending', 'rejected'])
            ->exists();

        if ($needsAction) {
            if ($application->status !== 'pending_documents') {
                $application->update(['status' => 'pending_documents']);
            }

            return;
        }

        if ($application->status !== 'pending_documents') {
            return;
        }

        $status = match ($application->current_stage) {
            'credit_appraisal', 'pre_approval', 'approval', 'disbursement' => 'under_review',
            'screening' => 'submitted',
            default     => 'submitted',
        };

        $application->update(['status' => $status]);
    }

    public function openRequestsForCustomer(Customer $customer): Collection
    {
        return LoanApplicationDocumentRequest::query()
            ->whereHas('application', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereIn('status', ['pending', 'rejected'])
            ->with(['application.product'])
            ->latest()
            ->get();
    }

    public function pendingReviewCount(): int
    {
        return LoanApplicationDocumentRequest::where('status', 'uploaded')->count();
    }
}
