<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Services\ConsoleNavService;
use App\Services\RoleService;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDeskConsoleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartmentSeeder::class);
    }

    public function test_partner_support_lands_on_a_desk_dashboard(): void
    {
        $user = $this->partnerSupport();

        $this->assertSame('admin.dashboard', app(RoleService::class)->homeRoute($user));

        $this->actingAs($user, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Your dashboard', false)
            ->assertSee('Applications to screen', false)
            ->assertSee('Coverage gaps', false)
            ->assertSee('Asset requests', false)
            ->assertSee('Awaiting activation', false)
            ->assertSee(route('admin.partner-applications.index'), false)
            ->assertSee(route('admin.partners.onboarding'), false)
            ->assertSee(route('admin.partners.index'), false);
    }

    public function test_partner_support_nav_is_the_partner_desk_only(): void
    {
        $user = $this->partnerSupport();
        $labels = collect(app(ConsoleNavService::class)->visibleSections($user))
            ->pluck('label')
            ->all();

        $this->assertSame(['Dashboard', 'Field & recovery', 'Assets', 'Partners'], $labels);

        $partnerItems = collect(app(ConsoleNavService::class)->visibleSections($user))
            ->firstWhere('label', 'Partners')['items'] ?? [];
        $partnerRoutes = array_column($partnerItems, 1);

        $this->assertContains('admin.partners.index', $partnerRoutes);
        $this->assertContains('admin.partner-applications.index', $partnerRoutes);
        $this->assertContains('admin.partners.efficiency', $partnerRoutes);
        $this->assertNotContains('admin.partners.tasks', $partnerRoutes);
        $this->assertNotContains('admin.partners.applications', $partnerRoutes);
        $this->assertNotContains('admin.partner-payout-requests.index', $partnerRoutes);
    }

    public function test_partner_applications_are_a_screening_queue(): void
    {
        $user = $this->partnerSupport();
        $application = PartnerApplication::create([
            'type' => 'service',
            'partner_category' => 'valuer',
            'applicant_category' => 'company',
            'full_name' => 'Kigoma Valuer',
            'email' => 'valuer@example.com',
            'phone' => '255712000222',
            'business_name' => 'Kigoma Valuations Ltd',
            'legal_name' => 'Kigoma Valuations Limited',
            'registration_number' => 'BRELA-88',
            'tin' => '111-222-333',
            'region' => 'Kigoma',
            'coverage_regions' => ['Kigoma'],
            'message' => 'We cover Kigoma region.',
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.partners.applications'))
            ->assertRedirect(route('admin.partner-applications.index'));

        $this->actingAs($user, 'admin')
            ->get(route('admin.partner-applications.index'))
            ->assertOk()
            ->assertSee('Screen what they submitted', false)
            ->assertSee('Kigoma Valuer', false)
            ->assertDontSee('Payouts', false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.partner-applications.show', $application))
            ->assertOk()
            ->assertSee('Kigoma Valuer', false)
            ->assertSee('Kigoma Valuations Ltd', false)
            ->assertSee('We cover Kigoma region.', false)
            ->assertSee('Valuer', false)
            ->assertDontSee('Partner wallet lines', false)
            ->assertDontSee('Halt open tasks', false);
    }

    public function test_approved_applications_leave_the_queue_for_the_partners_hub(): void
    {
        $user = $this->partnerSupport();
        $partner = Partner::create([
            'vendor_number' => 'PT-APP-OK',
            'name' => 'John Mabuga',
            'category' => 'valuer',
            'status' => 'active',
        ]);
        PartnerApplication::create([
            'type' => 'service',
            'partner_category' => 'valuer',
            'applicant_category' => 'individual',
            'full_name' => 'John Mabuga',
            'email' => 'jmabuga@example.com',
            'phone' => '255763234567',
            'business_name' => 'John Mabuga',
            'region' => 'Dodoma',
            'status' => 'approved',
            'partner_id' => $partner->id,
        ]);

        $pending = PartnerApplication::create([
            'type' => 'service',
            'partner_category' => 'valuer',
            'applicant_category' => 'individual',
            'full_name' => 'Neema Valuer',
            'email' => 'neema.valuer@example.com',
            'phone' => '255763234568',
            'business_name' => 'Neema Valuations',
            'region' => 'Mbeya',
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.partner-applications.index'))
            ->assertOk()
            ->assertSee('Neema Valuer', false)
            ->assertSee('Screen', false)
            ->assertDontSee('jmabuga@example.com', false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.partner-applications.index', ['status' => 'approved']))
            ->assertRedirect(route('admin.partners.index'));

        $response = $this->actingAs($user, 'admin')
            ->put(route('admin.partner-applications.update', $pending), [
                'status' => 'approved',
            ]);

        $pending->refresh();
        $this->assertSame('approved', $pending->status);
        $this->assertNotNull($pending->partner_id);
        $response->assertRedirect(route('admin.partners.show', $pending->partner_id));

        $this->actingAs($user, 'admin')
            ->get(route('admin.partner-applications.index'))
            ->assertDontSee('neema.valuer@example.com', false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.partners.show', $pending->partner_id))
            ->assertOk()
            ->assertSee('Neema Valuations', false)
            ->assertSee('Open dossier', false)
            ->assertDontSee('Open screening', false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('neema.valuer@example.com', false)
            ->assertSee('Neema Valuations', false)
            ->assertSee('Awaiting activation', false)
            ->assertSee(route('admin.partners.onboarding'), false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.partners.index'))
            ->assertOk()
            ->assertSee('Applications to screen', false)
            ->assertSee('Awaiting activation', false)
            ->assertSee('Neema Valuations', false);

        $this->actingAs($user, 'admin')
            ->get(route('admin.partners.onboarding'))
            ->assertOk()
            ->assertSee('Awaiting activation', false)
            ->assertSee('Neema Valuations', false);
    }

    public function test_officer_dashboard_is_screening_and_has_no_settings(): void
    {
        $und = Department::query()->where('code', 'UND')->firstOrFail();
        $officer = User::factory()->create([
            'role' => 'officer',
            'department_id' => $und->id,
            'is_active' => true,
        ]);
        $officer->departments()->sync([$und->id]);

        $this->actingAs($officer, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Credit screening', false)
            ->assertSee('Open screening queue', false);

        $labels = collect(app(ConsoleNavService::class)->visibleSections($officer->fresh()))
            ->pluck('label')
            ->all();

        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Lending', $labels);
        $this->assertNotContains('Ops', $labels);
        $this->assertNotContains('Partners', $labels);

        $this->actingAs($officer, 'admin')
            ->get(route('admin.settings.account-security'))
            ->assertOk()
            ->assertSee('Update password', false)
            ->assertDontSee('Settings hub', false);
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
}
