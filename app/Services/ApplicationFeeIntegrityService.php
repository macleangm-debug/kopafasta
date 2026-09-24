<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

/**
 * Read + repair verified application fees against their original obligation.
 * Never matches by name, amount, or "most recent" application.
 */
class ApplicationFeeIntegrityService
{
    public function __construct(
        private ApplicationFeePaymentService $fees,
        private LoanApplicationDraftService $drafts,
    ) {}

    /**
     * @return array{verified: int, class_a: list<array<string, mixed>>, class_b: list<array<string, mixed>>, class_c: list<array<string, mixed>>, opposite: list<array<string, mixed>>}
     */
    public function audit(): array
    {
        $classA = [];
        $classB = [];
        $classC = [];

        $payments = CustomerPayment::query()
            ->where('payment_type', 'application_fee')
            ->whereIn('status', ['paid', 'verified'])
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $row = $this->classify($payment);
            match ($row['class']) {
                'A' => $classA[] = $row,
                'B' => $classB[] = $row,
                'C' => $classC[] = $row,
                default => null,
            };
        }

        return [
            'verified' => $payments->count(),
            'class_a' => $classA,
            'class_b' => $classB,
            'class_c' => $classC,
            'opposite' => $this->oppositePaidWithoutPayment(),
        ];
    }

    /** @return array<string, mixed> */
    public function classify(CustomerPayment $payment): array
    {
        $draftRef = $this->draftReference($payment);
        $customer = $payment->customer;
        $product = $payment->loan_product_id ? LoanProduct::query()->find($payment->loan_product_id) : null;
        $draft = $draftRef !== ''
            ? LoanApplicationDraft::query()->where('draft_reference', $draftRef)->first()
            : null;
        $application = $this->applicationForPayment($payment);

        $base = [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'customer_id' => $payment->customer_id,
            'loan_product_id' => $payment->loan_product_id,
            'draft_reference' => $draftRef,
            'draft_id' => $draft?->id,
            'application_id' => $application?->id,
            'application_number' => $application?->application_number,
            'application_status' => $application?->status,
            'application_fee_status' => $application?->application_fee_status,
            'source_type' => $payment->source_type,
            'source_id' => $payment->source_id,
        ];

        if ($draftRef === '' && ! $application && ! $payment->source_id) {
            return $base + ['class' => 'C', 'reason' => 'no_obligation_anchor'];
        }

        $payload = $draft
            ? array_merge((array) $draft->payload, ['draft_reference' => $draft->draft_reference])
            : ['draft_reference' => $draftRef];
        $obligation = ($customer && $product)
            ? $this->fees->obligation($customer, $product, $payload, $application)
            : ['status' => 'unknown'];

        $draftFee = (string) data_get($draft?->payload, 'application_fee.status');
        $appFee = (string) ($application?->application_fee_status ?? '');
        $livePending = $draft && ! in_array($draftFee, ['paid', 'waived'], true);
        $appPending = $application
            && ! in_array($application->status, LoanApplication::CLOSED_STATUSES, true)
            && ! in_array($appFee, ['paid', 'waived', 'charged'], true);
        $withdrawnPaid = $application
            && $application->status === 'withdrawn'
            && in_array($appFee, ['paid', 'waived', 'charged'], true);

        if ($livePending || $appPending) {
            return $base + [
                'class' => $draft || $application ? 'A' : 'C',
                'reason' => $livePending ? 'draft_still_pending' : 'application_still_pending',
                'obligation' => $obligation['status'] ?? null,
            ];
        }

        if ($withdrawnPaid && (string) ($application->current_stage ?? '') === 'awaiting_guarantor') {
            return $base + [
                'class' => 'B',
                'reason' => 'withdrawn_after_paid_awaiting_guarantor',
                'obligation' => $obligation['status'] ?? null,
            ];
        }

        return $base + ['class' => 'ok', 'reason' => 'satisfied', 'obligation' => $obligation['status'] ?? null];
    }

    /**
     * Idempotent repair for one verified payment using only its stored obligation anchors.
     *
     * @return array<string, mixed>
     */
    public function repair(CustomerPayment $payment, bool $apply = false): array
    {
        $classified = $this->classify($payment);
        if (($classified['class'] ?? '') === 'ok') {
            return $classified + ['repaired' => false];
        }
        if (($classified['class'] ?? '') === 'C') {
            return $classified + ['repaired' => false, 'skipped' => 'ambiguous'];
        }

        $plan = [];
        $application = $this->applicationForPayment($payment);
        $draftRef = $this->draftReference($payment);
        $customer = $payment->customer;
        $product = $payment->loan_product_id ? LoanProduct::query()->find($payment->loan_product_id) : null;

        if ($application && ($payment->source_type !== LoanApplication::class || (int) $payment->source_id !== (int) $application->id)) {
            $plan[] = 'bind_source';
        }

        if ($application
            && $application->status === 'withdrawn'
            && in_array((string) $application->application_fee_status, ['paid', 'waived', 'charged'], true)
            && (string) ($application->current_stage ?? '') === 'awaiting_guarantor'
        ) {
            $plan[] = 'restore_withdrawn';
        }

        if ($customer && $product) {
            $restart = $this->laterUnpaidSameProductDraft($customer, $product, $draftRef);
            if ($restart) {
                $plan[] = 'discard_restart_draft:'.$restart->draft_reference;
            } else {
                $remaining = $this->drafts->find($customer, $product->id);
                if ($remaining && (string) $remaining->draft_reference === $draftRef) {
                    $plan[] = 'sync_draft';
                }
            }
        }

        if (! $apply) {
            return $classified + ['repaired' => false, 'plan' => $plan];
        }

        return DB::transaction(function () use ($payment, $application, $customer, $product, $draftRef, $plan, $classified) {
            $payment = $payment->fresh();
            if ($application && in_array('bind_source', $plan, true)) {
                $payment->update([
                    'source_type' => LoanApplication::class,
                    'source_id' => $application->id,
                ]);
            }

            if ($application && in_array('restore_withdrawn', $plan, true)) {
                $application->update([
                    'status' => 'awaiting_guarantor',
                    'current_stage' => 'awaiting_guarantor',
                    'rejection_reason' => null,
                ]);
            }

            if ($customer && $product) {
                $restart = $this->laterUnpaidSameProductDraft($customer, $product, $draftRef);
                if ($restart) {
                    $this->fees->abandonOpenFeePaymentsForDraft(
                        $customer,
                        (int) $product->id,
                        (string) $restart->draft_reference,
                    );
                    $this->drafts->discard($customer, (int) $product->id);
                }
            }

            if ($customer && $product && in_array('sync_draft', $plan, true)) {
                $this->fees->syncDraftFromVerifiedPayment($customer, $product);
            }

            return $this->classify($payment->fresh()) + ['repaired' => true, 'plan' => $plan];
        });
    }

    /** @return list<array<string, mixed>> */
    private function oppositePaidWithoutPayment(): array
    {
        return LoanApplication::query()
            ->whereIn('application_fee_status', ['paid', 'waived', 'charged'])
            ->get()
            ->filter(function (LoanApplication $application) {
                $ref = trim((string) ($application->application_fee_reference ?? ''));
                if ($ref === '') {
                    return true;
                }

                return ! CustomerPayment::query()
                    ->where('reference', $ref)
                    ->whereIn('status', ['paid', 'verified'])
                    ->exists();
            })
            ->map(fn (LoanApplication $application) => [
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'customer_id' => $application->customer_id,
                'fee_reference' => $application->application_fee_reference,
                'status' => $application->status,
            ])
            ->values()
            ->all();
    }

    private function draftReference(CustomerPayment $payment): string
    {
        return trim((string) data_get($payment->provider_meta, 'apply_context.draft_reference'));
    }

    private function applicationForPayment(CustomerPayment $payment): ?LoanApplication
    {
        if ($payment->source_type === LoanApplication::class && (int) $payment->source_id > 0) {
            return LoanApplication::query()->find($payment->source_id);
        }

        $ref = $this->draftReference($payment);
        if ($ref === '') {
            return null;
        }

        return LoanApplication::query()->where('application_number', $ref)->first();
    }

    private function laterUnpaidSameProductDraft(Customer $customer, LoanProduct $product, string $paidDraftRef): ?LoanApplicationDraft
    {
        $draft = $this->drafts->find($customer, $product->id);
        if (! $draft || (string) $draft->draft_reference === $paidDraftRef) {
            return null;
        }

        $fee = (string) data_get($draft->payload, 'application_fee.status');

        return in_array($fee, ['paid', 'waived'], true) ? null : $draft;
    }
}
