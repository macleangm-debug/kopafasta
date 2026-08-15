<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Support\MoneyFormat;

/**
 * Screening keys total deposits on the statement; the system derives monthly (canonical)
 * and weekly averages for capacity, Decision, and counter-offers.
 */
class StatementCapacityService
{
    public const CHECKLIST_KEY = 'activity_income.income_evidence';

    public const DEFAULT_MONTHS = 6;

    /**
     * @return array{
     *   statement_deposits_total: float,
     *   statement_months: int,
     *   statement_monthly: float,
     *   statement_weekly: float
     * }|null
     */
    public function fromIncoming(array $incoming): ?array
    {
        $raw = $incoming['statement_deposits_total'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        $total = round(MoneyFormat::toNumber($raw), 2);
        if ($total <= 0) {
            return null;
        }

        return $this->compute($total, self::DEFAULT_MONTHS);
    }

    /**
     * @return array{
     *   statement_deposits_total: float,
     *   statement_months: int,
     *   statement_monthly: float,
     *   statement_weekly: float
     * }
     */
    public function compute(float $totalDeposits, int $months = self::DEFAULT_MONTHS): array
    {
        $months = max(1, min(24, $months > 0 ? $months : self::DEFAULT_MONTHS));
        $monthly = round($totalDeposits / $months, 2);

        return [
            'statement_deposits_total' => round($totalDeposits, 2),
            'statement_months' => $months,
            'statement_monthly' => $monthly,
            'statement_weekly' => round($monthly * 12 / 52, 2),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function captureForSubject(LoanApplication $application, string $subject = 'borrower'): ?array
    {
        $items = (array) data_get(
            $application->screening_payload,
            'screening_checklist.by_subject.'.$subject.'.items',
            []
        );
        $item = $items[self::CHECKLIST_KEY] ?? null;
        if (! is_array($item)) {
            return null;
        }

        $monthly = (float) ($item['statement_monthly'] ?? 0);
        if ($monthly <= 0) {
            $total = (float) ($item['statement_deposits_total'] ?? 0);
            $months = (int) ($item['statement_months'] ?? 0);
            if ($total > 0 && $months > 0) {
                return $this->compute($total, $months);
            }

            return null;
        }

        return [
            'statement_deposits_total' => (float) ($item['statement_deposits_total'] ?? 0),
            'statement_months' => (int) ($item['statement_months'] ?? self::DEFAULT_MONTHS),
            'statement_monthly' => $monthly,
            'statement_weekly' => (float) ($item['statement_weekly'] ?? round($monthly * 12 / 52, 2)),
        ];
    }

    public function provenMonthly(LoanApplication $application, string $subject = 'borrower'): ?float
    {
        $capture = $this->captureForSubject($application, $subject);
        $monthly = (float) ($capture['statement_monthly'] ?? 0);

        return $monthly > 0 ? $monthly : null;
    }

    public function subjectForGroupMember(LoanApplication $application, object $member): string
    {
        $customerId = (int) ($member->customer_id ?? $member->customer?->id ?? 0);
        $role = (string) ($member->role ?? '');
        $memberId = (int) ($member->id ?? 0);

        if ($role === 'leader' || ($customerId > 0 && $customerId === (int) $application->customer_id)) {
            return 'borrower';
        }

        if ($memberId > 0) {
            return 'member:'.$memberId;
        }

        return 'borrower';
    }

    public function provenMonthlyForGroupMember(LoanApplication $application, object $member): ?float
    {
        return $this->provenMonthly($application, $this->subjectForGroupMember($application, $member));
    }

    public function provenMonthlyForGuarantor(LoanApplication $application, int $linkId): ?float
    {
        if ($linkId < 1) {
            return null;
        }

        return $this->provenMonthly($application, 'guarantor:'.$linkId);
    }

    /**
     * Save-time Gate 2 verdict: statement monthly vs declared profile income.
     *
     * @return array{verdict: string, fail_reason_code: ?string, source: string}
     */
    public function verdictAgainstDeclared(float $monthly, ?Customer $customer): array
    {
        $declared = $customer instanceof Customer ? $this->declaredMonthly($customer) : 0.0;
        if ($declared <= 0) {
            return [
                'verdict' => 'fail',
                'fail_reason_code' => 'income_insufficient',
                'source' => 'system',
            ];
        }
        if ($monthly >= $declared) {
            return [
                'verdict' => 'pass',
                'fail_reason_code' => null,
                'source' => 'system',
            ];
        }

        return [
            'verdict' => 'fail',
            'fail_reason_code' => 'revenue_mismatch',
            'source' => 'system',
        ];
    }

    /**
     * Declared profile income (range midpoint as fallback).
     */
    public function declaredMonthly(Customer $customer): float
    {
        $income = (float) ($customer->monthly_income ?? 0);
        if ($income > 0) {
            return $income;
        }

        if (filled($customer->income_range)) {
            return (float) (config('income_ranges.'.$customer->income_range.'.midpoint') ?? 0);
        }

        return 0.0;
    }

    /**
     * Statement monthly when keyed, otherwise declared.
     *
     * @return array{
     *   net_income: float,
     *   income_basis: 'statement'|'declared',
     *   declared_monthly_income: float,
     *   statement_deposits_total: float|null,
     *   statement_months: int|null,
     *   statement_monthly: float|null,
     *   statement_weekly: float|null
     * }
     */
    public function resolveIncome(LoanApplication $application, Customer $customer, string $subject = 'borrower'): array
    {
        $declared = $this->declaredMonthly($customer);
        $capture = $this->captureForSubject($application, $subject);
        $proven = (float) ($capture['statement_monthly'] ?? 0);

        if ($proven > 0) {
            return [
                'net_income' => $proven,
                'income_basis' => 'statement',
                'declared_monthly_income' => $declared,
                'statement_deposits_total' => (float) ($capture['statement_deposits_total'] ?? 0),
                'statement_months' => (int) ($capture['statement_months'] ?? self::DEFAULT_MONTHS),
                'statement_monthly' => $proven,
                'statement_weekly' => (float) ($capture['statement_weekly'] ?? round($proven * 12 / 52, 2)),
            ];
        }

        return [
            'net_income' => $declared,
            'income_basis' => 'declared',
            'declared_monthly_income' => $declared,
            'statement_deposits_total' => null,
            'statement_months' => null,
            'statement_monthly' => null,
            'statement_weekly' => null,
        ];
    }
}
