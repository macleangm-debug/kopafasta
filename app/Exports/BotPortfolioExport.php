<?php

namespace App\Exports;

use App\Models\Loan;
use App\Models\Repayment;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BotPortfolioExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private readonly Carbon $month) {}

    public function title(): string
    {
        return 'BOT '.$this->month->format('Y-m');
    }

    public function headings(): array
    {
        return [
            'Metric',
            'Value (TZS)',
            'Count',
        ];
    }

    public function array(): array
    {
        $start = $this->month->copy()->startOfMonth();
        $end   = $this->month->copy()->endOfMonth();

        $disbursed = Loan::query()
            ->whereBetween('disbursement_date', [$start, $end])
            ->selectRaw('COALESCE(SUM(approved_amount),0) as v, COUNT(*) as c')
            ->first();

        $repaid = Repayment::query()
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(amount),0) as v, COUNT(*) as c')
            ->first();

        $interest = (float) Repayment::query()
            ->whereBetween('paid_at', [$start, $end])
            ->sum('interest_component');

        $penalty = (float) Repayment::query()
            ->whereBetween('paid_at', [$start, $end])
            ->sum('penalty_component');

        $outstanding = (float) Loan::whereIn('status', ['active', 'arrears', 'restructured'])
            ->sum('outstanding_balance');

        $activeLoans = (int) Loan::whereIn('status', ['active', 'arrears', 'restructured'])->count();
        $writtenOff  = (float) Loan::where('status', 'written_off')
            ->whereBetween('updated_at', [$start, $end])
            ->sum('outstanding_balance');

        $par30 = $this->parBucket(30, 99999);
        $par   = $outstanding > 0 ? round($par30 * 100 / $outstanding, 2) : 0;

        return [
            ['Month',                    $start->format('Y-m'),       ''],
            ['Disbursements',            (float) $disbursed->v,       (int) $disbursed->c],
            ['Repayments collected',     (float) $repaid->v,          (int) $repaid->c],
            ['Interest income',          $interest,                   ''],
            ['Penalty income',           $penalty,                    ''],
            ['Outstanding portfolio',    $outstanding,                $activeLoans],
            ['Written-off (month)',      $writtenOff,                 ''],
            ['PAR 30+ amount',           $par30,                      ''],
            ['PAR 30+ %',                $par,                        ''],
        ];
    }

    private function parBucket(int $minDays, int $maxDays): float
    {
        return (float) Loan::whereIn('status', ['active', 'arrears'])
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->subDays($minDays)->endOfDay())
            ->where('next_due_date', '>=', now()->subDays($maxDays + 1)->startOfDay())
            ->sum('outstanding_balance');
    }
}
