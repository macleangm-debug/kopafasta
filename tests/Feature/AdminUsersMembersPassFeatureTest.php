<?php

namespace Tests\Feature;

use App\Models\ApprovalLimit;
use App\Models\User;
use App\Services\CreditAuthorityService;
use App\Services\PermissionService;
use App\Services\RoleService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\Admin\UsersTable;
use Tests\TestCase;

class AdminUsersMembersPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BranchSeeder::class);
        $this->seed(DepartmentSeeder::class);
    }

    public function test_users_list_excludes_borrower_and_customer_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        User::factory()->create([
            'name' => 'Borrower Person',
            'email' => 'borrower@example.com',
            'role' => 'borrower',
            'roles' => ['borrower'],
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'Staff Person',
            'email' => 'staff@example.com',
            'role' => 'credit_analyst',
            'roles' => ['credit_analyst'],
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(UsersTable::class)
            ->assertSee('Staff Person')
            ->assertDontSee('Borrower Person');
    }

    public function test_phone_local_emails_are_hidden_on_user_show(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $user = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'email' => '255700000001@phone.kopafasta.local',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertDontSee('@phone.kopafasta.local', false)
            ->assertSee('—', false);
    }

    public function test_multi_capability_unions_permissions_and_stores_roles_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.store'), [
                'name' => 'Analyst Support',
                'email' => 'analyst.support@example.com',
                'phone' => '255700000099',
                'password' => 'secret12',
                'roles' => ['credit_analyst', 'agent'],
                'is_active' => '1',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'analyst.support@example.com')->firstOrFail();
        $this->assertSame('credit_analyst', $user->role);
        $this->assertEqualsCanonicalizing(['credit_analyst', 'agent'], $user->roles);

        $perms = app(PermissionService::class)->forUser($user);
        $this->assertContains('support.tickets', $perms);
        $this->assertTrue($user->hasPermission('support.tickets'));
    }

    public function test_effective_approval_comes_from_matrix_not_agent(): void
    {
        ApprovalLimit::create([
            'role_code' => 'credit_analyst',
            'action' => 'loan_approve',
            'min_amount' => 0,
            'max_amount' => 2_500_000,
            'currency' => 'TZS',
            'requires_dual_control' => false,
            'is_active' => true,
        ]);

        $analyst = User::factory()->create([
            'role' => 'credit_analyst',
            'roles' => ['credit_analyst', 'agent'],
            'is_active' => true,
        ]);
        $agentOnly = User::factory()->create([
            'role' => 'agent',
            'roles' => ['agent'],
            'is_active' => true,
        ]);

        $authority = app(CreditAuthorityService::class);
        $display = $authority->effectiveLoanApproveDisplay($analyst);
        $this->assertNotNull($display);
        $this->assertStringContainsString('2,500,000', $display);
        $this->assertNull($authority->effectiveLoanApproveDisplay($agentOnly));

        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $analyst))
            ->assertOk()
            ->assertSee('Approval authority', false)
            ->assertSee('2,500,000', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $agentOnly))
            ->assertOk()
            ->assertSee('Approval authority', false);
    }

    public function test_console_nav_label_is_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $labels = collect(app(RoleService::class)->operationalRoles())->all();
        $this->assertNotContains('borrower', $labels);
        $this->assertNotContains('customer', $labels);

        $nav = collect(app(\App\Services\ConsoleNavService::class)->visibleSections($admin))
            ->pluck('label')
            ->all();
        $this->assertContains('Members', $nav);
        $this->assertNotContains('Customers', $nav);
    }

    public function test_operator_email_display_helper(): void
    {
        $this->assertSame('—', operator_email_display('2557@phone.kopafasta.local'));
        $this->assertSame('a@example.com', operator_email_display('a@example.com'));
        $this->assertSame('—', operator_email_display(null));
    }
}
