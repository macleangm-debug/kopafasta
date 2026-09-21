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
        'APP-IL-LQU6',
        'APP-IL-84SH',
        'APP-IL-ZR93',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly ProfileCompletionService $completion,
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

                $draft = $this->ensureResumeDraft($application->fresh(['customer', 'product']));

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

    private function ensureResumeDraft(LoanApplication $application): LoanApplicationDraft
    {
        $customer = $application->customer;
        $product = $application->product;
        if (! $customer || ! $product) {
            throw new \RuntimeException('Application '.$application->application_number.' is missing customer or product.');
        }

        $candidates = LoanApplicationDraft::query()
            ->where('customer_id', $customer->id)
            ->where('loan_product_id', $product->id)
            ->orderBy('id')
            ->get();

        $draft = $candidates->first(
            fn (LoanApplicationDraft $row) => $row->draft_reference === $application->application_number
        ) ?? $candidates->first();

        $feeStatus = (string) ($application->application_fee_status ?? '');
        $feePaid = in_array($feeStatus, ['paid', 'waived', 'charged'], true);
        $existingPayload = is_array($draft?->payload) ? $draft->payload : [];
        $existingForm = is_array($existingPayload['form'] ?? null) ? $existingPayload['form'] : [];
        $existingFee = is_array($existingPayload['application_fee'] ?? null) ? $existingPayload['application_fee'] : [];

        $payload = array_replace($existingPayload, [
            'application_started' => true,
            'draft_reference' => $application->application_number,
            'step_key' => $existingPayload['step_key'] ?? 'submit',
            'form' => array_replace($existingForm, [
                'loan_product_id' => $product->id,
                'requested_amount' => (float) $application->requested_amount,
                'requested_tenure_months' => (int) $application->requested_tenure_months,
                'purpose' => $application->purpose ?: ($existingForm['purpose'] ?? 'business'),
            ]),
            'application_fee' => $feePaid
                ? array_replace($existingFee, [
                    'status' => $feeStatus === 'waived' ? 'waived' : 'paid',
                    'reference' => $application->application_fee_reference ?: ($existingFee['reference'] ?? null),
                    'channel' => $application->application_fee_channel ?: ($existingFee['channel'] ?? null),
                    'amount' => (int) round((float) ($application->application_fee_amount ?? 0)),
                    'paid_at' => optional($application->application_fee_paid_at)?->toIso8601String()
                        ?: ($existingFee['paid_at'] ?? now()->toIso8601String()),
                    'draft_reference' => $application->application_number,
                ])
                : ($existingFee !== [] ? $existingFee : null),
        ]);

        if ($draft) {
            $draft->update([
                'phase' => 'application',
                'draft_reference' => $application->application_number,
                'payload' => $payload,
                'saved_at' => now(),
            ]);

            // Collapse competing same-product drafts into one resumable journey.
            LoanApplicationDraft::query()
                ->where('customer_id', $customer->id)
                ->where('loan_product_id', $product->id)
                ->where('id', '!=', $draft->id)
                ->delete();

            return $draft->fresh();
        }

        return LoanApplicationDraft::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'phase' => 'application',
            'step' => 0,
            'draft_reference' => $application->application_number,
            'saved_at' => now(),
            'payload' => $payload,
        ]);
    }
}
