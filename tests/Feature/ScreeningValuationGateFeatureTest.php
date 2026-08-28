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
use Tests\Support\CompletesPartnerJobs;
use Tests\TestCase;

class ScreeningValuationGateFeatureTest extends TestCase
{
    use CompletesPartnerJobs;
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
        $asset = CustomerAsset::create(array_merge([
            'customer_id' => $customer->id,
        ], $this->completeVehicleAssetAttributes([
            'label' => 'Toyota',
        ])));

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
        $this->completePartnerForJobs($valuer);

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
        $this->completePartnerForJobs($valuer);
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
        $this->completePartnerForJobs($valuer);
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

    public function test_attaching_an_asset_opens_the_valuation_fee_without_admin_cta(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer, 800_000);
        $asset = CustomerAsset::create(array_merge([
            'customer_id' => $customer->id,
        ], $this->completeVehicleAssetAttributes([
            'label' => 'Rav4',
        ])));

        app(\App\Services\CustomerAssetService::class)->attachToApplication($asset, $application, $customer);

        $state = app(CollateralSecureService::class)->state($application->fresh());
        $this->assertSame(CollateralSecureService::STATUS_AWAITING_VALUATION_FEE, $state['status'] ?? null);
        $this->assertSame(CollateralSecureService::PATH_SCREENING_VALUATION, $state['path'] ?? null);

