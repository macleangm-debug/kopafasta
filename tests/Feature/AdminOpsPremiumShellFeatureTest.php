<?php

namespace Tests\Feature;

use App\Models\ArrearCase;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanTopUpRequest;
use App\Models\Partner;
use App\Models\RecoveryAssignment;
use App\Models\RestructureRequest;
use App\Models\User;
use App\Models\WriteOffRequest;
use App\Services\LoanPenaltyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOpsPremiumShellFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $branch = Branch::create([
            'code'      => 'OPS'.random_int(10, 99),
            'name'      => 'Ops Branch',
            'region'    => 'Dar',
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role'      => 'admin',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    /** @return array{admin: User, loan: Loan, arrear: ArrearCase} */
    private function arrearsFixture(): array
    {
        $admin = $this->staff();
        $product = LoanProduct::create([
            'code'              => 'IL-OPS-'.random_int(100, 999),
            'name'              => 'Installment',
            'is_active'         => true,
            'interest_rate'     => 0.18,
            'min_amount'        => 50_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $customer = Customer::create([
            'user_id'         => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-OPS-'.random_int(1000, 9999),
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Ops',
            'last_name'       => 'Borrower',
            'phone'           => '25571'.random_int(1000000, 9999999),
            'branch_id'       => $admin->branch_id,
        ]);
        $application = LoanApplication::create([
            'customer_id'             => $customer->id,
            'loan_product_id'         => $product->id,
            'branch_id'               => $admin->branch_id,
            'application_number'      => 'APP-OPS-'.random_int(1000, 9999),
            'requested_amount'        => 100_000,
            'requested_tenure_months' => 6,
            'status'                  => 'disbursed',
            'current_stage'           => 'disbursement',
        ]);
        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_application_id' => $application->id,
            'loan_number'         => 'LN-OPS-'.strtoupper(substr(md5((string) random_int(1, 999999)), 0, 4)),
            'principal_amount'    => 100_000,
            'approved_amount'     => 100_000,
            'outstanding_balance' => 79_934,
            'interest_rate'       => 0.18,
            'tenure_months'       => 6,
            'status'              => 'arrears',
            'disbursement_date'   => now()->subMonths(2),
        ]);
        $arrear = ArrearCase::create([
            'loan_id'           => $loan->id,
            'days_past_due'     => 21,
            'amount_in_arrears' => 25_000,
            'penalty_amount'    => 1_200,
            'status'            => 'open',
        ]);

        return compact('admin', 'loan', 'arrear');
    }

    public function test_collection_case_and_queue_use_premium_letterhead(): void
    {
        ['admin' => $admin, 'arrear' => $arrear] = $this->arrearsFixture();

        $index = $this->actingAs($admin, 'admin')
            ->get(route('admin.arrear-cases.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Collection cases', $index);
        $this->assertStringContainsString('tracking-[0.2em]', $index);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.arrear-cases.show', $arrear))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Collection case #'.$arrear->id, $html);
        $this->assertStringContainsString('Collections', $html);
        $this->assertStringContainsString('tracking-[0.2em]', $html);
    }

    public function test_write_off_request_uses_premium_letterhead(): void
    {
        ['admin' => $admin, 'loan' => $loan, 'arrear' => $arrear] = $this->arrearsFixture();
        $request = WriteOffRequest::create([
            'loan_id'        => $loan->id,
            'arrear_case_id' => $arrear->id,
            'amount'         => 79_934,
            'reason'         => 'Uncollectable after field effort',
            'status'         => WriteOffRequest::STATUS_RECOMMENDED,
            'recommended_by' => $admin->id,
            'recommended_at' => now(),
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.write-off-requests.show', $request))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Write-off request #'.$request->id, $html);
        $this->assertStringContainsString('tracking-[0.2em]', $html);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.write-off-requests.index'))
            ->assertOk()
            ->assertSee('Write-off requests', false);
    }

    public function test_recovery_assignment_uses_premium_letterhead(): void
    {
        ['admin' => $admin, 'arrear' => $arrear] = $this->arrearsFixture();
        $partner = Partner::create([
            'vendor_number' => 'PTR-OPS-RC',
            'name'          => 'Call Center Partner',
            'category'      => 'call_center',
            'status'        => 'active',
            'phone'         => '255712346110',
        ]);
        $assignment = RecoveryAssignment::create([
            'arrear_case_id'       => $arrear->id,
            'vendor_id'            => $partner->id,
            'partner_type'         => 'call_center',
            'status'               => RecoveryAssignment::STATUS_ASSIGNED,
            'original_outstanding' => 79_934,
            'assigned_at'          => now(),
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.recovery.assignments.show', $assignment))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Recovery assignment #'.$assignment->id, $html);
        $this->assertStringContainsString('tracking-[0.2em]', $html);
    }

    public function test_restructure_and_top_up_use_premium_letterhead(): void
    {
        ['admin' => $admin, 'loan' => $loan] = $this->arrearsFixture();
        $restructure = RestructureRequest::create([
            'loan_id'            => $loan->id,
            'customer_id'        => $loan->customer_id,
            'reason'             => 'Cash flow gap',
            'restructure_type'   => 'extend_term',
            'new_tenure_months'  => 12,
            'status'             => 'pending',
        ]);
        $topUp = LoanTopUpRequest::create([
            'loan_id'          => $loan->id,
            'customer_id'      => $loan->customer_id,
            'requested_amount' => 50_000,
            'reason'           => 'Working capital',
            'status'           => 'pending',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.restructure-requests.show', $restructure))
            ->assertOk()
            ->assertSee('Restructure request #'.$restructure->id, false)
            ->assertSee('tracking-[0.2em]', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.top-up-requests.show', $topUp))
            ->assertOk()
            ->assertSee('Top-up request #'.$topUp->id, false)
            ->assertSee('tracking-[0.2em]', false);
    }

    public function test_loan_and_application_queues_use_premium_letterhead(): void
    {
        $admin = $this->staff();

        foreach ([
            route('admin.loans.index') => 'All loans',
            route('admin.loans.active') => 'Active loans',
            route('admin.loans.arrears') => 'Loans in arrears',
            route('admin.loan-applications.index') => 'Loan applications',
            route('admin.loan-applications.pipeline.under-review') => 'Credit screening',
            route('admin.partners.index') => 'Partners hub',
        ] as $url => $title) {
            $html = $this->actingAs($admin, 'admin')
                ->get($url)
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString($title, $html, $url);
            $this->assertStringContainsString('tracking-[0.2em]', $html, $url);
        }
    }

    public function test_money_ledger_repayment_queue_survives_direction_in_query(): void
    {
        $admin = $this->staff();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.ledger', [
                'direction' => 'in',
                'tab' => 'repayment_queue',
            ]))
            ->assertOk();
    }

    public function test_penalty_policy_can_be_resolved_from_container(): void
    {
        $policy = app(LoanPenaltyPolicy::class);

        $this->assertInstanceOf(LoanPenaltyPolicy::class, $policy);
        $this->assertGreaterThanOrEqual(0, $policy->graceDaysAfterDefault);
    }
}
