<?php

namespace App\Services;

use App\Models\AssetAuctionSettlement;
use App\Models\BorrowerRefund;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BorrowerRefundService
{
    public function createFromSettlement(AssetAuctionSettlement $settlement): ?BorrowerRefund
    {
        if ((float) $settlement->borrower_refund <= 0) {
            return null;
        }

        $loan = $settlement->loan;
        if (! $loan?->customer_id) {
            return null;
        }

        $existing = BorrowerRefund::query()
            ->where('asset_auction_settlement_id', $settlement->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $currency = app(CountrySettingsService::class)
            ->forCode($loan->customer?->country_code)['currency'] ?? 'TZS';

        $refund = BorrowerRefund::create([
            'customer_id'                 => $loan->customer_id,
            'loan_id'                     => $loan->id,
            'asset_auction_settlement_id' => $settlement->id,
            'reference'                   => 'REF-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
            'amount'                      => $settlement->borrower_refund,
            'currency'                    => $currency,
            'status'                      => BorrowerRefund::STATUS_PENDING,
            'notes'                       => 'Surplus from asset auction settlement #'.$settlement->id,
        ]);

        $customer = $loan->customer;
        if ($customer?->phone) {
            app(NotificationService::class)->sendSms(
                $customer->phone,
                brand_name().': You have a refund of '.format_money((float) $refund->amount)
                    .' from your loan auction. Log in to submit payout details.',
                $customer,
                'borrower_refund_created',
            );
        }

        app(BorrowerRefundPostingService::class)->accrue($refund->fresh());

        return $refund;
    }

    public function submitPayoutDetails(BorrowerRefund $refund, Customer $customer, array $data): BorrowerRefund
    {
        if ((int) $refund->customer_id !== (int) $customer->id) {
            abort(404);
        }

        if (! $refund->needsPayoutDetails() && ! $refund->isPayable()) {
            throw ValidationException::withMessages([
                'refund' => 'This refund is no longer awaiting payout details.',
            ]);
        }

        if (! $refund->needsPayoutDetails()) {
            throw ValidationException::withMessages([
                'refund' => 'Payout details were already submitted.',
            ]);
        }

        $validated = validator($data, [
            'payout_channel'        => ['required', 'in:mobile_money,bank'],
            'payout_phone'          => ['required_if:payout_channel,mobile_money', 'nullable', 'string', 'max:30'],
            'payout_provider'       => ['nullable', 'string', 'max:40'],
            'payout_account_name'   => ['required_if:payout_channel,bank', 'nullable', 'string', 'max:120'],
            'payout_account_number' => ['required_if:payout_channel,bank', 'nullable', 'string', 'max:80'],
        ])->validate();

        $refund->update([
            ...$validated,
            'status'                => BorrowerRefund::STATUS_AWAITING_PAYOUT,
            'details_submitted_at'  => now(),
        ]);

        return $refund->fresh();
    }

    public function markPaid(
        BorrowerRefund $refund,
        User $actor,
        ?string $paymentReference = null,
        ?string $notes = null,
        bool $autoDisburse = false,
    ): BorrowerRefund {
        if (! $refund->isPayable()) {
            throw ValidationException::withMessages([
                'refund' => 'Refund cannot be paid in its current status.',
            ]);
        }

        return DB::transaction(function () use ($refund, $actor, $paymentReference, $notes, $autoDisburse) {
            $refund = $refund->fresh(['customer', 'loan']);
            $disbursementMeta = [];

            if ($autoDisburse) {
                $result = app(MobileMoneyDisbursementService::class)->send($refund);
                if (! $result['success']) {
                    throw ValidationException::withMessages([
                        'disbursement' => $result['error'] ?? 'Mobile money disbursement failed.',
                    ]);
                }

                $paymentReference = $result['reference'];
                $disbursementMeta = [
                    'disbursement_status'                  => 'dispatched',
                    'disbursement_reference'               => $result['reference'],
                    'disbursement_dispatched_at'           => now(),
                    'disbursement_error'                   => null,
                    'disbursement_mobile_money_account_id' => $result['account']?->id,
                ];
            }

            if (! filled($paymentReference)) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'Enter a payment reference or use auto-disburse.',
                ]);
            }

            $refund->update([
                ...$disbursementMeta,
                'status'             => BorrowerRefund::STATUS_PAID,
                'paid_at'            => now(),
                'paid_by'            => $actor->id,
                'payment_reference'  => $paymentReference,
                'notes'              => trim(($refund->notes ? $refund->notes."\n" : '').($notes ?? '')),
            ]);

            app(BorrowerRefundPostingService::class)->postPayout($refund->fresh());

            $customer = $refund->customer;
            if ($customer?->phone) {
                app(NotificationService::class)->sendSms(
                    $customer->phone,
                    brand_name().': Refund '.format_money((float) $refund->amount)
                        .' paid. Reference: '.$paymentReference,
                    $customer,
                    'borrower_refund_paid',
                );
            }

            return $refund->fresh(['customer', 'loan', 'payoutJournalEntry', 'accrualJournalEntry']);
        });
    }
}
