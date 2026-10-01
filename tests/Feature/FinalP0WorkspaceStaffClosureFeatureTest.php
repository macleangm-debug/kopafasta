<?php

namespace Tests\Feature;

use App\Livewire\Admin\UsersTable;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\AdminRoleViewService;
use App\Services\Support\SupportAutomationService;
use App\Services\UserAccountService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FinalP0WorkspaceStaffClosureFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BranchSeeder::class);
        $this->seed(DepartmentSeeder::class);
    }

    public function test_workspace_homes_land_on_role_shells_not_admin_dashboard(): void
    {
        $views = app(AdminRoleViewService::class);

        $this->assertSame(route('admin.support.home'), $views->workspaceHomeUrl('support'));
        $this->assertSame(route('admin.growth.index'), $views->workspaceHomeUrl('marketer'));
        $this->assertSame(route('admin.teams.screening'), $views->workspaceHomeUrl('officer'));
        $this->assertSame(route('admin.dashboard'), $views->workspaceHomeUrl('admin'));
    }

    public function test_staff_users_table_excludes_vendor_partners(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        User::factory()->create([
            'name' => 'Partner Vendor',
            'email' => 'partner.vendor@example.com',
            'role' => 'vendor',
            'roles' => ['vendor'],
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'Marketer Staff',
            'email' => 'marketer.staff@example.com',
            'role' => 'marketer',
            'roles' => ['marketer'],
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(UsersTable::class)
            ->assertSee('Marketer Staff')
            ->assertDontSee('Partner Vendor');
    }

    public function test_admin_set_temporary_password_authenticates_on_admin_guard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'email' => 'officer.login@example.com',
            'password' => 'old-secret-99',
            'is_active' => true,
        ]);

        $result = app(UserAccountService::class)->resetPassword($admin, $staff, 'TempPass!42');
        $this->assertSame('TempPass!42', $result['temporary_password']);

        $fresh = $staff->fresh();
        $this->assertTrue(Hash::check('TempPass!42', $fresh->password));
        $this->assertTrue(Auth::guard('admin')->attempt([
            'email' => 'officer.login@example.com',
            'password' => 'TempPass!42',
        ]));
        Auth::guard('admin')->logout();
    }

    public function test_password_setup_link_sets_password_and_is_single_use(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'collector',
            'roles' => ['collector'],
            'email' => null,
            'phone' => '255711000001',
            'is_active' => true,
        ]);

        $issued = app(UserAccountService::class)->issuePasswordSetupLink($admin, $staff);
        $this->assertStringContainsString('password-setup', $issued['url']);
        $this->assertFalse($issued['emailed']);

        $query = [];
        parse_str(parse_url($issued['url'], PHP_URL_QUERY) ?: '', $query);

        $this->get($issued['url'])->assertOk()->assertSee('Choose your password', false);

        $this->post(route('staff.password-setup.store'), [
            'token' => $query['token'],
            'uid' => $staff->id,
            'email' => $query['email'],
            'password' => 'ChosenPass99',
            'password_confirmation' => 'ChosenPass99',
        ])->assertRedirect(route('staff.login'));

        $this->assertTrue(Hash::check('ChosenPass99', $staff->fresh()->password));

        $this->post(route('staff.password-setup.store'), [
            'token' => $query['token'],
            'uid' => $staff->id,
            'email' => $query['email'],
            'password' => 'AnotherPass99',
            'password_confirmation' => 'AnotherPass99',
        ])->assertSessionHasErrors('password');
    }

    public function test_another_issue_restarts_category_inside_same_conversation(): void
    {
        $member = User::factory()->create([
            'role' => 'borrower',
            'roles' => ['borrower'],
            'is_active' => true,
        ]);
        $conversation = SupportConversation::query()->create([
            'conversation_number' => 'CNV-TEST01',
            'user_id' => $member->id,
            'customer_id' => null,
            'status' => 'open',
            'handling_state' => SupportAutomationService::STATE_WAITING_CUSTOMER,
            'channel' => 'web',
            'automation_meta' => [
                'phase' => 'escalate_offer',
                'audience' => 'member',
                'category_key' => 'account',
                'issue_slug' => 'pin',
                'tried_slugs' => ['pin'],
            ],
        ]);

        $payload = app(SupportAutomationService::class)->step(
            $conversation,
            'another_issue',
            [],
            null,
            $member,
            'en'
        );

        $fresh = $conversation->fresh();
        $this->assertSame($conversation->id, $fresh->id);
        $this->assertSame('CNV-TEST01', $fresh->conversation_number);
        $this->assertSame('category', data_get($fresh->automation_meta, 'phase'));
        $this->assertNull(data_get($fresh->automation_meta, 'category_key'));
        $this->assertSame('category', $payload['phase'] ?? null);
        $this->assertNotEmpty($payload['choices'] ?? []);
    }
}