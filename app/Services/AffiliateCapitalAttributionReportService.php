<?php

namespace App\Services;

use App\Models\LoanCapitalAllocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AffiliateCapitalAttributionReportService
{
    /**
     * @return array{
     *     totals: array<string, int|float>,
     *     rows: list<array<string, mixed>>,
     *     matrix: array<string, array<string, float>>
     * }
     */
    public function report(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subYear()->startOfDay();
        $to ??= now()->endOfDay();

        $query = LoanCapitalAllocation::query()
            ->with(['loan.customer.affiliateVendor', 'lender'])
            ->whereHas('loan.customer', fn ($q) => $q->whereNotNull('affiliate_partner_id'))
            ->whereHas('loan', fn ($q) => $q->whereBetween('created_at', [$from, $to]));

        $allocations = $query->get();

        $rows = $allocations->map(function (LoanCapitalAllocation $allocation): array {
            $loan = $allocation->loan;
            $customer = $loan?->customer;
            $affiliate = $customer?->affiliateVendor;

            return [
                'affiliate_id'          => $affiliate?->id,
                'affiliate_name'        => $affiliate?->name,
                'affiliate_code'        => $affiliate?->affiliate_code,
                'customer_id'           => $customer?->id,
                'customer_name'         => trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')),
                'customer_number'       => $customer?->customer_number,
                'loan_id'               => $loan?->id,
                'loan_number'           => $loan?->loan_number,
                'lender_id'             => $allocation->lender_id,
                'lender_name'           => $allocation->lender?->name,
                'allocated_principal'   => (float) $allocation->allocated_principal,
                'outstanding_exposure'  => (float) $allocation->outstanding_exposure,
                'allocation_percent'    => (float) $allocation->allocation_percent,
            ];
        })->values()->all();

        $matrix = [];
        foreach ($rows as $row) {
            $affiliateKey = (string) ($row['affiliate_name'] ?? 'Unknown affiliate');
            $lenderKey = (string) ($row['lender_name'] ?? 'Unknown lender');
            $matrix[$affiliateKey][$lenderKey] = ($matrix[$affiliateKey][$lenderKey] ?? 0)
                + (float) $row['allocated_principal'];
        }

        return [
            'totals' => [
                'loans'                 => $allocations->pluck('loan_id')->unique()->count(),
                'allocations'           => $allocations->count(),
                'affiliates'            => collect($rows)->pluck('affiliate_id')->filter()->unique()->count(),
                'capital_partners'      => $allocations->pluck('lender_id')->unique()->count(),
                'allocated_principal'   => (float) $allocations->sum('allocated_principal'),
                'outstanding_exposure'  => (float) $allocations->sum('outstanding_exposure'),
            ],
            'rows'   => $rows,
            'matrix' => $matrix,
        ];
    }

    /** @return Collection<int, object> */
    public function summaryByAffiliate(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->subYear()->startOfDay();
        $to ??= now()->endOfDay();

        return collect(DB::table('loan_capital_allocations as lca')
            ->join('loans as l', 'l.id', '=', 'lca.loan_id')
            ->join('customers as c', 'c.id', '=', 'l.customer_id')
            ->join('partners as p', 'p.id', '=', 'c.affiliate_partner_id')
            ->whereNotNull('c.affiliate_partner_id')
            ->whereBetween('l.created_at', [$from, $to])
            ->selectRaw('p.id as affiliate_id, p.name as affiliate_name, p.affiliate_code, count(distinct l.id) as loans, sum(lca.allocated_principal) as allocated, sum(lca.outstanding_exposure) as exposure')
            ->groupBy('p.id', 'p.name', 'p.affiliate_code')
            ->orderByDesc('allocated')
            ->get());
    }
}