        $next = app(\App\Services\LoanApplicationNextActionService::class)
            ->forApplication($customer, $application->fresh());
        $this->assertSame('pay_valuation_fee', $next['code'] ?? null);
    }

    public function test_reattaching_already_linked_asset_opens_the_valuation_fee_gate(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer, 800_000);
        $asset = $this->pledge($application, $customer);

        $this->assertNull(app(CollateralSecureService::class)->state($application->fresh()));

        app(\App\Services\CustomerAssetService::class)->attachToApplication($asset, $application, $customer);

        $state = app(CollateralSecureService::class)->state($application->fresh());
        $this->assertSame(CollateralSecureService::STATUS_AWAITING_VALUATION_FEE, $state['status'] ?? null);
    }

    public function test_group_loan_attach_opens_valuation_fee_for_the_leader(): void
    {
        $customer = $this->borrower();
        $product = LoanProduct::create([
            'code' => 'GL',
            'name' => 'Group',
            'is_active' => true,
            'interest_rate' => 0.15,
            'min_amount' => 100_000,
            'max_amount' => 10_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 12,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-GL-'.random_int(100, 999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 2_000_000,
            'requested_tenure_months' => 6,
            'submitted_at' => now(),
        ]);
        $asset = CustomerAsset::create(array_merge([
            'customer_id' => $customer->id,
        ], $this->completeVehicleAssetAttributes([
            'label' => 'Rav4',
        ])));

        app(\App\Services\CustomerAssetService::class)->attachToApplication($asset, $application, $customer);

        $state = app(CollateralSecureService::class)->state($application->fresh());
        $this->assertSame(CollateralSecureService::STATUS_AWAITING_VALUATION_FEE, $state['status'] ?? null);
        $this->assertTrue(app(CollateralSecureService::class)->needsValuationFeePayment($application->fresh()));
    }

    public function test_heal_opens_valuation_fee_when_pledge_exists_without_pay_card(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer, 800_000);
        $this->pledge($application, $customer);
        app(\App\Services\CustomerAssetService::class)->persistOnLoanIds($application, [
            (int) $application->collateralAssets()->value('customer_asset_id'),
        ]);

        $this->assertNull(app(CollateralSecureService::class)->state($application->fresh()));

        $this->assertTrue(app(CollateralSecureService::class)->needsValuationFeePayment($application->fresh()));
        $this->assertSame(
            CollateralSecureService::STATUS_AWAITING_VALUATION_FEE,
            app(CollateralSecureService::class)->state($application->fresh())['status'] ?? null
        );
    }

    public function test_checklist_system_marks_fee_and_awaits_missing_fsv(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        app(CollateralSecureService::class)->promptValuationFeeAfterPledge($application->fresh());
        $application = $application->fresh();

        $admin = User::factory()->create(['role' => 'admin']);
        $vm = app(\App\Services\ScreeningChecklistService::class)
            ->viewModel($application, $admin, 'borrower', null, null, ['customer' => $customer]);
        $collateral = collect($vm['groups'] ?? [])->firstWhere('key', 'collateral');
        $this->assertNotNull($collateral);
        $fee = collect($collateral['items'] ?? [])->firstWhere('key', 'collateral.valuation_fee');
        $this->assertSame('fail', $fee['verdict'] ?? null);
        $this->assertTrue($fee['catalog_system'] ?? false);
        $this->assertFalse($fee['awaiting_data'] ?? false);

        $report = collect($collateral['items'] ?? [])->firstWhere('key', 'collateral.valuation_report');
        $this->assertTrue($report['awaiting_data'] ?? false);
        $this->assertSame('There is no data for this checklist', $report['awaiting_message'] ?? null);

        $photos = collect($collateral['items'] ?? [])->firstWhere('key', 'collateral.valuation_or_photos');
        $this->assertSame('photo_pairs', $photos['evidence']['layout'] ?? null);
        $this->assertNotEmpty($photos['evidence']['photo_pairs'] ?? []);
        $this->assertFalse($photos['catalog_system'] ?? false);
        $this->assertFalse($photos['awaiting_data'] ?? true);
        $this->assertNotSame('pass', $photos['verdict'] ?? null);
    }

    public function test_photo_match_shows_valuer_extras_and_stays_human(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer);
        $asset = $this->pledge($application, $customer);
        app(\App\Services\CustomerAssetService::class)->persistOnLoanIds($application, [(int) $asset->id]);

        $valuer = Vendor::create([
            'vendor_number' => 'V-PHOTO-'.random_int(100, 999),
            'name' => 'Geofrey Mwaijjonga',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        $task = \App\Models\PartnerTask::query()->create([
            'partner_id' => $valuer->id,
            'loan_application_id' => $application->id,
            'task_type' => 'asset_valuation',
            'status' => 'completed',
        ]);
        ValuationAssignment::query()->create([
            'loan_application_id' => $application->id,
            'vendor_id' => $valuer->id,
            'vendor_task_id' => $task->id,
            'status' => ValuationAssignment::STATUS_COMPLETED,
            'market_value' => 20_000_000,
            'forced_sale_value' => 15_000_000,
            'completed_at' => now(),
        ]);
        foreach (['front', 'dashboard', 'engine', 'vin'] as $angle) {
            \App\Models\PartnerDocument::query()->create([
                'vendor_id' => $valuer->id,
                'vendor_task_id' => $task->id,
                'doc_type' => 'valuer_photo_'.$angle.'_'.$asset->id,
                'label' => ucfirst($angle).' #'.$asset->id,
                'file_path' => 'valuer/'.$angle.'.jpg',
            ]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $vm = app(\App\Services\ScreeningChecklistService::class)
            ->viewModel($application->fresh(), $admin, 'borrower', null, null, ['customer' => $customer]);
        $photos = collect(collect($vm['groups'] ?? [])->firstWhere('key', 'collateral')['items'] ?? [])
            ->firstWhere('key', 'collateral.valuation_or_photos');

        $this->assertFalse($photos['awaiting_data'] ?? true);
        $this->assertNotSame('pass', $photos['verdict'] ?? null);
        $angles = collect($photos['evidence']['photo_pairs'] ?? [])->pluck('angle');
        $this->assertTrue($angles->contains('dashboard'));
        $this->assertTrue($angles->contains('engine'));
        $this->assertTrue($angles->contains('vin'));
        $this->assertTrue(
            collect($photos['evidence']['photo_pairs'] ?? [])->contains(fn ($pair) => ! empty($pair['extra']))
        );
        $dashboard = collect($photos['evidence']['photo_pairs'] ?? [])->firstWhere('angle', 'dashboard');
        $this->assertNotEmpty($dashboard['valuer']['url'] ?? null);
        $this->assertEmpty($dashboard['borrower']['url'] ?? null);
    }
}
