<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentReview;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Keeps Documents reviews and screening checklist in sync for file-backed items.
 */
class ChecklistDocumentBridge
{
    /**
     * @return array{
     *   status: string,
     *   label: string,
     *   verified: int,
     *   rejected: int,
     *   pending: int,
     *   total: int,
     *   fail_reason_code: ?string,
     *   fail_label: ?string,
     *   documents: list<array{id: int, name: string, status: string}>
     * }|null
     */
    public function statusForItem(
        LoanApplication $application,
        ?Customer $customer,
        string $fullKey,
    ): ?array {
        $bundleKey = config('checklist_document_bridge.item_bundles', [])[$fullKey] ?? null;
        if (! is_string($bundleKey) || ! $customer) {
            return null;
        }

        $docs = $this->documentsForBundle($application, $customer, $bundleKey);
        if ($docs->isEmpty()) {
            return [
                'status' => 'missing',
                'label' => 'No linked files in Documents yet',
                'verified' => 0,
                'rejected' => 0,
                'pending' => 0,
                'total' => 0,
                'fail_reason_code' => str_contains($fullKey, 'income') || str_contains($fullKey, 'bank_or_mobile')
                    ? 'statements_missing'
                    : (str_contains($fullKey, 'activity') ? 'docs_missing' : 'proof_missing'),
                'fail_label' => 'Upload / request the file in Documents',
                'documents' => [],
                'auto' => in_array($fullKey, config('checklist_document_bridge.auto_from_documents', []), true),
            ];
        }

        $reviews = app(ApplicationDocumentReviewService::class)->reviewsForApplication($application);
        $verified = 0;
        $rejected = 0;
        $pending = 0;
        $rows = [];
        $rejectReason = null;
        $rejectLabel = null;

        foreach ($docs as $doc) {
            /** @var CustomerDocument $doc */
            $status = $reviews->get($doc->id)?->status
                ?? app(ApplicationDocumentReviewService::class)->statusFor($application, $doc);
            if (in_array($status, ['verified', 'approved'], true)) {
                $verified++;
            } elseif ($status === 'rejected') {
                $rejected++;
                $review = $reviews->get($doc->id);
                if ($review && ! $rejectReason) {
                    $rejectReason = $this->mapDocFailToChecklist($fullKey, (string) ($review->fail_reason_code ?? ''));
                    $rejectLabel = $review->failReasonLabel();
                }
            } else {
                $pending++;
            }
            $rows[] = [
                'id' => (int) $doc->id,
                'name' => $doc->displayName(),
                'status' => (string) $status,
            ];
        }

        $status = match (true) {
            $rejected > 0 => 'rejected',
            $pending > 0 => 'pending',
            $verified > 0 && $verified === $docs->count() => 'verified',
            default => 'pending',
        };

        return [
            'status' => $status,
            'label' => match ($status) {
                'verified' => 'Reviewed in Documents · all clear',
                'rejected' => 'Failed in Documents · '.$verified.' ok / '.$rejected.' failed',
                'pending' => 'Documents · '.$pending.' still pending review',
                default => 'Documents status unknown',
            },
            'verified' => $verified,
            'rejected' => $rejected,
            'pending' => $pending,
            'total' => $docs->count(),
            'fail_reason_code' => $rejectReason,
            'fail_label' => $rejectLabel,
            'documents' => $rows,
            'auto' => in_array($fullKey, config('checklist_document_bridge.auto_from_documents', []), true),
        ];
    }

    /**
     * Auto verdict for document-driven checklist items.
     *
     * @return array{verdict: string, fail_reason_code?: string|null, source: string}|null
     */
    public function autoVerdict(LoanApplication $application, ?Customer $customer, string $fullKey): ?array
    {
        if (! in_array($fullKey, config('checklist_document_bridge.auto_from_documents', []), true)) {
            return null;
        }

        $status = $this->statusForItem($application, $customer, $fullKey);
        if ($status === null) {
            return null;
        }

        return match ($status['status']) {
            'verified' => ['verdict' => 'pass', 'source' => 'documents'],
            'rejected' => [
                'verdict' => 'fail',
                'fail_reason_code' => $status['fail_reason_code']
                    ?? ($fullKey === 'documents.falsified_docs' ? 'falsified_documentation' : 'poor_quality'),
                'source' => 'documents',
            ],
            'missing' => [
                'verdict' => 'fail',
                'fail_reason_code' => $status['fail_reason_code'] ?? 'proof_missing',
                'source' => 'documents',
            ],
            default => ['verdict' => '', 'source' => 'system_skip'],
        };
    }

    /** After a Documents review, refresh linked checklist entries for that subject. */
    public function syncChecklistAfterDocumentReview(
        LoanApplication $application,
        CustomerDocument $document,
        LoanApplicationDocumentReview $review,
        \App\Models\User $actor,
    ): void {
        $customer = $document->customer;
        if (! $customer) {
            return;
        }

        $codes = [(string) ($document->documentType?->code ?? '')];
        $touchedKeys = [];
        foreach (config('checklist_document_bridge.item_bundles', []) as $fullKey => $bundleKey) {
            if ($bundleKey === 'profile_all') {
                $touchedKeys[] = $fullKey;
                continue;
            }
            $bundleCodes = config('checklist_document_bridge.bundles.'.$bundleKey, []);
            if (array_intersect($codes, $bundleCodes) !== []) {
                $touchedKeys[] = $fullKey;
            }
        }

        if ($touchedKeys === []) {
            return;
        }

        $subjectKind = (string) ($review->subject_kind ?? 'borrower');
        $person = in_array($subjectKind, ['borrower', 'guarantor', 'member'], true) ? $subjectKind : 'borrower';
        $guarantorLinkId = null;
        $memberId = $review->loan_group_member_id ? (int) $review->loan_group_member_id : null;

        if ($person === 'guarantor' && $review->subject_customer_id) {
            $application->loadMissing('customerGuarantors');
            $link = $application->customerGuarantors
                ->firstWhere('guarantor_id', (int) $review->subject_customer_id);
            $guarantorLinkId = $link?->id;
        }

        app(ScreeningChecklistService::class)->refreshDocumentLinkedVerdicts(
            $application,
            $actor,
            $person,
            $guarantorLinkId,
            $memberId,
            $touchedKeys,
        );
    }

