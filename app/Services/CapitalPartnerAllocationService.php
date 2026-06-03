<?php

namespace App\Services;

use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\LenderInvestment;
use App\Models\LenderTransaction;
use App\Models\Loan;
use App\Models\LoanCapitalAllocation;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CapitalPartnerAllocationService
{
    public const PARTNER_INTEREST_SHARE = 60.0;

    public const COMPANY_INTEREST_SHARE = 40.0;

    /** Proportionally allocate approved principal across active capital partners. */
    public function allocateForLoan(Loan $loan): void
    {
        $loan->loadMissing(['product', 'application']);

        if ($loan->capitalAllocations()->exists()) {
            return;
        }

        $product = $loan->product;
        if (! $product || ! $this->productUsesCapitalPartner($product)) {
            return;
        }

        $amount = (float) $loan->principal_amount;
        if ($amount <= 0) {
            return;
        }

        $partners = $this->availablePartners();
        if ($partners->isEmpty()) {
            throw ValidationException::withMessages([
                'capital' => 'No capital partner pool has available funds for this loan.',
            ]);
        }

        $totalAvailable = $partners->sum('available');
        if ($totalAvailable < $amount) {
            throw ValidationException::withMessages([
                'capital' => 'Insufficient capital partner funds. Available: '.format_money($totalAvailable).', required: '.format_money($amount),
            ]);
        }

        DB::transaction(function () use ($loan, $partners, $amount, $totalAvailable): void {
            $remaining = $amount;
            $lastIndex = $partners->count() - 1;

            foreach ($partners->values() as $index => $partner) {
                $share = $index === $lastIndex
                    ? $remaining
                    : round($amount * ($partner['available'] / $totalAvailable), 2);

                $share = min($share, $remaining, $partner['available']);
                if ($share <= 0) {
                    continue;
                }

                $remaining -= $share;
                $percent = round(($share / $amount) * 100, 4);

                $investment = LenderInvestment::create([
                    'lender_id'        => $partner['lender']->id,
                    'funding_pool_id'  => $partner['pool']->id,
                    'loan_id'          => $loan->id,
                    'reference'        => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'principal'        => $share,
                    'return_amount'    => 0,
                    'return_rate'      => $partner['pool']->expected_yield ?? 0,
                    'invested_at'      => now()->toDateString(),
                    'status'           => 'active',
                ]);

                LoanCapitalAllocation::create([
                    'loan_id'                        => $loan->id,
                    'lender_id'                      => $partner['lender']->id,
                    'funding_pool_id'                => $partner['pool']->id,
                    'lender_investment_id'           => $investment->id,
                    'allocated_principal'            => $share,
                    'allocation_percent'             => $percent,
                    'partner_interest_share_percent' => self::PARTNER_INTEREST_SHARE,
                    'company_interest_share_percent' => self::COMPANY_INTEREST_SHARE,
                    'outstanding_exposure'           => $share,
                ]);

                $pool = $partner['pool'];
                $pool->amount_deployed = (float) $pool->amount_deployed + $share;
                $pool->save();

                $lender = $partner['lender'];
                if ($lender->available_balance > 0) {
                    $lender->available_balance = max(0, (float) $lender->available_balance - $share);
                    $lender->save();
                }

                LenderTransaction::create([
                    'lender_id'             => $lender->id,
                    'funding_pool_id'       => $pool->id,
                    'lender_investment_id'  => $investment->id,
                    'reference'             => 'TXN-'.Str::upper(Str::random(10)),
                    'type'                  => 'investment',
                    'amount'                => $share,
                    'status'                => 'completed',
                    'channel'               => 'system',
                    'notes'                 => 'Loan '.$loan->loan_number.' proportional allocation',
                    'processed_at'          => now(),
                ]);
            }
        });
    }

    /** Distribute interest from a repayment across capital partners (60/40 split). */
    public function distributeInterest(Loan $loan, float $interestAmount): void
    {
        if ($interestAmount <= 0) {
            return;
        }

        $allocations = $loan->capitalAllocations()->with('lender')->get();
        if ($allocations->isEmpty()) {
            return;
        }

        $loanPrincipal = max(0.01, (float) $loan->principal_amount);
        $remaining = $interestAmount;

        foreach ($allocations as $index => $allocation) {
            $weight = (float) $allocation->allocated_principal / $loanPrincipal;
            $share = $index === $allocations->count() - 1
                ? $remaining
                : round($interestAmount * $weight, 2);

            $remaining -= $share;
            $partnerShare = round($share * ((float) $allocation->partner_interest_share_percent / 100), 2);
            $companyShare = round($share - $partnerShare, 2);

            $allocation->interest_earned_partner = (float) $allocation->interest_earned_partner + $partnerShare;
            $allocation->interest_earned_company = (float) $allocation->interest_earned_company + $companyShare;
            $allocation->save();

            if ($allocation->investment) {
                $allocation->investment->return_amount = (float) $allocation->investment->return_amount + $partnerShare;
                $allocation->investment->save();
            }
        }
    }

    /** Reduce outstanding exposure when principal is repaid. */
    public function reduceExposure(Loan $loan, float $principalPaid): void
    {
        if ($principalPaid <= 0) {
            return;
        }

        $allocations = $loan->capitalAllocations()->get();
        if ($allocations->isEmpty()) {
            return;
        }

        $loanPrincipal = max(0.01, (float) $loan->principal_amount);
        $remaining = $principalPaid;

        foreach ($allocations as $index => $allocation) {
            $weight = (float) $allocation->allocated_principal / $loanPrincipal;
            $reduction = $index === $allocations->count() - 1
                ? $remaining
                : round($principalPaid * $weight, 2);

            $remaining -= $reduction;
            $allocation->outstanding_exposure = max(0, (float) $allocation->outstanding_exposure - $reduction);
            $allocation->save();
        }
    }

    public function productUsesCapitalPartner(LoanProduct $product): bool
    {
        return (bool) ($product->uses_capital_partner ?? true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{lender: Lender, pool: FundingPool, available: float}>
     */
    protected function availablePartners()
    {
        return Lender::query()
            ->where('status', 'active')
            ->with(['pools' => fn ($q) => $q->where('status', 'open')])
            ->get()
            ->flatMap(function (Lender $lender) {
                return $lender->pools->map(function (FundingPool $pool) use ($lender) {
                    $available = max(0, (float) $pool->amount_committed - (float) $pool->amount_deployed);

                    if ($available <= 0 && (float) $lender->available_balance > 0) {
                        $available = (float) $lender->available_balance;
                    }

                    return [
                        'lender'    => $lender,
                        'pool'      => $pool,
                        'available' => $available,
                    ];
                });
            })
            ->filter(fn (array $row) => $row['available'] > 0)
            ->sortByDesc('available')
            ->values();
    }
}
