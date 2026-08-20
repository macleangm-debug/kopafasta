<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\Department;
use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Models\Vendor;
use App\Services\CollateralSecureService;
use App\Services\ValuationPartnerService;
use Database\Seeders\ValuationPricingDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValuerCoverageGapFeatureTest extends TestCase
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
            'customer_number' => 'CU-VG-'.random_int(1000, 9999),
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

    private function installment(Customer $customer): LoanApplication
    {
        $product = LoanProduct::create([
            'code' => 'IL-VG-'.random_int(100, 999),
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
            'application_number' => 'APP-VG-'.random_int(100, 999),
            'status' => 'under_review',
            'current_stage' => 'screening',
            'requested_amount' => 2_000_000,
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

    public function test_borrower_is_told_when_no_valuer_covers_the_region(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin']);

        app(CollateralSecureService::class)->requestValuation($application, $admin);
        app(CollateralSecureService::class)->markValuationFeePaid($application->fresh());

        $this->assertSame(0, ValuationAssignment::query()->where('loan_application_id', $application->id)->count());

        $wait = app(ValuationPartnerService::class)->borrowerWaitView($application->fresh());
        $this->assertTrue($wait['show'] ?? false);
        $this->assertTrue($wait['unassigned'] ?? false);
        $this->assertTrue($wait['no_regional_cover'] ?? false);

        $vm = app(CollateralSecureService::class)->viewModel($application->fresh());
        $this->assertTrue($vm['no_regional_cover'] ?? false);

        $this->assertSame(1, NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', 'collateral_valuer_unassigned')
            ->count());
    }

    public function test_creating_and_activating_a_nationwide_valuer_assigns_waiting_files(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin']);

        app(CollateralSecureService::class)->requestValuation($application, $admin);
        app(CollateralSecureService::class)->markValuationFeePaid($application->fresh());
        $this->assertSame(0, ValuationAssignment::query()->where('loan_application_id', $application->id)->count());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'First Valuer Co',
                'legal_name' => 'First Valuer Co',
                'category' => 'valuer',
                'status' => 'inactive',
                'phone' => '255712349999',
                'email' => 'valuer@example.com',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'activate_now',
                'activation_pin' => '4321',
                'notify_partner' => '0',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'First Valuer Co')->first();
        $this->assertNotNull($partner);
        $this->assertSame('active', $partner->status);

        $assignment = ValuationAssignment::query()
            ->where('loan_application_id', $application->id)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertSame($partner->id, $assignment->vendor_id ?? $assignment->partner_id);

        $this->assertSame(1, NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', 'collateral_valuer_assigned')
            ->count());

        $wait = app(ValuationPartnerService::class)->borrowerWaitView($application->fresh());
        $this->assertFalse($wait['unassigned'] ?? true);
        $this->assertSame('First Valuer Co', $wait['valuer_name'] ?? null);
    }

    public function test_draft_valuer_does_not_take_waiting_files_until_activated(): void
    {
        $customer = $this->borrower();
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin']);

        app(CollateralSecureService::class)->requestValuation($application, $admin);
        app(CollateralSecureService::class)->markValuationFeePaid($application->fresh());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Draft Valuer Co',
                'category' => 'valuer',
                'status' => 'inactive',
                'phone' => '255712349998',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'draft',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status) => str_contains((string) $status, 'activate the portal PIN'));

        $this->assertSame(0, ValuationAssignment::query()->where('loan_application_id', $application->id)->count());
    }

    public function test_screening_asks_partners_management_instead_of_creating_a_valuer(): void
    {
        $customer = $this->borrower();
        $customer->update(['region' => 'Kigoma']);
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $officer = User::factory()->create(['role' => 'officer', 'is_active' => true]);

        app(CollateralSecureService::class)->requestValuation($application, $admin);
        app(CollateralSecureService::class)->markValuationFeePaid($application->fresh());

        $this->actingAs($officer, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'desk_phase' => 'security',
                'open_group' => 'collateral',
            ]))
            ->assertOk()
            ->assertSee('Ask Partners team to add a valuer', false)
            ->assertSee('Screening does not enroll partners', false)
            ->assertDontSee('Create this partner?', false);

        $this->actingAs($officer, 'admin')
            ->from(route('admin.loan-applications.show', $application))
            ->post(route('admin.loan-applications.request-partner-coverage', $application), [
                'category' => 'valuer',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertTrue((bool) data_get($application->screening_payload, 'partner_coverage_open'));
        $this->assertSame('valuer', data_get($application->screening_payload, 'partner_coverage_requests.0.category'));
        $this->assertSame('Kigoma', data_get($application->screening_payload, 'partner_coverage_requests.0.region'));
        $this->assertSame(1, NotificationLog::query()->where('template', 'partner.coverage_staff')->count());

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'desk_phase' => 'security',
                'open_group' => 'collateral',
            ]))
            ->assertOk()
            ->assertSee('Review coverage request', false)
            ->assertSee('/admin/partners/coverage-requests/'.$application->id, false);
    }

    public function test_credit_manager_asks_partners_management_instead_of_adding_a_valuer(): void
    {
        $customer = $this->borrower();
        $customer->update(['region' => 'Kigoma']);
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        app(CollateralSecureService::class)->requestValuation($application, $admin);
        app(CollateralSecureService::class)->markValuationFeePaid($application->fresh());

        $this->actingAs($manager, 'admin')
            ->get(route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'checklist',
                'desk_phase' => 'security',
                'open_group' => 'collateral',
            ]))
            ->assertOk()
            ->assertSee('Ask Partners team to add a valuer', false)
            ->assertDontSee('Create this partner?', false);
    }

    public function test_partners_team_officer_can_open_the_add_valuer_form(): void
    {
        $department = Department::create([
            'code' => 'PRT',
            'name' => 'Partner Operations',
            'is_active' => true,
        ]);
        $officer = User::factory()->create([
            'role' => 'officer',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $this->actingAs($officer, 'admin')
            ->get(route('admin.partners.create', ['category' => 'valuer', 'region' => 'Kigoma']))
            ->assertOk()
            ->assertSee('Create this partner?', false);
    }

    public function test_partners_team_can_add_the_missing_region_on_an_existing_valuer(): void
    {
        $customer = $this->borrower();
        $customer->update(['region' => 'Kigoma']);
        $application = $this->installment($customer);
        $this->pledge($application, $customer);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $officer = User::factory()->create(['role' => 'officer', 'is_active' => true]);

        $valuer = Vendor::query()->create([
            'name' => 'Lake Zone Valuers',
            'category' => 'valuer',
            'status' => 'active',
            'vendor_number' => 'PTR-VG-'.random_int(1000, 9999),
            'phone' => '255712340010',
            'coverage_type' => 'regions',
            'regions' => ['Dar es Salaam'],
            'metadata' => ['residence' => ['region' => 'Kigoma']],
        ]);

        app(CollateralSecureService::class)->requestValuation($application, $admin);
        app(CollateralSecureService::class)->markValuationFeePaid($application->fresh());

        $this->actingAs($officer, 'admin')
            ->post(route('admin.loan-applications.request-partner-coverage', $application), [
                'category' => 'valuer',
            ])
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.partners.coverage-request', $application))
            ->assertOk()
            ->assertSee('Lake Zone Valuers', false)
            ->assertSee('Based in Kigoma', false)
            ->assertSee('Add Kigoma', false)
            ->assertSee('Enroll a new valuer', false);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.coverage-request.add-region', [$application, $valuer]))
            ->assertRedirect(route('admin.partners.coverage-request', $application));

        $this->assertContains('Kigoma', $valuer->fresh()->regions ?? []);
        $this->assertNotNull(
            ValuationAssignment::query()
                ->where('loan_application_id', $application->id)
                ->where(function ($q) use ($valuer) {
                    $q->where('vendor_id', $valuer->id)->orWhere('partner_id', $valuer->id);
                })
                ->first()
        );
    }
}
