<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\AffordabilityService;
use App\Services\ApplicationOfferService;
use App\Services\ScreeningChecklistService;
use App\Services\StatementCapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatementCapacityFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $branch = Branch::create([
            'code' => 'ST'.random_int(1000, 9999),
            'name' => 'Statement Capacity Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function application(User $actor, array $customerExtra = []): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'IL-ST-'.random_int(100, 999),
            'name' => 'Statement Capacity Product',
            'is_active' => true,
            'interest_rate' => 0.05,
            'min_amount' => 50_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);

        $customer = Customer::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-ST-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Statement',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $actor->branch_id,
            'monthly_income' => 5_000_000,
        ], $customerExtra));

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $actor->branch_id,
            'application_number' => 'APP-ST-'.random_int(1000, 9999),
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 6,
            'status' => 'under_review',
            'current_stage' => 'screening',
            'submitted_at' => now(),
        ]);
    }

    public function test_affordability_uses_statement_monthly_not_declared_income(): void
    {
        $admin = $this->staff();
        $app = $this->application($admin);

        app(ScreeningChecklistService::class)->save($app, $admin, [
            'activity_income' => [
                'income_evidence' => [
                    'verdict' => 'pass',
                    'statement_deposits_total' => 600_000,
                    'statement_months' => 6,
                ],
            ],
        ]);

        $eval = app(AffordabilityService::class)->evaluate($app->fresh(['customer', 'product']));

        $this->assertSame('statement', $eval['income_basis']);
        $this->assertEquals(100_000, (float) $eval['net_income']);
        $this->assertEquals(5_000_000, (float) $eval['declared_monthly_income']);
        $this->assertEquals(round(100_000 * 12 / 52, 2), (float) $eval['statement_weekly']);
        $ratio = app(\App\Services\CountryCreditSettingsService::class)->repaymentRatio();
        $this->assertEquals(round(100_000 * $ratio, 2), (float) $eval['max_repayment_capacity']);
        $this->assertLessThan(2_000_000, (float) $eval['max_affordable_principal']);
    }

    public function test_counter_offer_uses_system_amount_not_typed_amount(): void
    {
        Setting::set('underwriting.enable_counter_offers', true);

        $admin = $this->staff();
        $app = $this->application($admin);

        app(ScreeningChecklistService::class)->save($app, $admin, [
            'activity_income' => [
                'income_evidence' => [
                    'verdict' => 'pass',
                    'statement_deposits_total' => 600_000,
                    'statement_months' => 6,
                ],
            ],
        ]);

        $offers = app(ApplicationOfferService::class);
        $system = $offers->maxCounterOffer($app->fresh(['customer', 'product']));

        $this->assertGreaterThan(0, $system['amount']);
        $this->assertLessThan(2_000_000, $system['amount']);

        $saved = $offers->submitRecommendation(
            $app->fresh(['customer', 'product']),
            $admin,
            ApplicationOfferService::RECOMMEND_COUNTER,
            4_000_000,
            6,
            'Statement inflows cannot support the requested amount.',
        );

        $this->assertSame('counter', $saved->recommendation_type);
        $this->assertEquals($system['amount'], (float) $saved->recommended_amount);
        $this->assertNotEquals(4_000_000, (float) $saved->recommended_amount);
    }

    public function test_from_incoming_parses_comma_formatted_deposits(): void
    {
        $capture = app(StatementCapacityService::class)->fromIncoming([
            'statement_deposits_total' => '6,000,000',
            'statement_months' => 6,
        ]);

        $this->assertNotNull($capture);
        $this->assertEquals(6_000_000, $capture['statement_deposits_total']);
        $this->assertSame(6, $capture['statement_months']);
        $this->assertEquals(1_000_000, $capture['statement_monthly']);
    }

    public function test_from_incoming_always_uses_six_months(): void
    {
        $capture = app(StatementCapacityService::class)->fromIncoming([
            'statement_deposits_total' => '3,000,000',
            'statement_months' => 3,
        ]);

        $this->assertNotNull($capture);
        $this->assertSame(6, $capture['statement_months']);
        $this->assertEquals(500_000, $capture['statement_monthly']);
    }

    public function test_verdict_against_declared_pass_and_fail(): void
    {
        $capacity = app(StatementCapacityService::class);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-ST-VD-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Verdict',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'monthly_income' => 1_000_000,
        ]);

        $pass = $capacity->verdictAgainstDeclared(1_000_000, $customer);
        $this->assertSame('pass', $pass['verdict']);
        $this->assertNull($pass['fail_reason_code']);

        $fail = $capacity->verdictAgainstDeclared(200_000, $customer);
        $this->assertSame('fail', $fail['verdict']);
        $this->assertSame('revenue_mismatch', $fail['fail_reason_code']);

        $customer->update(['monthly_income' => 0]);
        $noDeclared = $capacity->verdictAgainstDeclared(1_000_000, $customer->fresh());
        $this->assertSame('fail', $noDeclared['verdict']);
        $this->assertSame('income_insufficient', $noDeclared['fail_reason_code']);
    }
}
