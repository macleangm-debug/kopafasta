<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Models\Vendor;
use App\Services\CollateralCoverageService;
use App\Services\CollateralSecureService;
use App\Services\ValuationPartnerService;
use Database\Seeders\ValuationPricingDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningValuationGateFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ValuationPricingDefaultsSeeder::class);
    }

    private function borrower(): Customer
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(\App\Services\PinService::class)->setPin($user, '1234');

        return Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CU-VAL-'.random_int(100, 999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Leader',
            'last_name' => 'Pay',
            'phone' => '25571234'.random_int(1000, 9999),
            'region' => 'Dar es Salaam',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);
    }

    private function installment(Customer $customer, int $amount = 2_000_000): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'IL-VAL-'.random_int(100, 999),
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 10_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);

        return LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-VAL-'.random_int(100, 999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => $amount,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
    }

    private function pledge(LoanApplication $application, Customer $customer): CustomerAsset
    {
        $asset = CustomerAsset::create([
            'customer_id' => $customer->id,
            'asset_type' => 'vehicle',
            'label' => 'Toyota',
            'is_active' => true,
            'registration_number' => 'T123ABC',
            'photo_paths' => ['assets/car.jpg'],
            'metadata' => [
                'details' => ['insurance_expires_at' => now()->addYears(3)->toDateString()],
                'insurance_document_path' => 'assets/ins.pdf',
            ],
        ]);

        LoanApplicationAsset::create([
            'loan_application_id' => $application->id,
            'customer_asset_id' => $asset->id,
            'asset_type' => 'vehicle',
            'uw_status' => LoanApplicationAsset::UW_PENDING,
        ]);

        return $asset;
    }

    public function test_partners_hub_has_origination_auto_assign(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.origination-auto-assign'))
            ->assertOk()
            ->assertSee('Origination auto-assignment', false)
            ->assertSee('Valuation partner', false);
    }

    public function test_send_to_valuer_requests_payment_and_does_not_assign(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin']);

        Vendor::create([
            'vendor_number' => 'V-DSM',
            'name' => 'Dar Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'regions' => ['Dar es Salaam'],
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-applications.request-valuation', $application))
            ->assertRedirect();

        $state = app(CollateralSecureService::class)->state($application->fresh());
        $this->assertSame(CollateralSecureService::PATH_SCREENING_VALUATION, $state['path'] ?? null);
        $this->assertSame(CollateralSecureService::STATUS_AWAITING_VALUATION_FEE, $state['status'] ?? null);
        $this->assertSame(0, ValuationAssignment::query()->where('loan_application_id', $application->id)->count());

        $next = app(\App\Services\LoanApplicationNextActionService::class)
            ->forApplication($customer, $application);
        $this->assertSame('pay_valuation_fee', $next['code'] ?? null);
    }

    public function test_assign_is_blocked_until_valuation_fee_is_paid(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin']);
        $valuer = Vendor::create([
            'vendor_number' => 'V-BLK',
            'name' => 'Blocked Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'regions' => ['Dar es Salaam'],
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ValuationPartnerService::class)->assign($application, $valuer, $admin);
    }

    public function test_fsv_below_requested_amount_is_shortfall_not_insurance(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer, 5_000_000);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin']);
        app(CollateralSecureService::class)->requestValuation($application, $admin);
        $application->refresh();
        $state = app(CollateralSecureService::class)->state($application);
        $state['valuation_fee_paid_at'] = now()->toIso8601String();
        $state['status'] = CollateralSecureService::STATUS_AWAITING_VALUER;
        $payload = $application->screening_payload;
        $payload['collateral_secure'] = $state;
        $application->update(['screening_payload' => $payload]);

        $valuer = Vendor::create([
            'vendor_number' => 'V-FSV',
            'name' => 'FSV Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'regions' => ['Dar es Salaam'],
        ]);
        $assignment = app(ValuationPartnerService::class)->assign($application->fresh(), $valuer, $admin);
        app(ValuationPartnerService::class)->complete($assignment, 1_000_000, 800_000);

        $coverage = app(CollateralCoverageService::class)->forApplication($application->fresh());
        $this->assertFalse($coverage['sufficient'] ?? true);
        $this->assertSame(CollateralCoverageService::NEXT_ADD_COLLATERAL, $coverage['next'] ?? null);
        $this->assertSame(
            CollateralSecureService::STATUS_SHORTFALL,
            app(CollateralSecureService::class)->state($application->fresh())['status'] ?? null
        );
    }

    public function test_fsv_covering_requested_amount_then_checks_insurance(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer, 400_000);
        $asset = $this->pledge($application, $customer);
        $asset->update(['metadata' => ['details' => []]]);
        $admin = User::factory()->create(['role' => 'admin']);
        app(CollateralSecureService::class)->requestValuation($application, $admin);
        $application->refresh();
        $state = app(CollateralSecureService::class)->state($application);
        $state['valuation_fee_paid_at'] = now()->toIso8601String();
        $state['status'] = CollateralSecureService::STATUS_AWAITING_VALUER;
        $payload = $application->screening_payload;
        $payload['collateral_secure'] = $state;
        $application->update(['screening_payload' => $payload]);

        $valuer = Vendor::create([
            'vendor_number' => 'V-INS',
            'name' => 'Ins Valuer',
            'category' => 'valuer',
            'status' => 'active',
            'regions' => ['Dar es Salaam'],
        ]);
        $assignment = app(ValuationPartnerService::class)->assign($application->fresh(), $valuer, $admin);
        app(ValuationPartnerService::class)->complete($assignment, 2_000_000, 1_500_000);

        $coverage = app(CollateralCoverageService::class)->forApplication($application->fresh());
        $this->assertTrue($coverage['sufficient'] ?? false);
        $this->assertSame(CollateralCoverageService::NEXT_INSURANCE, $coverage['next'] ?? null);
        $this->assertSame(
            CollateralSecureService::STATUS_AWAITING_INSURANCE,
            app(CollateralSecureService::class)->state($application->fresh())['status'] ?? null
        );
    }

    public function test_pledging_an_asset_opens_the_valuation_fee_gate(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer, 800_000);
        $this->pledge($application, $customer);

        app(CollateralSecureService::class)->promptValuationFeeAfterPledge($application->fresh());

        $state = app(CollateralSecureService::class)->state($application->fresh());
        $this->assertContains($state['status'] ?? null, [
            CollateralSecureService::STATUS_AWAITING_VALUATION_FEE,
            CollateralSecureService::STATUS_AWAITING_VALUER,
        ]);
        $this->assertSame(CollateralSecureService::PATH_SCREENING_VALUATION, $state['path'] ?? null);
    }
}
