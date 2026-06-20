<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\Setting;
use App\Models\User;
use App\Services\PostApprovalFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase45FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_approval_fee_can_be_waived_per_loan(): void
    {
        $product = LoanProduct::create([
            'code'              => 'P45',
            'name'              => 'Test product',
            'category'          => 'personal',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $customer = Customer::create([
            'customer_number' => 'CU-P45-F',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Fee',
            'last_name'       => 'Test',
            'phone'           => '255700000046',
        ]);
        $application = LoanApplication::create([
            'customer_id'        => $customer->id,
            'loan_product_id'    => $product->id,
            'application_number' => 'APP-P45-001',
            'status'             => 'approved',
            'current_stage'      => 'approval',
            'requested_amount'   => 500_000,
            'requested_tenure_months' => 12,
        ]);

        $fee = LoanApplicationPostApprovalFee::create([
            'loan_application_id' => $application->id,
            'code'                => 'GPS',
            'name'                => 'GPS fee',
            'fee_type'            => 'fixed',
            'configured_amount'   => 50_000,
            'calculated_amount'   => 50_000,
            'status'              => 'pending',
        ]);

        $admin = User::factory()->create(['role' => 'super_admin']);

        app(PostApprovalFeeService::class)->waiveApplicationFee($fee, $admin, 'UAT waiver');

        $fee->refresh();
        $this->assertSame('waived', $fee->status);
        $this->assertSame(0.0, (float) $fee->calculated_amount);
    }

    public function test_repayment_maker_checker_holds_pending_until_approval(): void
    {
        Setting::setMany(['finance.repayment_approval_required' => true]);

        $branch = Branch::create(['code' => 'B45', 'name' => 'Main', 'region' => 'Dar', 'is_active' => true]);
        $product = LoanProduct::create([
            'code'              => 'P45-R',
            'name'              => 'Repayment product',
            'category'          => 'personal',
            'is_active'         => true,
            'interest_rate'     => 0.15,
            'min_amount'        => 100_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);
        $customer = Customer::create([
            'branch_id'       => $branch->id,
            'customer_number' => 'CU-P45',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Test',
            'last_name'       => 'Borrower',
            'phone'           => '255700000045',
        ]);
        $loan = Loan::create([
            'customer_id'         => $customer->id,
            'loan_product_id'     => $product->id,
            'loan_number'         => 'LN-P45-001',
            'principal_amount'    => 1_000_000,
            'approved_amount'     => 1_000_000,
            'outstanding_balance' => 1_000_000,
            'interest_rate'       => 0.05,
            'tenure_months'       => 12,
            'status'              => 'active',
        ]);

        $recorder = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create(['role' => 'admin']);

        $this->actingAs($recorder, 'admin')
            ->post(route('admin.repayments.store'), [
                'loan_id'  => $loan->id,
                'channel'  => 'cash',
                'amount'   => 100_000,
                'status'   => 'pending',
                'paid_at'  => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $repayment = Repayment::query()->where('loan_id', $loan->id)->latest()->first();
        $this->assertSame('pending', $repayment->status);
        $this->assertSame($recorder->id, $repayment->recorded_by);

        $selfApprove = $this->actingAs($recorder, 'admin')
            ->from(route('admin.repayments.show', $repayment))
            ->post(route('admin.repayments.approve', $repayment));

        $selfApprove->assertRedirect(route('admin.repayments.show', $repayment));
        $selfApprove->assertSessionHas('error');

        $this->actingAs($approver, 'admin')
            ->post(route('admin.repayments.approve', $repayment))
            ->assertRedirect();

        $repayment->refresh();
        $this->assertSame('allocated', $repayment->status);
        $this->assertSame($approver->id, $repayment->approved_by);
        $this->assertNotNull($repayment->approved_at);
    }
}
