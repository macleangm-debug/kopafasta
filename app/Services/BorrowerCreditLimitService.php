<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;

class BorrowerCreditLimitService
{
    public function __construct(
        private readonly LoanQualificationService $qualification,
    ) {}

    /** @return array{total: float, outstanding: float, available: float, has_data: bool} */
    public function forCustomer(Customer $customer): array
    {
        $qualification = $this->qualification->calculate($customer);
        $total = (float) ($qualification['amount'] ?? 0);
        $hasData = (bool) ($qualification['has_data'] ?? false);

        $outstanding = (float) Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring'])
            ->sum('outstanding_balance');

        return [
            'total'       => $total,
            'outstanding' => $outstanding,
            'available'   => max(0, $total - $outstanding),
            'has_data'    => $hasData,
        ];
    }

    public function availableAmount(Customer $customer): float
    {
        return $this->forCustomer($customer)['available'];
    }

    public function availableFormatted(Customer $customer): string
    {
        $limits = $this->forCustomer($customer);

        return $limits['has_data']
            ? format_money($limits['available'])
            : __('borrower.dashboard.snapshot.limit_unknown');
    }
}
