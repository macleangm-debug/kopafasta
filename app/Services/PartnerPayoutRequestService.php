<?php

namespace App\Services;

use App\Models\PartnerPayment;
use App\Models\PartnerPayoutRequest;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PartnerPayoutRequestService
{
    public function availableBalance(Vendor $vendor, string $sourceType): float
    {
        $approved = (float) PartnerPayment::query()
            ->where('partner_id', $vendor->id)
            ->where('source_type', $sourceType)
            ->where('status', 'approved')
            ->sum('amount');

        $payoutQuery = PartnerPayoutRequest::query()
            ->where('partner_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved']);

        $sourceColumn = Schema::hasColumn('partner_payout_requests', 'source_type')
            ? 'source_type'
            : (Schema::hasColumn('partner_payout_requests', 'wallet_type') ? 'wallet_type' : 'source_type');

        $reserved = (float) (clone $payoutQuery)
            ->where($sourceColumn, $sourceType)
            ->sum('amount');

        return max(0, round($approved - $reserved, 2));
    }

    public function request(Vendor $vendor, string $sourceType, float $amount, ?string $notes = null): PartnerPayoutRequest
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payout amount must be greater than zero.');
        }

        if ($sourceType === 'affiliate_commission') {
            $min = app(AffiliateSettingsService::class)->minimumPayoutAmount();
            if ($amount < $min) {
                throw new \InvalidArgumentException(__('site.affiliate_portal.payout_minimum', ['amount' => format_money($min)]));
            }
        }

        $available = $this->availableBalance($vendor, $sourceType);
        if ($amount > $available) {
            throw new \InvalidArgumentException(__('site.affiliate_portal.payout_exceeds_balance', ['available' => format_money($available)]));
        }

        $sourceColumn = Schema::hasColumn('partner_payout_requests', 'source_type')
            ? 'source_type'
            : 'wallet_type';

        return PartnerPayoutRequest::create([
            'partner_id'   => $vendor->id,
            $sourceColumn  => $sourceType,
            'amount'       => $amount,
            'status'       => 'pending',
            'notes'        => filled($notes) ? trim($notes) : null,
        ]);
    }

    public function approve(PartnerPayoutRequest $request, ?\App\Models\User $actor = null): PartnerPayoutRequest
    {
        abort_unless($request->status === 'pending', 422, 'Only pending payout requests can be approved.');

        $request->update([
            'status'      => 'approved',
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
        ]);

        return $request->fresh();
    }

    public function reject(PartnerPayoutRequest $request, ?\App\Models\User $actor = null, ?string $reason = null): PartnerPayoutRequest
    {
        abort_unless($request->status === 'pending', 422, 'Only pending payout requests can be rejected.');

        $request->update([
            'status'      => 'rejected',
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
            'notes'       => trim(($request->notes ? $request->notes."\n" : '').($reason ? 'Rejected: '.$reason : 'Rejected')),
        ]);

        $request = $request->fresh();

        $partner = $this->resolvePartner($request);
        if ($partner) {
            try {
                app(NotificationService::class)->notifyPartner(
                    $partner,
                    'partner_payout_rejected',
                    [
                        'partner' => $partner->name,
                        'amount' => format_money((float) $request->amount),
                        'reason' => $reason ? 'Reason: '.$reason : '',
                        '_fallback_subject' => 'Payout request rejected',
                        '_fallback_body' => trim('Your payout request for '.format_money((float) $request->amount).' was rejected.'.($reason ? ' Reason: '.$reason : '')),
                    ],
                    $this->partnerPaymentsUrl(),
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify partner of payout rejection', ['request_id' => $request->id, 'error' => $e->getMessage()]);
            }
        }

        return $request;
    }

    public function markPaid(PartnerPayoutRequest $request, ?\App\Models\User $actor = null): PartnerPayoutRequest
    {
        abort_unless(in_array($request->status, ['pending', 'approved'], true), 422, 'Request cannot be marked paid.');

        $request = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $actor) {
            $payload = [
                'status'      => 'paid',
                'reviewed_by' => $actor?->id ?? $request->reviewed_by,
                'reviewed_at' => now(),
            ];
            if (Schema::hasColumn('partner_payout_requests', 'paid_at')) {
                $payload['paid_at'] = now();
            }

            $request->update($payload);

            // Mark matching approved commission lines as paid up to the request amount.
            $sourceType = $request->source_type ?? $request->wallet_type ?? null;
            if ($sourceType) {
                $remaining = (float) $request->amount;
                PartnerPayment::query()
                    ->where('partner_id', $request->partner_id)
                    ->where('source_type', $sourceType)
                    ->where('status', 'approved')
                    ->orderBy('id')
                    ->get()
                    ->each(function (PartnerPayment $payment) use (&$remaining): void {
                        if ($remaining <= 0) {
                            return;
                        }
                        $payment->update(array_filter([
                            'status'  => 'paid',
                            'paid_at' => Schema::hasColumn($payment->getTable(), 'paid_at') ? now() : null,
                        ]));
                        $remaining -= (float) $payment->amount;
                    });
            }

            $this->postPayoutJournal($request->fresh());

            return $request->fresh();
        });

        $partner = $this->resolvePartner($request);
        if ($partner) {
            try {
                app(NotificationService::class)->notifyPartner(
                    $partner,
                    'partner_payout_paid',
                    [
                        'partner' => $partner->name,
                        'amount' => format_money((float) $request->amount),
                        '_fallback_subject' => 'Payout sent',
                        '_fallback_body' => 'Your payout of '.format_money((float) $request->amount).' has been marked paid.',
                    ],
                    $this->partnerPaymentsUrl(),
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify partner of payout paid', ['request_id' => $request->id, 'error' => $e->getMessage()]);
            }
        }

        return $request;
    }

    private function postPayoutJournal(PartnerPayoutRequest $request): void
    {
        $amount = round((float) $request->amount, 2);
        if ($amount <= 0) {
            return;
        }

        $already = \App\Models\JournalEntry::query()
            ->where('source_type', PartnerPayoutRequest::class)
            ->where('source_id', $request->id)
            ->where('status', 'posted')
            ->exists();
        if ($already) {
            return;
        }

        $ledger = app(LedgerService::class);
        $payableId = $ledger->recoveryPartnerPayableAccountId();
        $cashId = $ledger->cashAccountId();
        if (! $payableId || ! $cashId) {
            Log::warning('Partner payout paid without GL accounts configured', ['request_id' => $request->id]);

            return;
        }

        try {
            $ledger->post(
                [
                    ['account_id' => $payableId, 'debit' => $amount, 'credit' => 0, 'description' => 'Partner payout'],
                    ['account_id' => $cashId, 'debit' => 0, 'credit' => $amount, 'description' => 'Cash/bank'],
                ],
                'Partner payout #'.$request->id,
                $request,
                now()->toDateString(),
                'Partner payout request marked paid',
            );
        } catch (\Throwable $e) {
            Log::error('Failed to post partner payout journal', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function resolvePartner(PartnerPayoutRequest $request): ?Vendor
    {
        if ($request->partner_id) {
            return Vendor::query()->find($request->partner_id);
        }

        $partner = $request->partner ?? null;

        return $partner ? Vendor::query()->find($partner->id) : null;
    }

    private function partnerPaymentsUrl(): ?string
    {
        return \Illuminate\Support\Facades\Route::has('site.partner.payments')
            ? route('site.partner.payments')
            : null;
    }
}
