<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use Illuminate\Support\Facades\DB;

/**
 * Moves historically premature loan_applications (borrower profile incomplete)
 * back to the canonical Incomplete / draft journey without deleting rows,
 * payments, documents, guarantors, or history.
 */
class PrematureApplicationReconciliationService
{
    /** @var list<string> */
    public const TARGET_NUMBERS = [
        'APP-IL-84SH',
        'APP-IL-ZR93',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly ProfileCompletionService $completion,
        private readonly LoanApplicationDraftService $drafts,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function plan(array $applicationNumbers = self::TARGET_NUMBERS): array
    {
        $plans = [];
        foreach ($applicationNumbers as $number) {
            $application = LoanApplication::query()
                ->with(['customer', 'product'])
                ->where('application_number', $number)
                ->first();
            if (! $application) {
                $plans[] = ['application_number' => $number, 'action' => 'missing'];

                continue;
            }

            $customer = $application->customer;
            $summary = $customer
                ? $this->completion->completionSummary($customer)
                : ['percent' => 0, 'actionable' => []];
            $drafts = LoanApplicationDraft::query()
                ->where('customer_id', $application->customer_id)
                ->where('loan_product_id', $application->loan_product_id)
                ->orderBy('id')
                ->get();
            $draft = $drafts->first(
                fn (LoanApplicationDraft $row) => $row->draft_reference === $application->application_number
            ) ?? $drafts->first();

            $plans[] = [
                'application_number' => $number,
                'application_id' => $application->id,
                'customer_id' => $application->customer_id,
                'action' => $application->isPreSubmit() ? 'already_pre_submit' : 'reclassify_to_draft',
                'from_status' => $application->status,
                'from_stage' => $application->current_stage,
                'to_status' => 'draft',
                'to_stage' => 'draft',
                'profile_percent' => (int) ($summary['percent'] ?? 0),
                'missing' => collect($summary['actionable'] ?? [])->pluck('label')->filter()->values()->all(),
                'draft_id' => $draft?->id,
                'competing_draft_ids' => $drafts->where('id', '!=', $draft?->id)->pluck('id')->values()->all(),
                'draft_action' => $draft
                    ? ($draft->draft_reference === $application->application_number
                        ? ($drafts->count() > 1 ? 'reuse_existing_draft_collapse_duplicates' : 'reuse_existing_draft')
                        : ($drafts->count() > 1 ? 'align_existing_draft_reference_collapse_duplicates' : 'align_existing_draft_reference'))
                    : 'create_resume_draft',
                'fee_status' => $application->application_fee_status,
                'fee_reference' => $application->application_fee_reference,
            ];
        }

        return $plans;
    }

    /**
     * @param  list<string>  $applicationNumbers
     * @return list<array<string, mixed>>
     */
    public function reconcile(array $applicationNumbers = self::TARGET_NUMBERS): array
    {
        $results = [];

        foreach ($applicationNumbers as $number) {
            $results[] = DB::transaction(function () use ($number) {
                $application = LoanApplication::query()
                    ->with(['customer', 'product'])
                    ->where('application_number', $number)
                    ->lockForUpdate()
                    ->first();

                if (! $application) {
                    return ['application_number' => $number, 'ok' => false, 'reason' => 'missing'];
                }

                $before = [
                    'status' => $application->status,
                    'current_stage' => $application->current_stage,
                    'guarantor_deadline_at' => optional($application->guarantor_deadline_at)?->toIso8601String(),
                ];

                if (! $application->isPreSubmit()) {
                    $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
                    $payload['premature_submission_return'] = [
                        'returned_at' => now()->toIso8601String(),
                        'from_status' => $application->status,
                        'from_stage' => $application->current_stage,
                        'reason' => 'Borrower profile incomplete under current KYC requirements',
                    ];

                    $application->update([
                        'status' => 'draft',
                        'current_stage' => 'draft',
                        'guarantor_deadline_at' => null,
                        'screening_payload' => $payload,
                    ]);
                }

                $draft = $this->drafts->ensureResumeDraftForApplication($application->fresh(['customer', 'product']));

                $this->audit->log(null, 'application.returned_to_incomplete', $application, $before, [
                    'status' => 'draft',
                    'current_stage' => 'draft',
                    'actor' => 'system',
                    'reason' => 'Borrower profile incomplete — returned to Applications in Progress',
                    'draft_id' => $draft->id,
                    'application_number' => $application->application_number,
                ]);

                $summary = $application->customer
                    ? $this->completion->completionSummary($application->customer)
                    : ['percent' => 0, 'actionable' => []];

                return [
                    'application_number' => $number,
                    'ok' => true,
                    'application_id' => $application->id,
                    'draft_id' => $draft->id,
                    'status' => $application->fresh()->status,
                    'stage' => $application->fresh()->current_stage,
                    'profile_percent' => (int) ($summary['percent'] ?? 0),
                    'missing' => collect($summary['actionable'] ?? [])->pluck('label')->filter()->values()->all(),
                    'fee_status' => $application->application_fee_status,
                ];
            });
        }

        return $results;
    }
}
