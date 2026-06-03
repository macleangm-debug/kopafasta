<?php

namespace App\Services;

use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanCapitalAllocation;
use Illuminate\Support\Collection;

class CapitalPartnerMetricsService
{
    /** @return array<string, float|int> */
    public function platformSummary(): array
    {
        $invested = (float) FundingPool::query()->sum('amount_committed');
        $utilized = (float) FundingPool::query()->sum('amount_deployed');

        $sums = LoanCapitalAllocation::query()
            ->selectRaw('COALESCE(SUM(outstanding_exposure), 0) as outstanding_exposure')
            ->selectRaw('COALESCE(SUM(interest_earned_partner), 0) as interest_earned_partner')
            ->selectRaw('COALESCE(SUM(interest_earned_company), 0) as interest_earned_company')
            ->selectRaw('COUNT(DISTINCT CASE WHEN outstanding_exposure > 0 THEN loan_id END) as active_loans')
            ->first();

        $partnerInterest = (float) ($sums->interest_earned_partner ?? 0);
        $companyInterest = (float) ($sums->interest_earned_company ?? 0);

        return [
            'capital_invested'        => $invested,
            'capital_utilized'        => $utilized,
            'capital_available'       => max(0, $invested - $utilized),
            'outstanding_exposure'    => (float) ($sums->outstanding_exposure ?? 0),
            'interest_earned_total'   => $partnerInterest + $companyInterest,
            'interest_earned_partner' => $partnerInterest,
            'interest_earned_company' => $companyInterest,
            'active_partners'         => Lender::query()->where('status', 'active')->count(),
            'active_loans'            => (int) ($sums->active_loans ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function partnersOverview(): array
    {
        return Lender::query()
            ->with('pools')
            ->orderBy('name')
            ->get()
            ->map(function (Lender $lender) {
                $metrics = $this->forLender($lender);

                return [
                    'id'                      => $lender->id,
                    'code'                    => $lender->code,
                    'name'                    => $lender->name,
                    'status'                  => $lender->status,
                    'capital_invested'        => $metrics['capital_invested'],
                    'capital_utilized'        => $metrics['capital_utilized'],
                    'capital_available'       => $metrics['capital_available'],
                    'outstanding_exposure'    => $metrics['outstanding_exposure'],
                    'interest_earned_partner' => $metrics['interest_earned_partner'],
                    'interest_earned_company' => $metrics['interest_earned_company'],
                    'active_loans'            => $metrics['active_loans'],
                ];
            })
            ->all();
    }

    /** @return Collection<int, LoanCapitalAllocation> */
    public function recentAllocations(int $limit = 20): Collection
    {
        return LoanCapitalAllocation::query()
            ->with(['loan.customer', 'lender', 'pool'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, float|int> */
    public function forLender(Lender $lender): array
    {
        $lender->loadMissing('pools');

        $capitalInvested = (float) $lender->pools->sum('amount_committed');
        if ($capitalInvested <= 0) {
            $capitalInvested = (float) ($lender->credit_limit ?? 0);
        }

        $capitalUtilized = (float) $lender->pools->sum('amount_deployed');

        $sums = LoanCapitalAllocation::query()
            ->where('lender_id', $lender->id)
            ->selectRaw('COALESCE(SUM(outstanding_exposure), 0) as outstanding_exposure')
            ->selectRaw('COALESCE(SUM(interest_earned_partner), 0) as interest_earned_partner')
            ->selectRaw('COALESCE(SUM(interest_earned_company), 0) as interest_earned_company')
            ->selectRaw('COUNT(DISTINCT CASE WHEN outstanding_exposure > 0 THEN loan_id END) as active_loans')
            ->first();

        $partnerInterest = (float) ($sums->interest_earned_partner ?? 0);
        $companyInterest = (float) ($sums->interest_earned_company ?? 0);

        return [
            'capital_invested'        => $capitalInvested,
            'capital_utilized'        => $capitalUtilized,
            'capital_available'       => max(0, $capitalInvested - $capitalUtilized),
            'outstanding_exposure'    => (float) ($sums->outstanding_exposure ?? 0),
            'interest_earned_total'   => $partnerInterest + $companyInterest,
            'interest_earned_partner' => $partnerInterest,
            'interest_earned_company' => $companyInterest,
            'active_loans'            => (int) ($sums->active_loans ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function poolRows(Lender $lender): array
    {
        $totalCommitted = (float) $lender->pools->sum('amount_committed') ?: (float) ($lender->credit_limit ?? 0);

        return $lender->pools->map(function (FundingPool $pool) use ($totalCommitted) {
            $committed = (float) $pool->amount_committed;
            $deployed = (float) $pool->amount_deployed;

            return [
                'name'      => $pool->name,
                'status'    => $pool->status,
                'committed' => $committed,
                'deployed'  => $deployed,
                'available' => max(0, $committed - $deployed),
                'share_pct' => $totalCommitted > 0 ? round(($committed / $totalCommitted) * 100, 2) : 0,
            ];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function allocationsForLoan(Loan $loan): array
    {
        return $loan->capitalAllocations()
            ->with(['lender', 'pool'])
            ->get()
            ->map(fn (LoanCapitalAllocation $row) => [
                'partner'               => $row->lender?->name ?? '—',
                'pool'                  => $row->pool?->name ?? '—',
                'allocated_principal'   => (float) $row->allocated_principal,
                'allocation_percent'    => (float) $row->allocation_percent,
                'outstanding_exposure'  => (float) $row->outstanding_exposure,
                'interest_earned_partner' => (float) $row->interest_earned_partner,
                'interest_earned_company' => (float) $row->interest_earned_company,
                'partner_share_pct'     => (float) $row->partner_interest_share_percent,
                'company_share_pct'     => (float) $row->company_interest_share_percent,
            ])
            ->all();
    }

    /** @return array<string, float> */
    public function loanTotals(Loan $loan): array
    {
        $rows = $loan->capitalAllocations;

        return [
            'allocated_principal'     => (float) $rows->sum('allocated_principal'),
            'outstanding_exposure'    => (float) $rows->sum('outstanding_exposure'),
            'interest_earned_partner'   => (float) $rows->sum('interest_earned_partner'),
            'interest_earned_company' => (float) $rows->sum('interest_earned_company'),
        ];
    }
}
