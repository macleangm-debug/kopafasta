<?php

namespace Tests\Unit;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanPenaltyPolicy;
use Tests\TestCase;

class LoanPenaltyPolicyTest extends TestCase
{
    public function test_per_day_penalty_is_one_percent_of_overdue_balance(): void
    {
        $policy = new LoanPenaltyPolicy(7, 1.0, 'per_day', 30.0);

        $this->assertEqualsWithDelta(1000.0, $policy->perDayPenaltyAmount(100_000), 0.01);
        $this->assertEqualsWithDelta(30_000.0, $policy->maxPenaltyAmount(100_000), 0.01);
    }

    public function test_loan_inherits_product_grace_and_penalty(): void
    {
        $product = new LoanProduct([
            'code' => 'IL',
            'default_grace_days' => 7,
            'penalty_rate_percent' => 1.0,
            'penalty_basis' => 'per_day',
        ]);

        $loan = new Loan([
            'default_grace_days' => null,
            'penalty_rate_percent' => null,
            'penalty_basis' => null,
        ]);
        $loan->setRelation('product', $product);

        $policy = LoanPenaltyPolicy::for($loan);

        $this->assertSame(7, $policy->graceDaysAfterDefault);
        $this->assertEqualsWithDelta(1.0, $policy->penaltyRatePercent, 0.001);
    }

    public function test_emergency_product_defaults_three_day_grace(): void
    {
        $product = new LoanProduct(['code' => 'EM']);
        $defaults = LoanPenaltyPolicy::defaultsForProduct($product);

        $this->assertSame(3, $defaults['default_grace_days']);
        $this->assertEqualsWithDelta(1.0, $defaults['penalty_rate_percent'], 0.001);
    }
}
