<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanProduct;

class ApplicationFeeCreditService
{
    /** Amount already paid toward application fees (verified payments + paid application rows). */
    public function paidCredit(Customer $customer, ?LoanApplication $application = null): int
    {
        $credit = 0;

        if ($application) {
            if (in_array($application->application_fee_status ?? '', ['paid', 'charged'], true)) {
                $credit += (int) ($application->application_fee_amount ?? 0);
            }
        }

        $credit += (int) CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->whereIn('payment_type', ['application_fee', 'asset_reservation_fee'])
            ->whereIn('status', ['paid', 'verified'])
            ->when($application, fn ($q) => $q->where(function ($query) use ($application): void {
                $query->where('source_type', LoanApplication::class)
                    ->where('source_id', $application->id);
            }))
            ->sum('amount');

        return max(0, $credit);
    }

    public function quotedFee(?Customer $customer, ?LoanProduct $product): int
    {
        return quoted_application_fee($customer, $product);
    }

    /** Fee still due after crediting prior payments on the same application. */
    public function additionalDue(Customer $customer, LoanProduct $product, ?LoanApplication $application = null): int
    {
        $quoted = $this->quotedFee($customer, $product);
        $credit = $this->paidCredit($customer, $application);

        return max(0, $quoted - $credit);
    }

    /** @return array{quoted: int, credit: int, due: int, prior_product_fee: int|null, new_product_fee: int} */
    public function conversionQuote(LoanApplication $application, LoanProduct $newProduct): array
    {
        $application->loadMissing(['customer', 'product']);
        $customer = $application->customer;
        $priorFee = (int) ($application->application_fee_amount ?? $this->quotedFee($customer, $application->product));
        $newFee = $this->quotedFee($customer, $newProduct);
        $credit = $this->paidCredit($customer, $application);
        $due = max(0, $newFee - $credit);

        return [
            'quoted'           => $newFee,
            'credit'           => $credit,
            'due'              => $due,
            'prior_product_fee'=> $priorFee,
            'new_product_fee'  => $newFee,
        ];
    }
}
