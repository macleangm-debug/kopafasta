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

        $this->notifyBorrower($request);

        return $request;
    }

    public function notifyBorrower(LoanApplicationDocumentRequest $request): void
    {
        $application = $request->application()->with('customer')->first();
        $customer = $application?->customer;

        if (! $customer) {
            return;
        }

        $uploadUrl = route('site.borrower.application', $application);

        $this->notifier->notifyCustomer($customer, 'application_document_request', [
            'name'                => $customer->first_name ?? 'Customer',
            'application_number'  => $application->application_number,
            'label'               => $request->label,
            'instructions'        => $request->instructions ?: 'Please upload the requested item.',
            'due_date'            => optional($request->due_at)->format('d M Y') ?? 'as soon as possible',
            'upload_url'          => $uploadUrl,
            '_fallback_body'      => "Hi {$customer->first_name}, underwriting needs \"{$request->label}\" for application {$application->application_number}. Upload at {$uploadUrl} — Kopa Fasta",
            '_fallback_subject'   => 'Document requested for your loan application',
        ]);
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
                'customer_id'                           => $customer->id,
                'loan_application_id'                   => $application->id,
                'loan_application_document_request_id'  => $request->id,
                'document_type_id'                      => null,
                'file_path'                             => $path,
                'status'                                => 'pending_review',
            ]));
        }

        $request->update([
            'status'      => 'uploaded',
            'admin_notes' => null,
        ]);

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
            $this->notifier->notifyCustomer($customer, 'application_document_request', [
                'name'               => $customer->first_name ?? 'Customer',
                'application_number' => $application->application_number,
                'label'              => $request->label,
                'instructions'       => "Please re-upload. Reason: {$notes}",
                'due_date'           => optional($request->due_at)->format('d M Y') ?? 'as soon as possible',
                'upload_url'         => route('site.borrower.application', $application),
                '_fallback_body'     => "Hi {$customer->first_name}, your upload for \"{$request->label}\" was rejected. {$notes}. Re-upload at ".route('site.borrower.application', $application).' — Kopa Fasta',
                '_fallback_subject'  => 'Document upload rejected — action required',
            ]);
        }

        return $request->fresh();
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
