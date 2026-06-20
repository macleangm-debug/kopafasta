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

    public function partnerInterestSharePercent(?Lender $lender = null): float
    {
        if ($lender && $lender->revenue_share_percent !== null && $lender->revenue_share_percent !== '') {
            return (float) $lender->revenue_share_percent;
        }

        return (float) (\App\Models\Setting::get('finance.capital_partner_interest_share_percent') ?? self::PARTNER_INTEREST_SHARE);
    }

    public function companyInterestSharePercent(?Lender $lender = null): float
    {
        return max(0, 100 - $this->partnerInterestSharePercent($lender));
    }

    /**
     * Dry-run capital availability for a loan (no writes).
     *
     * @return array{ok: bool, required: float, available: float, uses_capital: bool, message: ?string}
     */
    public function capitalReadinessForLoan(Loan $loan): array
    {
        $loan->loadMissing(['product', 'application']);

        if (! $loan->product || ! $this->productUsesCapitalPartner($loan->product)) {
            return [
                'ok'            => true,
                'required'      => 0,
                'available'     => 0,
                'uses_capital'  => false,
                'message'       => null,
            ];
        }

        if ($loan->application && application_uses_internal_funding($loan->application)) {
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
                'manual_required' => false,
            ];
        }

        if ($this->allocationStrategy() === 'manual') {
            $partners = $this->availablePartners($loan->application?->preferred_lender_id);
            $totalAvailable = (float) $partners->sum('available');

            return [
                'ok'              => false,
                'required'        => $amount,
                'available'       => $totalAvailable,
                'uses_capital'    => true,
                'message'         => 'Assign capital partners on this loan before disbursement.',
                'manual_required' => true,
            ];
        }

        $partners = $this->availablePartners($loan->application?->preferred_lender_id);
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

        if ($loan->application && application_uses_internal_funding($loan->application)) {
            return;
        }

        $amount = (float) $loan->principal_amount;
        if ($amount <= 0) {
            return;
        }

        $partners = $this->availablePartners($loan->application?->preferred_lender_id);
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

        $strategy = $this->allocationStrategy();

        if ($strategy === 'manual') {
            throw ValidationException::withMessages([
                'capital' => 'Manual allocation required. Assign capital partners on the loan page before disbursing.',
            ]);
        }

        $slices = match ($strategy) {
            'round_robin' => $this->buildRoundRobinSlices($partners, $amount),
            'priority'    => $this->buildPrioritySlices($partners, $amount),
            default       => $this->buildProportionalSlices($partners, $amount),
        };

        if ($slices === []) {
            throw ValidationException::withMessages([
                'capital' => 'Could not build a capital allocation plan for this loan.',
            ]);
        }

        DB::transaction(function () use ($loan, $amount, $slices, $strategy): void {
            foreach ($slices as $slice) {
                $this->persistAllocationSlice($loan, $slice, $strategy);
            }

            $this->audit->log(
                $this->actor(),
                'capital_partner.loan_allocation',
                $loan,
                [],
                [
                    'loan_number' => $loan->loan_number,
                    'principal'   => $amount,
                    'strategy'    => $strategy,
                    'partners'    => $loan->capitalAllocations()->count(),
                ],
            );
        });
    }

    /**
     * Manually assign capital partner(s) to a pending loan (when strategy = manual).
     *
     * @param  list<array{lender_id: int, amount: float|int|string}>  $rows
     */
    public function allocateManually(Loan $loan, array $rows): void
    {
        $loan->loadMissing(['product', 'application']);

        if ($this->allocationStrategy() !== 'manual') {
            throw ValidationException::withMessages([
                'capital' => 'Manual allocation is only available when finance settings use the manual strategy.',
            ]);
        }

        if ($loan->capitalAllocations()->exists()) {
            throw ValidationException::withMessages([
                'capital' => 'This loan already has capital allocations.',
            ]);
        }

        if (! $loan->product || ! $this->productUsesCapitalPartner($loan->product)) {
            throw ValidationException::withMessages([
                'capital' => 'This loan product does not use capital partner funding.',
            ]);
        }

        if ($loan->application && application_uses_internal_funding($loan->application)) {
            throw ValidationException::withMessages([
                'capital' => 'Internal funding was selected at approval — no partner allocation is needed.',
            ]);
        }

        if (! in_array($loan->status, ['pending'], true)) {
            throw ValidationException::withMessages([
                'capital' => 'Capital can only be allocated while the loan is pending disbursement.',
            ]);
        }

        $principal = (float) $loan->principal_amount;
        if ($principal <= 0) {
            throw ValidationException::withMessages([
                'capital' => 'Loan principal must be greater than zero.',
            ]);
        }

        $partners = $this->availablePartners($loan->application?->preferred_lender_id)
            ->keyBy(fn (array $row) => $row['lender']->id);

        $slices = [];
        $total = 0.0;

        foreach ($rows as $index => $row) {
            $lenderId = (int) ($row['lender_id'] ?? 0);
            $share = (float) ($row['amount'] ?? 0);

            if ($share <= 0) {
                continue;
            }

            $partner = $partners->get($lenderId);
            if (! $partner) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.lender_id" => 'Selected partner is unavailable or has no open pool.',
                ]);
            }

            if ($share > (float) $partner['available'] + 0.01) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" => 'Amount exceeds available capital ('.format_money($partner['available']).').',
                ]);
            }

            $total += $share;
            $slices[] = [
                'lender'  => $partner['lender'],
                'pool'    => $partner['pool'],
                'share'   => round($share, 2),
                'percent' => round(($share / $principal) * 100, 4),
            ];
        }

        if ($slices === []) {
            throw ValidationException::withMessages([
                'allocations' => 'Add at least one partner with an amount greater than zero.',
            ]);
        }

        if (abs($total - $principal) > 0.01) {
            throw ValidationException::withMessages([
                'allocations' => 'Allocated total must equal loan principal ('.format_money($principal).').',
            ]);
        }

        DB::transaction(function () use ($loan, $principal, $slices): void {
            foreach ($slices as $slice) {
                $this->persistAllocationSlice($loan, $slice, 'manual');
            }

            $this->audit->log(
                $this->actor(),
                'capital_partner.manual_allocation',
                $loan,
                [],
                [
                    'loan_number' => $loan->loan_number,
                    'principal'   => $principal,
                    'partners'    => count($slices),
                ],
            );
        });
    }

    /** @return \Illuminate\Support\Collection<int, array{lender_id: int, lender_name: string, pool_name: string, available: float}> */
    public function partnerOptionsForLoan(Loan $loan): \Illuminate\Support\Collection
    {
        $loan->loadMissing('application');

        return $this->availablePartners($loan->application?->preferred_lender_id)
            ->map(fn (array $row) => [
                'lender_id'   => $row['lender']->id,
                'lender_name' => $row['lender']->name,
                'pool_name'   => $row['pool']->name,
                'available'   => (float) $row['available'],
            ])
            ->values();
    }

    public function needsManualAllocation(Loan $loan): bool
    {
        if ($this->allocationStrategy() !== 'manual') {
            return false;
        }

        $loan->loadMissing(['product', 'application']);

        if (! $loan->product || ! $this->productUsesCapitalPartner($loan->product)) {
            return false;
        }

        if ($loan->application && application_uses_internal_funding($loan->application)) {
            return false;
        }

        return $loan->status === 'pending' && ! $loan->capitalAllocations()->exists();
    }

    public function allocationStrategy(): string
    {
        $strategy = (string) (\App\Models\Setting::get('finance.capital_allocation_strategy') ?? 'proportional');

        return in_array($strategy, ['proportional', 'round_robin', 'priority', 'manual'], true)
            ? $strategy
            : 'proportional';
    }

    /** @return list<array{lender: Lender, pool: FundingPool, share: float, percent: float}> */
    protected function buildProportionalSlices($partners, float $amount): array
    {
        $totalAvailable = $partners->sum('available');
        $remaining = $amount;
        $lastIndex = $partners->count() - 1;
        $slices = [];

        foreach ($partners->values() as $index => $partner) {
            $share = $index === $lastIndex
                ? $remaining
                : round($amount * ($partner['available'] / $totalAvailable), 2);

            $share = min($share, $remaining, $partner['available']);
            if ($share <= 0) {
                continue;
            }

            $remaining -= $share;
            $slices[] = [
                'lender'  => $partner['lender'],
                'pool'    => $partner['pool'],
                'share'   => $share,
                'percent' => round(($share / $amount) * 100, 4),
            ];
        }

        return $slices;
    }

    /** @return list<array{lender: Lender, pool: FundingPool, share: float, percent: float}> */
    protected function buildRoundRobinSlices($partners, float $amount): array
    {
        $ordered = $this->partnersOrderedForRoundRobin($partners);

        foreach ($ordered as $partner) {
            if ($partner['available'] >= $amount) {
                return [[
                    'lender'  => $partner['lender'],
                    'pool'    => $partner['pool'],
                    'share'   => $amount,
                    'percent' => 100.0,
                ]];
            }
        }

        return $this->buildProportionalSlices($partners, $amount);
    }

    /** @return list<array{lender: Lender, pool: FundingPool, share: float, percent: float}> */
    protected function buildPrioritySlices($partners, float $amount): array
    {
        $ordered = $partners
            ->sortBy(fn (array $row) => $row['lender']->allocation_priority ?? 9999)
            ->values();

        $remaining = $amount;
        $slices = [];

        foreach ($ordered as $partner) {
            if ($remaining <= 0) {
                break;
            }

            $share = min($remaining, $partner['available']);
            if ($share <= 0) {
                continue;
            }

            $remaining -= $share;
            $slices[] = [
                'lender'  => $partner['lender'],
                'pool'    => $partner['pool'],
                'share'   => $share,
                'percent' => round(($share / $amount) * 100, 4),
            ];
        }

        return $slices;
    }

    /** @param array{lender: Lender, pool: FundingPool, share: float, percent: float} $slice */
    protected function persistAllocationSlice(Loan $loan, array $slice, string $strategy): void
    {
        $share = (float) $slice['share'];
        $percent = (float) $slice['percent'];
        $strategyLabel = str_replace('_', ' ', $strategy);

        $investment = LenderInvestment::create([
            'lender_id'        => $slice['lender']->id,
            'funding_pool_id'  => $slice['pool']->id,
            'loan_id'          => $loan->id,
            'reference'        => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'principal'        => $share,
            'return_amount'    => 0,
            'return_rate'      => $slice['pool']->expected_yield ?? 0,
            'invested_at'      => now()->toDateString(),
            'status'           => 'active',
        ]);

        LoanCapitalAllocation::create([
            'loan_id'                        => $loan->id,
            'lender_id'                      => $slice['lender']->id,
            'funding_pool_id'                => $slice['pool']->id,
            'lender_investment_id'           => $investment->id,
            'allocated_principal'            => $share,
            'allocation_percent'             => $percent,
            'partner_interest_share_percent' => $this->partnerInterestSharePercent($slice['lender']),
            'company_interest_share_percent' => $this->companyInterestSharePercent($slice['lender']),
            'outstanding_exposure'           => $share,
        ]);

        $pool = $slice['pool'];
        $pool->amount_deployed = (float) $pool->amount_deployed + $share;
        $pool->save();

        $lender = $slice['lender'];
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
            'notes'                 => 'Loan '.$loan->loan_number.' '.$strategyLabel.' allocation ('.format_number($percent, 2).'%)',
            'processed_at'          => now(),
            'created_by'            => $this->actorId(),
        ]);
    }

    protected function partnersOrderedForRoundRobin($partners)
    {
        $lastAllocated = LoanCapitalAllocation::query()
            ->selectRaw('lender_id, MAX(created_at) as last_allocated_at')
            ->groupBy('lender_id')
            ->pluck('last_allocated_at', 'lender_id');

        return $partners
            ->sortBy(fn (array $row) => $lastAllocated[$row['lender']->id] ?? '1970-01-01 00:00:00')
            ->values();
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
            ['interest' => $interestAmount, 'partner_pct' => $this->partnerInterestSharePercent()],
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
    protected function availablePartners(?int $preferredLenderId = null)
    {
        return Lender::query()
            ->where('status', 'active')
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('lenders', 'funding_source'),
                fn ($q) => $q->where(function ($inner): void {
                    $inner->where('funding_source', 'external')->orWhereNull('funding_source');
                })
            )
            ->when($preferredLenderId, fn ($q) => $q->where('id', $preferredLenderId))
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