    /** @return Collection<int, CustomerDocument> */
    private function documentsForBundle(LoanApplication $application, Customer $customer, string $bundleKey): Collection
    {
        if ($bundleKey === 'profile_all') {
            return CustomerDocument::query()
                ->with(['documentType', 'documentRequest'])
                ->where('customer_id', $customer->id)
                ->where(function ($q) use ($application) {
                    $q->whereNull('loan_application_id')
                        ->orWhere('loan_application_id', $application->id);
                })
                ->whereNotNull('file_path')
                ->whereNotIn('status', ['replaced', 'archived'])
                ->latest('id')
                ->get()
                ->unique(fn (CustomerDocument $doc) => (string) ($doc->document_type_id ?? $doc->id))
                ->values();
        }

        $codes = config('checklist_document_bridge.bundles.'.$bundleKey, []);
        if ($codes === []) {
            return collect();
        }

        return app(ProfileDocumentService::class)
            ->latestByCodes($customer, $codes)
            ->filter(fn ($doc) => $doc && filled($doc->file_path ?? null))
            ->values();
    }

    private function mapDocFailToChecklist(string $fullKey, string $docFailCode): string
    {
        if ($fullKey === 'documents.falsified_docs') {
            return 'falsified_documentation';
        }
        if ($fullKey === 'documents.doc_authenticity') {
            return match ($docFailCode) {
                'altered' => 'falsified',
                'name_mismatch' => 'inconsistent',
                default => 'inconsistent',
            };
        }
        if (str_starts_with($fullKey, 'identity.')) {
            return match ($docFailCode) {
                'altered' => 'suspected_tamper',
                default => 'poor_quality',
            };
        }
        if (str_starts_with($fullKey, 'residence.')) {
            return match ($docFailCode) {
                'expired' => 'proof_invalid',
                'wrong_document' => 'proof_invalid',
                default => 'proof_invalid',
            };
        }
        if (str_contains($fullKey, 'activity')) {
            return 'inconsistent';
        }

        return 'custom';
    }

    /**
     * Checklist rows that a document type feeds (for Documents UI badges).
     *
     * @return list<array{key: string, label: string, auto: bool}>
     */
    public function checklistLinksForDocumentCode(string $documentTypeCode): array
    {
        $links = [];
        $catalog = config('screening_checklist', []);
        foreach (config('checklist_document_bridge.item_bundles', []) as $fullKey => $bundleKey) {
            $codes = $bundleKey === 'profile_all'
                ? null
                : (array) config('checklist_document_bridge.bundles.'.$bundleKey, []);
            if ($codes !== null && ! in_array($documentTypeCode, $codes, true)) {
                continue;
            }
            [$groupKey, $itemKey] = array_pad(explode('.', (string) $fullKey, 2), 2, '');
            $label = (string) (data_get($catalog, "{$groupKey}.items.{$itemKey}.label") ?: $fullKey);
            $links[] = [
                'key' => (string) $fullKey,
                'label' => $label,
                'auto' => in_array($fullKey, config('checklist_document_bridge.auto_from_documents', []), true),
            ];
        }

        return $links;
    }

    /**
     * After checklist save: if reverse-verify keys for a bundle are all Pass,
     * mark pending Documents in that bundle verified for this application.
     *
     * @param  array<string, mixed>  $checklistItems
     */
    public function syncDocumentsAfterChecklistPass(
        LoanApplication $application,
        User $actor,
        ?Customer $customer,
        array $checklistItems,
        array $subject = [],
    ): int {
        if (! $customer) {
            return 0;
        }

        $verified = 0;
        $reviewService = app(ApplicationDocumentReviewService::class);

        foreach (config('checklist_document_bridge.reverse_auto_verify', []) as $bundleKey => $requiredKeys) {
            $requiredKeys = array_values(array_filter((array) $requiredKeys, 'is_string'));
            if ($requiredKeys === []) {
                continue;
            }

            $allPass = true;
            foreach ($requiredKeys as $fullKey) {
                $row = (array) ($checklistItems[$fullKey] ?? []);
                $verdict = strtolower(trim((string) ($row['verdict'] ?? '')));
                if ($verdict !== 'pass') {
                    $allPass = false;
                    break;
                }
            }
            if (! $allPass) {
                continue;
            }

            foreach ($this->documentsForBundle($application, $customer, (string) $bundleKey) as $doc) {
                $status = $reviewService->statusFor($application, $doc);
                if (in_array($status, ['verified', 'approved', 'rejected'], true)) {
                    continue;
                }

                // Quiet path: verify without pushing checklist again (already Pass).
                $reviewService->verifyQuiet($doc, $application, $actor, $subject);
                $verified++;
            }
        }

        return $verified;
    }
}
