<?php

namespace App\Services;

use App\Models\ChargesFee;
use Illuminate\Support\Collection;

class FeeCatalogService
{
    /** Active fees charged after approval, before disbursement prep. */
    public function postApprovalFees(): Collection
    {
        return ChargesFee::query()
            ->where('is_active', true)
            ->where('charge_when', 'post_approval')
            ->orderBy('code')
            ->get();
    }

    public function findPostApprovalFee(int|string $idOrCode): ?ChargesFee
    {
        $query = ChargesFee::query()
            ->where('is_active', true)
            ->where('charge_when', 'post_approval');

        if (is_numeric($idOrCode)) {
            return $query->whereKey($idOrCode)->first();
        }

        return $query->where('code', strtoupper((string) $idOrCode))->first();
    }

    /** Map catalog basis to product post-approval fee_type. */
    public function feeTypeFromCatalog(ChargesFee $fee): string
    {
        return match ($fee->basis) {
            'percentage' => 'percent',
            'fixed' => in_array(strtoupper((string) $fee->code), array_map('strtoupper', config('gps_pricing.fee_codes', ['GPS_FEE'])), true)
                || $fee->type === 'gps'
                ? 'gps'
                : 'fixed',
            default => $fee->type === 'gps' ? 'gps' : 'fixed',
        };
    }

    /** @return array{charges_fee_id: int, code: string, name: string, fee_type: string, amount: float} */
    public function snapshotForProduct(ChargesFee $fee): array
    {
        $feeType = $this->feeTypeFromCatalog($fee);

        return [
            'charges_fee_id' => (int) $fee->id,
            'code'           => $fee->code,
            'name'           => $fee->name,
            'fee_type'       => $feeType,
            'amount'         => (float) $fee->amount,
        ];
    }

    public function formatAmountLabel(ChargesFee $fee): string
    {
        return $fee->basis === 'percentage'
            ? format_number($fee->amount, 2).'% of principal'
            : format_money($fee->amount);
    }
}
