<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\User;
use App\Services\AdminRoleViewService;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminRoleViewSwitcherFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'roles' => ['admin'],
            'is_active' => true,
        ]);
    }

    public function test_admin_header_exposes_staff_role_directory_not_person_search(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('admin.role_view.short'), false)
            ->assertSee(__('admin.role_view.title'), false)
            ->assertSee(__('admin.role_view.filter_all'), false)
            ->assertSee(__('admin.role_view.enter_workspace'), false)
            ->assertSee('Customer Support', false)
            ->assertDontSee(__('admin.role_view.type_more'), false)
            ->assertDontSee('Search partner, staff, or borrower', false)
            ->getContent();

        $this->assertStringContainsString('No staff assigned', $html);
        $this->assertStringNotContainsString('Search a person', $html);
    }

    public function test_staff_role_directory_lists_all_configured_roles_even_without_assignees(): void
    {
        $roles = app(RoleService::class)->staffRoles();
        $this->assertNotEmpty($roles);

        $directory = app(AdminRoleViewService::class)->staffRoleDirectory();
        $keys = collect($directory)->pluck('key')->all();

        $this->assertEquals($roles, $keys);
        $this->assertTrue(collect($directory)->every(fn ($row) => $row['staff_count'] === 0));
        $this->assertContains('Customer Support', collect($directory)->pluck('label')->all());
    }

    public function test_directory_shows_assigned_staff_and_excludes_partners_borrowers(): void
    {
        $officer = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'name' => 'John Mushi',
            'email' => 'john.mushi@example.com',
            'is_active' => true,
        ]);
        $analystA = User::factory()->create([
            'role' => 'credit_analyst',
            'roles' => ['credit_analyst'],
            'name' => 'Asha Analyst',
            'is_active' => true,
        ]);
        $analystB = User::factory()->create([
            'role' => 'credit_analyst',
            'roles' => ['credit_analyst'],
            'name' => 'Baraka Analyst',
            'is_active' => true,
        ]);

        Partner::create([
            'vendor_number' => 'PT-AVENTRIS-001',
            'name' => 'Aventris Insurance',
            'phone' => '255715222132',
            'email' => 'info@aventris.co.tz',
            'category' => 'insurance',
            'roles' => ['insurance', 'affiliate'],
            'status' => 'active',
            'activated_at' => now(),
        ]);
        Customer::create([
            'customer_number' => 'CU-BORROWER-ONLY',
            'first_name' => 'Borrower',
            'last_name' => 'Only',
            'phone' => '255700111222',
            'email' => 'borrower-only@example.com',
            'status' => 'active',
        ]);

        $directory = app(AdminRoleViewService::class)->staffRoleDirectory();
        $officerRow = collect($directory)->firstWhere('key', 'officer');
        $analystRow = collect($directory)->firstWhere('key', 'credit_analyst');
        $agentRow = collect($directory)->firstWhere('key', 'agent');

        $this->assertSame(1, $officerRow['staff_count']);
        $this->assertSame($officer->id, $officerRow['staff'][0]['id']);
        $this->assertSame(2, $analystRow['staff_count']);
        $this->assertEqualsCanonicalizing(
            [$analystA->id, $analystB->id],
            collect($analystRow['staff'])->pluck('id')->all()
        );
        $this->assertSame(0, $agentRow['staff_count']);

        $names = collect($directory)->flatMap(fn ($row) => collect($row['staff'])->pluck('name'))->all();
        $this->assertNotContains('Aventris Insurance', $names);
        $this->assertNotContains('Borrower Only', $names);
    }

    public function test_view_profile_opens_staff_360_without_entering_workspace(): void
    {
        $admin = $this->admin();
        $officer = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.profile'), [
                'subject_type' => 'staff',
                'subject_id' => $officer->id,
            ])
            ->assertRedirect(route('admin.users.show', $officer));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
    }

    public function test_enter_staff_workspace_keeps_admin_auth_and_shows_banner(): void
    {
        $admin = $this->admin();
        $officer = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer', 'collector'],
            'name' => 'John Mushi',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $officer->id,
                'role_key' => 'officer',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame($admin->id, Auth::guard('admin')->id());

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertNotNull($ctx);
        $this->assertSame('staff', $ctx['subject_type']);
        $this->assertSame('officer', $ctx['role_key']);
        $this->assertSame('John Mushi', $ctx['subject_name']);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('admin.role_view.viewing'), false)
            ->assertSee('John Mushi', false)
            ->assertSee(__('admin.role_view.exit'), false);

        $this->assertTrue(
            AuditLog::query()->where('event', 'admin.role_view.enter')->where('user_id', $admin->id)->exists()
        );

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.exit'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
        $this->assertTrue(Auth::guard('admin')->check());
    }

    public function test_cannot_enter_unassigned_staff_role_or_borrower_workspace(): void
    {
        $admin = $this->admin();
        $officer = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $officer->id,
                'role_key' => 'manager',
            ])
            ->assertStatus(422);

        $customer = Customer::create([
            'customer_number' => 'CU-NO-ENTER',
            'first_name' => 'No',
            'last_name' => 'Enter',
            'phone' => '255700000077',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'borrower',
                'subject_id' => $customer->id,
                'role_key' => 'borrower',
            ])
            ->assertSessionHasErrors('subject_type');
    }

    public function test_legacy_search_is_staff_only(): void
    {
        $admin = $this->admin();
        User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'name' => 'Searchable Officer',
            'is_active' => true,
        ]);
        Partner::create([
            'vendor_number' => 'PT-SEARCH',
            'name' => 'Searchable Affiliate',
            'phone' => '255700000066',
            'email' => 'search-aff@example.com',
            'category' => 'affiliate',
            'roles' => ['affiliate'],
            'status' => 'active',
        ]);
        Customer::create([
            'customer_number' => 'CU-SEARCH',
            'first_name' => 'Searchable',
            'last_name' => 'Borrower',
            'phone' => '255700000055',
            'status' => 'active',
        ]);

        $staffHits = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.role-view.search', ['q' => 'Searchable']))
            ->assertOk()
            ->json('results');

        $this->assertCount(1, $staffHits);
        $this->assertSame('staff', $staffHits[0]['subject_type']);
        $this->assertSame('Searchable Officer', $staffHits[0]['name']);
    }
}
