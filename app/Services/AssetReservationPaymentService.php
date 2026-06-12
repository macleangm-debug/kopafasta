<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AssetReservationPaymentService
{
    public const STEP_RESERVATION_FEE = 'reservation_fee';

    public const STEP_DEPOSIT = 'deposit';

    public function usesDummyGateway(): bool
    {
        return payment_gateway_is_dummy();
    }

    public function paymentType(string $step): string
    {
        return $step === self::STEP_DEPOSIT ? 'asset_deposit' : 'asset_reservation_fee';
    }

    public function amountFor(AssetReservation $reservation, string $step): float
    {
        return $step === self::STEP_DEPOSIT
            ? (float) $reservation->deposit_amount
            : (float) $reservation->reservation_fee_amount;
    }

    public function expectedStatus(string $step): string
    {
        return $step === self::STEP_DEPOSIT ? 'reservation_fee_paid' : 'interest_confirmed';
    }

    public function isPaid(AssetReservation $reservation, string $step): bool
    {
        return $step === self::STEP_DEPOSIT
            ? $reservation->deposit_status === 'paid'
            : $reservation->reservation_fee_status === 'paid';
    }

    /**
     * @param  array{
     *   payment_method: string,
     *   mobile_number?: ?string,
     *   payment_date?: ?string,
     *   proof?: ?UploadedFile,
     *   reference?: ?string,
     * }  $data
     */
    public function submit(Customer $customer, AssetReservation $reservation, string $step, array $data): CustomerPayment
    {
        if ($this->isPaid($reservation, $step)) {
            throw ValidationException::withMessages([
                'payment' => __('borrower.marketplace.payment_already_recorded'),
            ]);
        }

        if ($reservation->status !== $this->expectedStatus($step)) {
            throw ValidationException::withMessages([
                'payment' => __('borrower.marketplace.payment_step_invalid'),
            ]);
        }

        $amount = $this->amountFor($reservation, $step);
        if ($amount <= 0) {
            $this->markPaidWithoutPayment($reservation, $step);

            return CustomerPayment::make([
                'reference'      => 'WAIVED-'.$reservation->id,
                'amount'         => 0,
                'status'         => 'verified',
                'payment_type'   => $this->paymentType($step),
                'payment_method' => 'waived',
            ]);
        }

        $method = $data['payment_method'];
        $dummyGateway = $this->usesDummyGateway();
        $autoVerify = $method === 'mobile_money' || ($dummyGateway && $method === 'bank_transfer');

        return app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => $this->paymentType($step),
            'payment_method' => $method,
            'amount'         => $amount,
            'mobile_number'  => $data['mobile_number'] ?? null,
            'payment_date'   => $data['payment_date'] ?? null,
            'proof'          => $data['proof'] ?? null,
            'source'         => $reservation,
            'reference'      => $data['reference'] ?? null,
            'auto_verify'    => $autoVerify,
        ]);
    }

    public function applyVerifiedPayment(CustomerPayment $payment): void
    {
        if (! in_array($payment->payment_type, ['asset_reservation_fee', 'asset_deposit'], true)) {
            return;
        }

        if ($payment->source_type !== AssetReservation::class || ! $payment->source_id) {
            return;
        }

        $reservation = AssetReservation::query()->find($payment->source_id);
        if (! $reservation) {
            return;
        }

        $reservations = app(AssetReservationService::class);

        if ($payment->payment_type === 'asset_reservation_fee' && $reservation->reservation_fee_status !== 'paid') {
            $reservations->markReservationFeePaid($reservation, $payment->reference);
        }

        if ($payment->payment_type === 'asset_deposit' && $reservation->deposit_status !== 'paid') {
            $reservations->markDepositPaid($reservation, $payment->reference);
        }
    }

    private function markPaidWithoutPayment(AssetReservation $reservation, string $step): void
    {
        $service = app(AssetReservationService::class);

        if ($step === self::STEP_RESERVATION_FEE) {
            $service->markReservationFeePaid($reservation);
        } else {
            $service->markDepositPaid($reservation);
        }
    }
}
