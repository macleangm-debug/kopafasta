<?php

namespace Tests\Feature;

use App\Models\ArrearCase;
use App\Models\Branch;
use App\Models\CollectionAction;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\Partner;
use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerEfficiencyService;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerSupportHubUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartmentSeeder::class);
    }

    public function test_partner_support_can_edit_legacy_equipment_marketplace_asset(): void
    {
        $user = $this->partnerSupport();
        $asset = MarketplaceAsset::create([
            'slug'               => 'maize-milling-plant',
            'title'              => 'Maize Milling Plant',
            'category'           => 'equipment',
            'supplier_name'      => 'AgriEquip TZ',
            'asset_value'        => 12_500_000,
            'supplier_deposit'   => 2_500_000,
            'customer_deposit'   => 2_750_000,
            'weekly_installment' => 240_000,
            'max_tenure_months'  => 12,
            'is_active'          => true,
            'photos'             => ['/images/marketplace/mill.jpg'],
        ]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.marketplace-assets.edit', $asset))
            ->assertOk()
            ->assertSee('Maize Milling Plant', false)
            ->assertSee('Deposit (% of asset value)', false);
    }

    public function test_marketplace_index_is_premium_and_hides_settings_chrome(): void
    {
        $user = $this->partnerSupport();

        $this->actingAs($user, 'admin')
            ->get(route('admin.marketplace-assets.index'))
            ->assertOk()
            ->assertSee('Marketplace assets', false)
            ->assertDontSee('Identity &amp; compliance', false)
            ->assertDontSee('Integrations', false)
            ->assertDontSee('Settings hub', false)
            ->assertDontSee('Document templates', false);
    }

    public function test_marketplace_show_is_grouped_for_admin_and_partner_support(): void
    {
        $asset = MarketplaceAsset::create([
            'slug'               => 'view-layout-truck',
            'title'              => 'View Layout Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Fleet TZ',
            'asset_value'        => 8_000_000,
            'supplier_deposit'   => 1_600_000,
            'customer_deposit'   => 1_760_000,
            'weekly_installment' => 180_000,
            'max_tenure_months'  => 12,
            'is_active'          => true,
        ]);

        foreach (['admin', 'partner_support'] as $role) {
            $actor = $role === 'admin' ? $this->admin() : $this->partnerSupport();
            $this->actingAs($actor, 'admin')
                ->get(route('admin.marketplace-assets.show', $asset))
                ->assertOk()
                ->assertSee('View Layout Truck', false)
                ->assertSee('Listing', false)
                ->assertSee('Pricing', false)
                ->assertSee('Cover', false)
                ->assertDontSee('view-layout-truck', false);
        }
    }

    public function test_partners_hub_does_not_show_duties_copy(): void
    {
        $this->actingAs($this->partnerSupport(), 'admin')
            ->get(route('admin.partners.index'))
            ->assertOk()
            ->assertDontSee('Partner support duties', false)
            ->assertDontSee('Do not screen, approve, or reject the loan', false)
            ->assertSee('Partner efficiency', false)
            ->assertDontSee('Partner auto-assignment', false);
    }

    public function test_partners_hub_opens_profile_instead_of_inline_edit(): void
    {
        $partner = Vendor::create([
            'name' => 'Kigoma Field Valuer',
            'category' => 'valuer',
            'status' => 'inactive',
            'partner_number' => 'PT-VL-TZ-HUB1',
            'phone' => '255784275297',
        ]);

        $this->actingAs($this->partnerSupport(), 'admin')
            ->get(route('admin.partners.index'))
            ->assertOk()
            ->assertSee('Kigoma Field Valuer', false)
            ->assertSee(route('admin.partners.show', $partner), false)
            ->assertSee('>Performance</th>', false)
            ->assertDontSee('>Phone</th>', false)
            ->assertDontSee('>Edit</a>', false)
            ->assertDontSee(route('admin.partners.edit', $partner), false);

        $this->actingAs($this->partnerSupport(), 'admin')
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Edit', false)
            ->assertSee(route('admin.partners.edit', $partner), false)
            ->assertSee('Profile', false)
            ->assertSee('Jobs', false)
            ->assertSee('Performance', false)
            ->assertSee('+255', false)
            ->assertDontSee('784,275,297', false)
            ->assertSee('View only. Use Edit to change', false);
    }

    public function test_partner_support_create_form_hides_rate_overrides(): void
    {
        $this->actingAs($this->partnerSupport(), 'admin')
            ->get(route('admin.partners.create', ['category' => 'valuer']))
            ->assertOk()
            ->assertSee('Platform default rates apply', false)
            ->assertDontSee('Optional partner override', false)
            ->assertDontSee('Service partner default rates', false);
    }

    public function test_partner_support_cannot_persist_negotiated_rates(): void
    {
        $this->actingAs($this->partnerSupport(), 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Kigoma Valuer Desk',
                'category' => 'valuer',
                'status' => 'inactive',
                'phone' => '255712345910',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'draft',
                'partner_cost' => 999999,
                'markup_percent' => 50,
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Kigoma Valuer Desk')->first();
        $this->assertNotNull($partner);
        $this->assertNull($partner->partner_cost);
        $this->assertNull($partner->markup_percent);
    }

    public function test_admin_can_persist_negotiated_rates(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Negotiated Valuer',
                'category' => 'valuer',
                'status' => 'inactive',
                'phone' => '255712345911',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'draft',
                'partner_cost' => 1500,
                'markup_percent' => 12,
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Negotiated Valuer')->first();
        $this->assertNotNull($partner);
        $this->assertEquals(1500, (float) $partner->partner_cost);
        $this->assertEquals(12, (float) $partner->markup_percent);
    }

    public function test_recovery_assignment_index_has_remind_action_and_show_has_borrower_detail(): void
    {
        $fixture = $this->recoveryFixture();
        $user = $this->partnerSupport();

        $this->actingAs($user, 'admin')
            ->get(route('admin.recovery.assignments.index'))
            ->assertOk()
            ->assertSee('Remind partner', false)
            ->assertSee('Ops Borrower', false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.recovery.assignments.show', $fixture['assignment']))
            ->assertOk()
            ->assertSee('Remind partner', false)
            ->assertSee('Remind borrower', false)
            ->assertSee($fixture['customer']->phone, false);

        $this->actingAs($user, 'admin')
            ->post(route('admin.recovery.assignments.remind-partner', $fixture['assignment']))
            ->assertRedirect();

        $this->assertTrue(
            CollectionAction::query()
                ->where('recovery_assignment_id', $fixture['assignment']->id)
                ->where('action_type', 'partner_reminder')
                ->exists()
        );
    }

    public function test_partner_efficiency_board_flags_escalated_partners(): void
    {
        $strong = Partner::create([
            'vendor_number' => 'PT-EFF-OK',
            'name' => 'On Time Valuer',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        $weak = Partner::create([
            'vendor_number' => 'PT-EFF-BAD',
            'name' => 'Late Collector',
            'category' => 'debt_collector',
            'status' => 'active',
        ]);

        for ($i = 0; $i < 4; $i++) {
            PartnerTask::create([
                'partner_id' => $strong->id,
                'task_type' => 'valuation',
                'status' => 'completed',
                'due_at' => now()->addDay(),
                'completed_at' => now(),
            ]);
        }

        $fixture = $this->recoveryFixture();
        $arrearId = $fixture['assignment']->arrear_case_id;

        for ($i = 0; $i < 3; $i++) {
            RecoveryAssignment::create([
                'arrear_case_id' => $arrearId,
                'partner_id' => $weak->id,
                'partner_type' => 'debt_collector',
                'status' => RecoveryAssignment::STATUS_COMPLETED,
                'original_outstanding' => 40_000,
                'assigned_at' => now()->subDays(10),
                'completed_at' => now()->subDays(8),
                'sla_due_at' => now()->subDays(7),
            ]);
            RecoveryAssignment::create([
                'arrear_case_id' => $arrearId,
                'partner_id' => $weak->id,
                'partner_type' => 'debt_collector',
                'status' => RecoveryAssignment::STATUS_ESCALATED,
                'original_outstanding' => 40_000,
                'assigned_at' => now()->subDays(6),
                'sla_due_at' => now()->subDays(4),
            ]);
        }

        $board = app(PartnerEfficiencyService::class)->board();
        $weakRow = $board['rows']->firstWhere(fn ($row) => $row['partner']->is($weak));
        $strongRow = $board['rows']->firstWhere(fn ($row) => $row['partner']->is($strong));

        $this->assertNotNull($weakRow);
        $this->assertSame(PartnerEfficiencyService::BAND_AT_RISK, $weakRow['band']);
        $this->assertTrue($board['coaching']->contains(fn ($row) => $row['partner']->is($weak)));
        $this->assertNotNull($strongRow);
        $this->assertSame(PartnerEfficiencyService::BAND_STRONG, $strongRow['band']);

        $this->actingAs($this->partnerSupport(), 'admin')
            ->get(route('admin.partners.efficiency'))
            ->assertOk()
            ->assertSee('Late Collector', false)
            ->assertSee('Needs coaching', false)
            ->assertSee('On Time Valuer', false);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.partners.efficiency'))
            ->assertOk()
            ->assertSee('Partner efficiency', false);
    }

    public function test_partner_support_cannot_open_settings_or_origination_auto_assign(): void
    {
        $support = $this->partnerSupport();

        $this->actingAs($support, 'admin')
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($support, 'admin')
            ->get(route('admin.partners.origination-auto-assign'))
            ->assertForbidden();

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.settings.account-security'))
            ->assertOk();

        $this->actingAs($support, 'admin')
            ->get(route('admin.settings.account-security'))
            ->assertOk();
    }

    private function partnerSupport(): User
    {
        $prt = Department::query()->where('code', 'PRT')->firstOrFail();
        $user = User::factory()->create([
            'role' => 'partner_support',
            'department_id' => $prt->id,
            'is_active' => true,
        ]);
        $user->departments()->sync([$prt->id]);

        return $user->fresh();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    /** @return array{assignment: RecoveryAssignment, customer: Customer} */
    private function recoveryFixture(): array
    {
        $admin = $this->admin();
        $branch = Branch::create([
            'code' => 'PRT'.random_int(10, 99),
            'name' => 'Partner Branch',
            'region' => 'Dar',
            'is_active' => true,
        ]);
        $product = LoanProduct::create([
            'code' => 'IL-PRT-'.random_int(100, 999),
            'name' => 'Installment',
            'is_active' => true,
            'interest_rate' => 0.18,
            'min_amount' => 50_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
        ]);
        $customer = Customer::create([
            'user_id' => User::factory()->create(['role' => 'borrower'])->id,
            'customer_number' => 'CU-PRT-'.random_int(1000, 9999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Ops',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
            'branch_id' => $branch->id,
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'branch_id' => $branch->id,
            'application_number' => 'APP-PRT-'.random_int(1000, 9999),
            'requested_amount' => 100_000,
            'requested_tenure_months' => 6,
            'status' => 'disbursed',
            'current_stage' => 'disbursement',
        ]);
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-PRT-'.strtoupper(substr(md5((string) random_int(1, 999999)), 0, 4)),
            'principal_amount' => 100_000,
            'approved_amount' => 100_000,
            'outstanding_balance' => 40_000,
            'interest_rate' => 0.18,
            'tenure_months' => 6,
            'status' => 'arrears',
            'disbursement_date' => now()->subMonths(2),
        ]);
        $arrear = ArrearCase::create([
            'loan_id' => $loan->id,
            'days_past_due' => 21,
            'amount_in_arrears' => 40_000,
            'status' => 'open',
            'assigned_to' => $admin->id,
        ]);
        $partner = Partner::create([
            'vendor_number' => 'PTR-REM-01',
            'name' => 'Field Collector Co',
            'category' => 'debt_collector',
            'status' => 'active',
            'phone' => '255712346200',
        ]);
        $assignment = RecoveryAssignment::create([
            'arrear_case_id' => $arrear->id,
            'vendor_id' => $partner->id,
            'partner_type' => 'debt_collector',
            'status' => RecoveryAssignment::STATUS_ASSIGNED,
            'original_outstanding' => 40_000,
            'assigned_at' => now(),
            'sla_due_at' => now()->addDays(3),
        ]);

        return compact('assignment', 'customer');
    }
}
