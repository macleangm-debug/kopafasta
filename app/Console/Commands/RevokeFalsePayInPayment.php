<?php

namespace App\Console\Commands;

use App\Models\CustomerPayment;
use App\Models\MembershipHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reverse a PayIn payment that was incorrectly verified (e.g. event=completed, status=failed)
 * and roll back membership issued solely from that payment reference.
 */
class RevokeFalsePayInPayment extends Command
{
    protected $signature = 'payments:revoke-false-payin
                            {reference : Customer payment reference (e.g. PAY-8NL0EM)}
                            {--force : Skip confirmation}';

    protected $description = 'Reject a falsely verified PayIn payment and revoke membership issued from it';

    public function handle(): int
    {
        $reference = (string) $this->argument('reference');
        $payment = CustomerPayment::query()->where('reference', $reference)->first();

        if (! $payment) {
            $this->error("Payment {$reference} not found.");

            return self::FAILURE;
        }

        $payloadStatus = strtolower((string) data_get($payment->provider_meta, 'last_payload.status', ''));
        $this->line("Payment #{$payment->id} status={$payment->status} type={$payment->payment_type} payload_status={$payloadStatus}");

        if (! $this->option('force') && ! $this->confirm("Reject this payment and revoke membership tied to {$reference}?")) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($payment, $reference) {
            $payment->update([
                'status' => 'rejected',
                'verified_at' => null,
                'verified_by' => null,
                'verification_notes' => trim(($payment->verification_notes ?? '')."\nRevoked: PayIn payload was not a successful collection."),
            ]);

            if ($payment->payment_type !== 'registration_fee' || ! $payment->customer_id) {
                return;
            }

            $customer = $payment->customer()->lockForUpdate()->first();
            if (! $customer) {
                return;
            }

            $fromThisPayment = MembershipHistory::query()
                ->where('customer_id', $customer->id)
                ->where('payment_reference', $reference)
                ->whereIn('event', ['issued', 'renewed'])
                ->exists();

            if (! $fromThisPayment) {
                return;
            }

            // Only clear membership when this payment was the sole issue/renew source still on record.
            $other = MembershipHistory::query()
                ->where('customer_id', $customer->id)
                ->whereIn('event', ['issued', 'renewed'])
                ->where(function ($q) use ($reference) {
                    $q->whereNull('payment_reference')
                        ->orWhere('payment_reference', '!=', $reference);
                })
                ->exists();

            if ($other) {
                MembershipHistory::create([
                    'customer_id' => $customer->id,
                    'event' => 'revoked_payment',
                    'payment_reference' => $reference,
                    'notes' => 'False PayIn verification revoked; other membership history retained.',
                ]);

                return;
            }

            $customer->forceFill([
                'membership_status' => null,
                'membership_issued_at' => null,
                'membership_expires_at' => null,
                'last_renewal_at' => null,
                'member_no' => $customer->member_no,
                'renewal_count' => 0,
            ])->save();

            MembershipHistory::create([
                'customer_id' => $customer->id,
                'event' => 'revoked',
                'payment_reference' => $reference,
                'notes' => 'Membership cleared after false PayIn verification.',
            ]);
        });

        $this->info("Revoked {$reference}.");

        return self::SUCCESS;
    }
}
