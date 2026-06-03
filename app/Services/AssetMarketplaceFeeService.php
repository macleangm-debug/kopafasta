<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\MarketplaceAsset;

class AssetMarketplaceFeeService
{
    /** @return array{application_fee: int, application_fee_label: string, deposit: float, post_approval: list<array{code: string, name: string, amount_label: string, detail: string|null}>} */
    public function breakdown(Customer $customer, MarketplaceAsset $asset): array
    {
        $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
        $appFee = ChargesFee::where('code', 'APP_FEE')->where('is_active', true)->first();

        return [
            'application_fee'       => quoted_application_fee($customer),
            'application_fee_label' => $appFee?->name ?? __('borrower.marketplace.fees.application'),
            'application_fee_detail' => $appFee?->description,
            'deposit'               => $deposit,
            'deposit_label'         => __('borrower.marketplace.deposit'),
            'post_approval'         => $this->postApprovalLines(),
        ];
    }

    /** @return list<array{code: string, name: string, amount_label: string, detail: string|null}> */
    public function postApprovalLines(): array
    {
        return ChargesFee::query()
            ->where('is_active', true)
            ->where('charge_when', 'post_approval')
            ->orderBy('code')
            ->get()
            ->map(fn (ChargesFee $fee) => [
                'code'         => $fee->code,
                'name'         => $fee->name,
                'amount_label' => $fee->basis === 'percentage'
                    ? number_format((float) $fee->amount, 2).'%'
                    : 'TZS '.number_format((float) $fee->amount, 0),
                'detail'       => $fee->description,
            ])
            ->values()
            ->all();
    }

    public function applicationFeeAmount(Customer $customer): int
    {
        $quoted = quoted_application_fee($customer);

        return $quoted > 0
            ? $quoted
            : (int) config('asset_marketplace.reservation_fee', 50000);
    }
}
