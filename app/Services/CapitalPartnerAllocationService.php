<?php

namespace App\Services;

use App\Models\FundingPool;
use App\Models\Lender;
use App\Models\LenderInvestment;
use App\Models\LenderTransaction;
use App\Models\Loan;
use App\Models\LoanCapitalAllocation;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CapitalPartnerAllocationService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public const PARTNER_INTEREST_SHARE = 60.0;

    public const COMPANY_INTEREST_SHARE = 40.0;

    /**
     * Dry-run capital availability for a loan (no writes).
     *
     * @return array{ok: bool, required: float, available: float, uses_capital: bool, message: ?string}
     */
    public function capitalReadinessForLoan(Loan $loan): array
    {
        $loan->loadMissing(['product']);

        if (! $loan->product || ! $this->productUsesCapitalPartner($loan->product)) {
            return [
                'ok'            => true,
                'required'      => 0,
                'available'     => 0,
                'uses_capital'  => false,
                'message'       => null,
            ];
        }

        $amount = (float) $loan->principal_amount;
        if ($amount <= 0) {
            return [
                'ok'            => true,
                'required'      => 0,
                'available'     => 0,
                'uses_capital'  => true,
                'message'       => null,
            ];
        }

        if ($loan->capitalAllocations()->exists()) {
            return [
                'ok'            => true,
                'required'      => $amount,
                'available'     => $amount,
                'uses_capital'  => true,
                'message'       => null,
            ];
        }

        $partners = $this->availablePartners();
        if ($partners->isEmpty()) {
            return [
                'ok'            => false,
                'required'      => $amount,
                'available'     => 0,
                'uses_capital'  => true,
                'message'       => 'No capital partner pool has available funds for this loan.',
            ];
        }

        $totalAvailable = (float) $partners->sum('available');

        return [
            'ok'            => $totalAvailable >= $amount,
            'required'      => $amount,
            'available'     => $totalAvailable,
            'uses_capital'  => true,
            'message'       => $totalAvailable >= $amount
                ? null
                : 'Insufficient capital partner funds. Available: '.format_money($totalAvailable).', required: '.format_money($amount),
        ];
    }

    /**
     * Release capital reserved for a pending loan (e.g. legacy approval-time allocation or cancelled approval).
     */
    public function releaseAllocationForLoan(Loan $loan): void
    {
        if (! $loan->capitalAllocations()->exists()) {
            return;
        }

        if ($loan->status !== 'pending') {
            throw ValidationException::withMessages([
                'loan' => 'Capital can only be released for pending loans. Use reverse allocation for disbursed loans.',
            ]);
        }

        DB::transaction(function () use ($loan): void {
            $allocations = $loan->capitalAllocations()->with(['pool', 'lender', 'investment'])->get();

            foreach ($allocations as $allocation) {
                $share = (float) $allocation->allocated_principal;

                if ($allocation->pool) {
                    $allocation->pool->amount_deployed = max(0, (float) $allocation->pool->amount_deployed - $share);
                    $allocation->pool->save();
                }

                if ($allocation->lender) {
                    $allocation->lender->available_balance = (float) $allocation->lender->available_balance + $share;
                    $allocation->lender->save();
                }

                if ($allocation->investment) {
                    $allocation->investment->update(['status' => 'cancelled']);
                }

                LenderTransaction::create([
                    'lender_id'            => $allocation->lender_id,
                    'funding_pool_id'      => $allocation->funding_pool_id,
                    'lender_investment_id' => $allocation->lender_investment_id,
                    'loan_id'              => $loan->id,
                    'reference'            => 'TXN-'.Str::upper(Str::random(10)),
                    'type'                 => 'return',
                    'direction'            => 'credit',
                    'amount'               => $share,
                    'status'               => 'completed',
                    'channel'              => 'system',
                    'notes'                => 'Capital allocation released — loan '.$loan->loan_number,
                    'processed_at'         => now(),
                    'created_by'           => $this->actorId(),
                ]);

                $allocation->delete();
            }

            $this->audit->log(
                $this->actor(),
                'capital_partner.allocation_released',
                $loan,
                [],
                ['loan_number' => $loan->loan_number],
            );
        });
    }

    /**
     * Reverse capital exposure when a disbursed loan is cancelled or written off.
     * Returns remaining outstanding exposure to partner pools.
     */
    public function reverseAllocationForLoan(Loan $loan, string $reason = 'Loan reversed'): void
    {
        if (! $loan->capitalAllocations()->exists()) {
            return;
        }

        DB::transaction(function () use ($loan, $reason): void {
            $allocations = $loan->capitalAllocations()->with(['pool', 'lender', 'investment'])->get();

            foreach ($allocations as $allocation) {
                $exposure = (float) $allocation->outstanding_exposure;
                if ($exposure <= 0) {
                    if ($allocation->investment) {
                        $allocation->investment->update(['status' => 'closed']);
                    }

                    continue;
                }

                if ($allocation->pool) {
                    $allocation->pool->amount_deployed = max(0, (float) $allocation->pool->amount_deployed - $exposure);
                    $allocation->pool->save();
                }

                if ($allocation->lender) {
                    $allocation->lender->available_balance = (float) $allocation->lender->available_balance + $exposure;
                    $allocation->lender->save();
                }

                LenderTransaction::create([
                    'lender_id'            => $allocation->lender_id,
                    'funding_pool_id'      => $allocation->funding_pool_id,
                    'lender_investment_id' => $allocation->lender_investment_id,
                    'loan_id'              => $loan->id,
                    'reference'            => 'TXN-'.Str::upper(Str::random(10)),
                    'type'                 => 'return',
                    'direction'            => 'credit',
                    'amount'               => $exposure,
                    'status'               => 'completed',
                    'channel'              => 'system',
                    'notes'                => $reason.' — loan '.$loan->loan_number,
                    'processed_at'         => now(),
                    'created_by'           => $this->actorId(),
                ]);

                $allocation->outstanding_exposure = 0;
                $allocation->save();

                if ($allocation->investment) {
                    $allocation->investment->update(['status' => 'closed']);
                }
            }

            $this->audit->log(
                $this->actor(),
                'capital_partner.allocation_reversed',
                $loan,
                [],
                ['loan_number' => $loan->loan_number, 'reason' => $reason],
            );
        });
    }

    /**
     * Proportionally allocate approved principal across active capital partners at disbursement.
     * Example: Partner A 50M + Partner B 100M → A funds 33.33%, B funds 66.67% of each loan.
     */
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
                    'loan_id'               => $loan->id,
                    'reference'             => 'TXN-'.Str::upper(Str::random(10)),
                    'type'                  => 'investment',
                    'direction'             => 'debit',
                    'amount'                => $share,
                    'status'                => 'completed',
                    'channel'               => 'system',
                    'notes'                 => 'Loan '.$loan->loan_number.' proportional allocation ('.format_number($percent, 2).'%)',
                    'processed_at'          => now(),
                    'created_by'            => $this->actorId(),
                ]);
            }

            $this->audit->log(
                $this->actor(),
                'capital_partner.loan_allocation',
                $loan,
                [],
                ['loan_number' => $loan->loan_number, 'principal' => $amount, 'partners' => $loan->capitalAllocations()->count()],
            );
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

            if ($partnerShare > 0 && $allocation->lender) {
                LenderTransaction::create([
                    'lender_id'        => $allocation->lender_id,
                    'funding_pool_id'  => $allocation->funding_pool_id,
                    'loan_id'          => $loan->id,
                    'reference'        => 'TXN-'.Str::upper(Str::random(10)),
                    'type'             => 'interest_earned',
                    'direction'        => 'credit',
                    'amount'           => $partnerShare,
                    'status'           => 'completed',
                    'channel'          => 'system',
                    'notes'            => 'Interest share — loan '.$loan->loan_number,
                    'processed_at'     => now(),
                    'created_by'       => $this->actorId(),
                ]);
            }
        }

        $this->audit->log(
            $this->actor(),
            'capital_partner.interest_distribution',
            $loan,
            [],
            ['interest' => $interestAmount, 'partner_pct' => self::PARTNER_INTEREST_SHARE],
        );
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
        return loan_product_uses_capital_partner($product);
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

    protected function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function actorId(): ?int
    {
        return $this->actor()?->id;
    }
}
