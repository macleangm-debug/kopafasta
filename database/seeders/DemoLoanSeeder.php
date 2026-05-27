<?php

namespace Database\Seeders;

use App\Models\ApplicationStageHistory;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use Illuminate\Database\Seeder;

class DemoLoanSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedApplicationsAndActiveLoans();
        $this->seedStatusVariants();
    }

    private function seedApplicationsAndActiveLoans(): void
    {
        $customers = Customer::query()->orderBy('id')->limit(8)->get();
        $products = LoanProduct::query()->where('is_active', true)->get()->keyBy('code');

        if ($customers->isEmpty() || $products->isEmpty()) {
            $this->command?->warn('DemoLoanSeeder: needs customers and loan products first. Run CustomerSeeder + LoanProductSeeder.');
            return;
        }

        $plans = [
            ['code' => 'BIZ-30', 'amount' => 800000,  'tenure' => 3,  'final_stage' => 'submitted'],
            ['code' => 'SAL-12', 'amount' => 2500000, 'tenure' => 12, 'final_stage' => 'screening'],
            ['code' => 'AGR-24', 'amount' => 4000000, 'tenure' => 18, 'final_stage' => 'credit_appraisal'],
            ['code' => 'EMG-06', 'amount' => 500000,  'tenure' => 4,  'final_stage' => 'pre_approval'],
            ['code' => 'BIZ-30', 'amount' => 1500000, 'tenure' => 3,  'final_stage' => 'approval'],
            ['code' => 'AST-36', 'amount' => 15000000,'tenure' => 36, 'final_stage' => 'disbursement', 'disburse' => true],
            ['code' => 'SAL-12', 'amount' => 3000000, 'tenure' => 12, 'final_stage' => 'disbursement', 'disburse' => true],
            ['code' => 'EMG-06', 'amount' => 250000,  'tenure' => 2,  'final_stage' => 'rejected'],
        ];

        foreach ($plans as $i => $plan) {
            $customer = $customers[$i] ?? $customers[$i % $customers->count()];
            $product = $products[$plan['code']] ?? $products->first();

            $appNumber = 'APP-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);

            $app = LoanApplication::query()->updateOrCreate(
                ['application_number' => $appNumber],
                [
                    'customer_id' => $customer->id,
                    'loan_product_id' => $product->id,
                    'branch_id' => $customer->branch_id,
                    'requested_amount' => $plan['amount'],
                    'requested_tenure_months' => $plan['tenure'],
                    'recommended_amount' => $plan['amount'],
                    'status' => 'submitted',
                    'current_stage' => 'submitted',
                    'purpose' => 'Demo seeded application',
                    'submitted_at' => now()->subDays(10 - $i),
                ]
            );

            $stages = ['submitted', 'screening', 'credit_appraisal', 'pre_approval', 'approval', 'disbursement'];
            $target = $plan['final_stage'];
            $targetIndex = $target === 'rejected' ? 1 : array_search($target, $stages, true);

            $from = 'submitted';
            for ($s = 1; $s <= $targetIndex; $s++) {
                $to = $stages[$s];
                ApplicationStageHistory::query()->firstOrCreate(
                    [
                        'loan_application_id' => $app->id,
                        'from_stage' => $from,
                        'to_stage' => $to,
                    ],
                    ['changed_by' => null, 'remarks' => 'Demo transition']
                );
                $from = $to;
            }

            if ($target === 'rejected') {
                $app->update([
                    'current_stage' => 'rejected',
                    'status' => 'rejected',
                    'rejection_reason' => 'Demo: insufficient income.',
                ]);
                ApplicationStageHistory::query()->firstOrCreate(
                    ['loan_application_id' => $app->id, 'from_stage' => $from, 'to_stage' => 'rejected'],
                    ['changed_by' => null, 'remarks' => 'Demo rejection']
                );
                continue;
            }

            $app->update([
                'current_stage' => $target,
                'status' => $target === 'disbursement' ? 'approved' : 'in_progress',
                'pre_approved_at' => $targetIndex >= 3 ? now()->subDays(5) : null,
                'approved_at' => $targetIndex >= 4 ? now()->subDays(3) : null,
            ]);

            if (! empty($plan['disburse'])) {
                $loanNumber = 'LN-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);
                $loan = Loan::query()->updateOrCreate(
                    ['loan_number' => $loanNumber],
                    [
                        'loan_application_id' => $app->id,
                        'customer_id' => $customer->id,
                        'loan_product_id' => $product->id,
                        'principal_amount' => $plan['amount'],
                        'approved_amount' => $plan['amount'],
                        'interest_rate' => (float) $product->interest_rate,
                        'tenure_months' => $plan['tenure'],
                        'outstanding_balance' => $plan['amount'],
                        'status' => 'active',
                        'disbursement_date' => now()->subDays(2)->toDateString(),
                        'maturity_date' => now()->addMonths($plan['tenure'])->toDateString(),
                        'next_due_date' => now()->addMonth()->toDateString(),
                    ]
                );

                $app->update([
                    'status' => 'disbursed',
                    'disbursed_at' => now()->subDays(2),
                ]);

                $monthlyPrincipal = round($plan['amount'] / $plan['tenure'], 2);
                $monthlyInterest = round($plan['amount'] * (float) $product->interest_rate, 2);
                for ($m = 1; $m <= $plan['tenure']; $m++) {
                    RepaymentSchedule::query()->firstOrCreate(
                        ['loan_id' => $loan->id, 'installment_no' => $m],
                        [
                            'due_date' => now()->addMonths($m)->toDateString(),
                            'principal_due' => $monthlyPrincipal,
                            'interest_due' => $monthlyInterest,
                            'total_due' => $monthlyPrincipal + $monthlyInterest,
                            'amount_paid' => 0,
                            'status' => 'pending',
                        ]
                    );
                }
            }
        }
    }

    private function seedStatusVariants(): void
    {
        $customers = Customer::query()->orderBy('id')->get();
        $product = LoanProduct::query()->where('is_active', true)->first();

        if ($customers->isEmpty() || ! $product) {
            return;
        }

        $variants = [
            ['status' => 'pending',       'prefix' => 'LN-PEN-', 'count' => 3, 'amount' => 600000,  'tenure' => 6],
            ['status' => 'restructuring', 'prefix' => 'LN-RST-', 'count' => 2, 'amount' => 1800000, 'tenure' => 12],
            ['status' => 'defaulted',     'prefix' => 'LN-DEF-', 'count' => 3, 'amount' => 1200000, 'tenure' => 9],
            ['status' => 'closed',        'prefix' => 'LN-CLS-', 'count' => 4, 'amount' => 900000,  'tenure' => 6],
        ];

        $rate = (float) $product->interest_rate;
        $i = 0;

        foreach ($variants as $variant) {
            for ($n = 1; $n <= $variant['count']; $n++) {
                $customer = $customers[$i % $customers->count()];
                $i++;

                $loanNumber = $variant['prefix'].str_pad((string) $n, 4, '0', STR_PAD_LEFT);
                $isPending = $variant['status'] === 'pending';
                $isClosed = $variant['status'] === 'closed';

                Loan::query()->updateOrCreate(
                    ['loan_number' => $loanNumber],
                    [
                        'customer_id' => $customer->id,
                        'loan_product_id' => $product->id,
                        'principal_amount' => $variant['amount'],
                        'approved_amount' => $variant['amount'],
                        'interest_rate' => $rate,
                        'tenure_months' => $variant['tenure'],
                        'outstanding_balance' => $isClosed ? 0 : $variant['amount'],
                        'status' => $variant['status'],
                        'disbursement_date' => $isPending ? null : now()->subDays(30)->toDateString(),
                        'maturity_date' => now()->addMonths($variant['tenure'])->toDateString(),
                        'next_due_date' => $isClosed ? null : now()->addMonth()->toDateString(),
                        'closed_at' => $isClosed ? now()->subDays(5) : null,
                    ]
                );
            }
        }
    }
}
