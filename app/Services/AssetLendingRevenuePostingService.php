<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\JournalEntry;
use App\Models\MarketplaceAsset;

class AssetLendingRevenuePostingService
{
    public function postDepositMarkup(AssetReservation $reservation): ?JournalEntry
    {
        $reservation->loadMissing('asset');
        $asset = $reservation->asset;

        if (! $asset) {
            return null;
        }

        $markup = app(AssetLendingService::class)->depositMarkupAmount($asset);
        if ($markup <= 0) {
            return null;
        }

        $ledger = app(LedgerService::class);
        $revenueAccountId = $ledger->assetLendingRevenueAccountId();
        $receivableAccountId = $ledger->loanReceivableAccountId();

        if (! $revenueAccountId || ! $receivableAccountId) {
            return null;
        }

        $reference = 'AL-MKU-'.$reservation->id;

        $exists = JournalEntry::query()
            ->where('source_type', AssetReservation::class)
            ->where('source_id', $reservation->id)
            ->where('description', 'like', '%deposit markup%')
            ->exists();

        if ($exists) {
            return null;
        }

        try {
            return $ledger->post(
                [
                    ['account_id' => $receivableAccountId, 'debit' => $markup, 'credit' => 0, 'description' => 'Deposit markup receivable'],
                    ['account_id' => $revenueAccountId, 'debit' => 0, 'credit' => $markup, 'description' => 'Asset lending revenue'],
                ],
                'Asset lending deposit markup · '.$asset->title.' · '.$reference,
                $reservation,
            );
        } catch (\Throwable $e) {
            logger()->warning('Asset lending markup GL not posted: '.$e->getMessage(), [
                'reservation_id' => $reservation->id,
            ]);

            return null;
        }
    }
}
