<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\VendorTask;
use App\Services\CollateralInsurancePartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CompletesPartnerJobs;
use Tests\TestCase;

class InsuranceCoverWalletAccrualFeatureTest extends TestCase
{
    use CompletesPartnerJobs;
    use RefreshDatabase;

    public function test_premium_accrues_on_complete_as_base_only_not_on_open(): void
    {
        Setting::setMany([
            'partner_defaults.insurance.rate_percent' => 3.5,
            'partner_defaults.insurance.has_markup' => true,
            'partner_defaults.insurance.markup_percent' => 1,
            'underwriting.collateral_insurance_rate_percent' => 3.5,
            'underwriting.collateral_insurance_markup_percent' => 1,
        ]);

        $branch = Branch::create([
            'code' => 'ICW1',
            'name' => 'Insurance Wallet Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);

        $product = LoanProduct::create([
            'code' => 'IL-ICW',
            'name' => 'Individual Loan',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 20_000,
        ]);

        $borrower = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-ICW-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Borrower',
            'last_name' => 'One',
            'phone' => '255711000111',
            'region' => 'Dar',
            'branch_id' => $branch->id,
        ]);

        $application = LoanApplication::create([
            'customer_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-ICW-1',
            'requested_amount' => 500_000,
            'requested_tenure_months' => 6,
            'status' => 'under_review',
            'current_stage' => 'screening',
            'submitted_at' => now(),
            'application_fee_amount' => 20_000,
            'application_fee_status' => 'paid',
        ]);

        $asset = CustomerAsset::create([
            'customer_id' => $borrower->id,
            'asset_type' => 'vehicle',
            'label' => 'Vitz Wallet',
            'registration_number' => 'T100ICW',
            'is_active' => true,
            'metadata' => ['details' => []],
        ]);

        $partner = $this->completePartnerForJobs(Vendor::create([
            'vendor_number' => 'PT-IN-ICW',
            'name' => 'Aventris Test',
            'category' => 'insurance',
            'applicant_category' => 'company',
            'roles' => ['insurance'],
            'status' => 'active',
            'phone' => '255712000222',
            'coverage_type' => 'nationwide',
            'activated_at' => now(),
        ]));

        $payment = CustomerPayment::create([
            'reference' => 'INS-ICW-1',
            'customer_id' => $borrower->id,
            'payment_type' => 'insurance_premium',
            'payment_method' => 'mobile_money',
            'amount' => 45000,
            'currency' => 'TZS',
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        $service = app(CollateralInsurancePartnerService::class);
        $quote = $service->quote(1_000_000);
        $this->assertSame(35000, $quote['base_premium']);
        $this->assertSame(10000, $quote['markup_amount']); // 1% of insured value
        $this->assertSame(45000, $quote['premium']);
        $this->assertSame(4.5, $quote['effective_rate_percent']);

        $opened = $service->openCoverCase($application, $asset, 1_000_000, $borrower, $payment);
        $task = $opened['task'];
        $this->assertInstanceOf(VendorTask::class, $task);
        $this->assertSame($partner->id, $task->vendor_id);
        $this->assertSame(35000, (int) $task->fee_amount);
        $this->assertSame(0, VendorPayment::query()->where('partner_task_id', $task->id)->count());

        $service->completeCover($task->fresh(), now()->addYear()->toDateString(), 'POL-ICW-1');

        $walletLine = VendorPayment::query()
            ->where('partner_task_id', $task->id)
            ->where('source_type', 'insurance_premium')
            ->first();

        $this->assertNotNull($walletLine);
        $this->assertSame(35000, (int) $walletLine->amount);
        $this->assertSame('pending', $walletLine->status);

        // Idempotent — recording again must not double-credit.
        $service->completeCover($task->fresh(), now()->addYear()->toDateString(), 'POL-ICW-1');
        $this->assertSame(1, VendorPayment::query()
            ->where('partner_task_id', $task->id)
            ->where('source_type', 'insurance_premium')
            ->where('status', '!=', 'cancelled')
            ->count());
    }
}
