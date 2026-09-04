<?php

namespace App\Services\Staging;

use App\Models\CustomerPayment;
use App\Services\CollateralSecureService;
use App\Services\CustomerPaymentService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staging PSP adapter. Exercises the same verify/reject/reconcile path as PayIn.
 * Impossible to construct a live-money collection.
 */
class StagingPaymentSimulator
{
    public function __construct(
        private readonly StagingPaymentsService $staging,
        private readonly CustomerPaymentService $payments,
    ) {}

    public function initiate(CustomerPayment $payment, string $phone, ?string $operator = null): CustomerPayment
    {
        $this->staging->assertStaging();
        if (! $this->staging->isSimulator()) {
            throw ValidationException::withMessages([
                'payment_method' => [__('borrower.payments.aggregator_required')],
            ]);
        }

        $attempt = (int) data_get($payment->provider_meta, 'collect_attempt', 0) + 1;
        $requestRef = 'stg_sim_'.Str::lower((string) Str::ulid());
        $meta = array_merge((array) ($payment->provider_meta ?? []), [
            'awaiting_collection' => false,
            'simulator' => true,
            'initiated_at' => now()->toIso8601String(),
            'phone' => $phone,
            'attempted_phone' => $phone,
            'requested_operator' => $operator,
            'collect_attempt' => $attempt,
            'message' => __('borrower.payment_waiting.simulator_initiated'),
        ]);

        $payment->update([
            'status' => 'processing',
            'provider' => StagingPaymentsService::PROVIDER,
            'provider_ref' => $requestRef,
            'mobile_number' => $phone,
            'provider_meta' => $meta,
            'payment_instructions' => trim(($payment->payment_instructions ?? '')."\n".__('borrower.payment_waiting.simulator_initiated')),
        ]);

        return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
    }

    public function applyOutcome(CustomerPayment $payment, string $outcome): CustomerPayment
    {
        $this->staging->assertStaging();
        if (! $this->staging->isSimulator()) {
            abort(404);
        }

        $outcome = $this->normalizeOutcome($outcome);
        if (! $this->staging->allows($outcome)) {
            throw ValidationException::withMessages([
                'outcome' => [__('borrower.payment_waiting.simulator_outcome_disabled')],
            ]);
        }

        $payment = $payment->fresh();
        if ($payment->provider !== StagingPaymentsService::PROVIDER) {
            throw ValidationException::withMessages([
                'payment_method' => [__('borrower.payment_waiting.cannot_retry')],
            ]);
        }

        $payload = [
            'event' => 'staging.'.$outcome,
            'status' => $outcome,
            'request_ref' => $payment->provider_ref,
            'external_ref' => $payment->reference,
            'simulator' => true,
            'at' => now()->toIso8601String(),
        ];

        $meta = array_merge((array) ($payment->provider_meta ?? []), [
            'last_event' => $payload['event'],
            'last_payload' => $payload,
            'updated_at' => $payload['at'],
        ]);
        $payment->update(['provider_meta' => $meta]);
        $payment = $payment->fresh();

        return match ($outcome) {
            'success' => $this->succeed($payment),
            'pending' => $this->keepPending($payment),
            'failed', 'cancelled' => $this->fail($payment, $outcome),
            'reversed' => $this->reverse($payment),
            default => $payment,
        };
    }

    private function succeed(CustomerPayment $payment): CustomerPayment
    {
        if ($payment->isVerified()) {
            return $payment;
        }

        if (! $payment->isPending()) {
            throw ValidationException::withMessages([
                'outcome' => [__('borrower.payment_waiting.cannot_retry')],
            ]);
        }

        $verified = $this->payments->verify($payment->fresh(), null, 'Staging simulator: success');
        $this->latchCollateralIfNeeded($verified);

        return $verified->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
    }

    private function keepPending(CustomerPayment $payment): CustomerPayment
    {
        if ($payment->status !== 'processing') {
            $meta = array_merge((array) ($payment->provider_meta ?? []), [
                'simulator_pending_at' => now()->toIso8601String(),
            ]);
            $payment->update([
                'status' => 'processing',
                'provider_meta' => $meta,
            ]);
        }

        return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
    }

    private function fail(CustomerPayment $payment, string $outcome): CustomerPayment
    {
        if ($payment->isVerified()) {
            throw ValidationException::withMessages([
                'outcome' => [__('borrower.payment_waiting.cannot_retry')],
            ]);
        }

        if (! $payment->isPending()) {
            return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
        }

        $notes = 'Staging simulator: '.$outcome;
        $returned = $this->payments->returnToPaymentGate($payment);
        $meta = array_merge((array) ($returned->provider_meta ?? []), [
            'last_collect_error' => __('borrower.payment_waiting.simulator_failed', ['outcome' => $outcome]),
            'last_collect_error_at' => now()->toIso8601String(),
            'simulator_outcome' => $outcome,
        ]);
        $returned->update([
            'provider_meta' => $meta,
            'verification_notes' => trim(($returned->verification_notes ?? '')."\n".$notes),
        ]);

        return $returned->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
    }

    private function reverse(CustomerPayment $payment): CustomerPayment
    {
        if ($payment->status === 'reversed') {
            return $payment;
        }

        if (! $payment->isVerified()) {
            return $this->fail($payment, 'cancelled');
        }

        $payment->update([
            'status' => 'reversed',
            'verification_notes' => trim(($payment->verification_notes ?? '')."\nStaging simulator: reversed"),
            'provider_meta' => array_merge((array) ($payment->provider_meta ?? []), [
                'simulator_reversed_at' => now()->toIso8601String(),
            ]),
        ]);

        return $payment->fresh(['customer', 'bankAccount', 'mobileMoneyAccount']);
    }

    private function latchCollateralIfNeeded(CustomerPayment $payment): void
    {
        $fresh = $payment->fresh();
        if (! $fresh
            || $fresh->source_type !== \App\Models\LoanApplication::class
            || ! $fresh->source_id) {
            return;
        }

        $application = \App\Models\LoanApplication::query()->find($fresh->source_id);
        if (! $application) {
            return;
        }

        $secure = app(CollateralSecureService::class);
        $state = $secure->state($application);
        if ($fresh->payment_type === 'application_fee'
            && ($state['status'] ?? '') === CollateralSecureService::STATUS_AWAITING_FEE) {
            $secure->markFeePaid($application);
        }
        if ($fresh->payment_type === 'valuation_fee'
            && ($state['status'] ?? '') === CollateralSecureService::STATUS_AWAITING_VALUATION_FEE) {
            $secure->markValuationFeePaid($application);
        }
    }

    private function normalizeOutcome(string $outcome): string
    {
        $outcome = strtolower(trim($outcome));

        return match ($outcome) {
            'success', 'paid', 'completed' => 'success',
            'pending' => 'pending',
            'failed', 'rejected' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'reversed', 'reversed_payment' => 'reversed',
            default => $outcome,
        };
    }
}
