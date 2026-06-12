<?php

namespace App\Services;

use App\Models\CustomerDocument;
use App\Models\LoanApplication;
use App\Models\LoanProductRequirement;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationDocumentReviewService
{
    public function __construct(private readonly AuditService $audit) {}

    public function verify(CustomerDocument $document, LoanApplication $application, User $user): CustomerDocument
    {
        $this->assertCanReview($document, $application, $user);

        $document->update([
            'status'      => 'verified',
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);

        $this->audit->log($user, 'admin.loan_applications.document_verified', $application, [], [
            'document_id'   => $document->id,
            'requirement_id'=> $document->loan_product_requirement_id,
        ]);

        return $document->fresh();
    }

    public function reject(CustomerDocument $document, LoanApplication $application, User $user, ?string $notes = null): CustomerDocument
    {
        $this->assertCanReview($document, $application, $user);

        $document->update([
            'status' => 'rejected',
            'notes'  => $notes ?: $document->notes,
        ]);

        $this->audit->log($user, 'admin.loan_applications.document_rejected', $application, [], [
            'document_id' => $document->id,
            'notes'       => $notes,
        ]);

        return $document->fresh();
    }

    /** @return array{title: string, items: list<string>} */
    public function guidanceForRequirement(LoanProductRequirement $requirement): array
    {
        $config = config('underwriting_document_guidance', []);
        $defaults = $config['defaults'] ?? ['title' => 'What to verify', 'items' => []];

        $keys = array_filter([
            $requirement->code ?? null,
            Str::slug((string) $requirement->name, '_'),
            Str::slug((string) ($requirement->document_type ?? ''), '_'),
        ]);

        foreach ($keys as $key) {
            if (! empty($config[$key])) {
                return $config[$key];
            }
        }

        foreach ($config as $key => $guidance) {
            if ($key === 'defaults' || ! is_array($guidance)) {
                continue;
            }
            if (Str::contains(Str::lower((string) $requirement->name), str_replace('_', ' ', $key))) {
                return $guidance;
            }
        }

        return $defaults;
    }

    /** @return array{title: string, items: list<string>} */
    public function guidanceForDocument(CustomerDocument $document): array
    {
        $config = config('underwriting_document_guidance', []);
        $defaults = $config['defaults'] ?? ['title' => 'What to verify', 'items' => []];

        $code = $document->documentType?->code ?? Str::slug((string) ($document->documentType?->name ?? ''), '_');

        if ($code && ! empty($config[$code])) {
            return $config[$code];
        }

        return $defaults;
    }

    private function assertCanReview(CustomerDocument $document, LoanApplication $application, User $user): void
    {
        if (! app(PermissionService::class)->has($user, 'applications.review')) {
            throw ValidationException::withMessages(['document' => 'You do not have permission to review documents.']);
        }

        abort_unless((int) $document->customer_id === (int) $application->customer_id, 404);
    }
}
