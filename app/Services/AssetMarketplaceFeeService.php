<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductPostApprovalFee;
use App\Models\MarketplaceAsset;

class AssetMarketplaceFeeService
{
    /** @return array{application_fee: int, application_fee_label: string, deposit: float, post_approval: list<array{code: string, name: string, amount_label: string, detail: string|null}>} */
    public function breakdown(Customer $customer, MarketplaceAsset $asset): array
    {
        $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
        $appFee = ChargesFee::where('code', 'APP_FEE')->where('is_active', true)->first();

        return [
            'application_fee'        => quoted_application_fee($customer),
            'application_fee_label'  => $appFee?->name ?? __('borrower.marketplace.fees.application'),
            'application_fee_detail' => $appFee?->description,
            'deposit'                => $deposit,
            'deposit_label'          => __('borrower.marketplace.deposit'),
            'post_approval'          => $this->postApprovalLines($asset),
        ];
    }

    /** @return list<array{code: string, name: string, amount_label: string, detail: string|null}> */
    public function postApprovalLines(?MarketplaceAsset $asset = null): array
    {
        $product = LoanProduct::query()
            ->where('code', config('asset_marketplace.asset_loan_product_code', 'AL'))
            ->first();

        if ($product) {
            $lines = $this->productPostApprovalLines($product, $asset);
            if ($lines !== []) {
                return $lines;
            }
        }

        return ChargesFee::query()
            ->where('is_active', true)
            ->where('charge_when', 'post_approval')
            ->orderBy('code')
            ->get()
            ->map(fn (ChargesFee $fee) => [
                'code'         => $fee->code,
                'name'         => $fee->name,
                'amount_label' => $fee->basis === 'percentage'
                    ? format_number($fee->amount, 2).'%'
                    : format_money($fee->amount),
                'detail'       => $fee->description,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{code: string, name: string, amount_label: string, detail: string|null}> */
    private function productPostApprovalLines(LoanProduct $product, ?MarketplaceAsset $asset): array
    {
        if ($asset) {
            $deposit = (float) ($asset->customer_deposit ?: $asset->computeCustomerDeposit());
            $assetValue = (float) ($asset->asset_value ?: ($deposit * 1.4));
            $principal = max(0, round($assetValue - $deposit, 2));
        } else {
            $principal = (float) $product->min_amount;
        }

        $tenure = (int) ($asset?->max_tenure_months ?? $product->default_tenure_months ?? 12);

        $previewApplication = new LoanApplication([
            'loan_product_id'         => $product->id,
            'requested_tenure_months' => $tenure,
        ]);
        $previewApplication->setRelation('product', $product);

        $feeService = app(PostApprovalFeeService::class);

        return $product->postApprovalFees()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (LoanProductPostApprovalFee $fee) use ($feeService, $principal, $previewApplication) {
                $amount = $feeService->calculateAmount($fee, $principal, $previewApplication);

                return [
                    'code'         => $fee->code,
                    'name'         => $fee->name,
                    'amount_label' => format_money($amount),
                    'detail'       => $fee->description,
                ];
            })
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
