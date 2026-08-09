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
        // Deposit is paid only after loan approval (not before screening).
        return $step === self::STEP_DEPOSIT ? 'approved' : 'interest_confirmed';
    }

    public function canPayDeposit(AssetReservation $reservation): bool
    {
        return $reservation->status === 'approved'
            && $reservation->deposit_status !== 'paid'
            && (float) $reservation->deposit_amount > 0;
    }

    public function paymentReference(AssetReservation $reservation, string $step): string
    {
        $suffix = $step === self::STEP_DEPOSIT ? 'DEP' : 'FEE';

        return 'RES-'.$reservation->id.'-'.$suffix;
    }

    public function gateFeeType(string $step): string
    {
        return $step === self::STEP_DEPOSIT ? 'asset_deposit' : 'application_fee';
    }

    /** @return array<string, mixed> */
    public function quote(Customer $customer, AssetReservation $reservation, string $step, bool $useWallet = false, ?string $promoCode = null): array
    {
        $base = $this->amountFor($reservation, $step);
        $cfg = MembershipService::config();

        if ($base <= 0) {
            return [
                'base'           => 0,
                'after_discount' => 0,
                'discount'       => 0,
                'total_discount' => 0,
                'wallet_applied' => 0,
                'cash_due'       => 0,
                'wallet_usable'  => 0,
                'wallet_allowed' => false,
                'currency'       => $cfg['currency'],
            ];
        }

        return app(PaymentGateService::class)->quote(
            $customer,
            $base,
            $this->gateFeeType($step),
            $useWallet,
            $promoCode,
        );
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

        $useWallet = (bool) ($data['use_wallet'] ?? false);
        $promoCode = $data['promo_code'] ?? null;
        $quote = $this->quote($customer, $reservation, $step, $useWallet, $promoCode);
        $cashDue = (float) ($quote['cash_due'] ?? $quote['after_discount']);

        if ($cashDue <= 0) {
            app(PaymentGateService::class)->settle(
                $customer,
                $quote,
                $this->gateFeeType($step),
                AssetReservation::class,
                (int) $reservation->id,
                $useWallet,
            );
            $this->markPaidWithoutPayment($reservation, $step);

            return CustomerPayment::make([
                'reference'      => $data['reference'] ?? $this->paymentReference($reservation, $step),
                'amount'         => 0,
                'status'         => 'verified',
                'payment_type'   => $this->paymentType($step),
                'payment_method' => 'waived',
            ]);
        }

        $method = $data['payment_method'];
        $dummyGateway = $this->usesDummyGateway();
        $autoVerify = $method === 'mobile_money' || ($dummyGateway && $method === 'bank_transfer');

        if ($autoVerify) {
            app(PaymentGateService::class)->settle(
                $customer,
                $quote,
                $this->gateFeeType($step),
                AssetReservation::class,
                (int) $reservation->id,
                $useWallet,
            );
        }

        return app(CustomerPaymentService::class)->create([
            'customer'       => $customer,
            'payment_type'   => $this->paymentType($step),
            'payment_method' => $method,
            'amount'         => $cashDue,
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
