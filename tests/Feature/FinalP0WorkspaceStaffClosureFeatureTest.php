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
        $this->assertSame(route('admin.audit-logs.index'), $views->workspaceHomeUrl('auditor'));
        $this->assertSame(route('admin.dashboard'), $views->workspaceHomeUrl('admin'));
        // Unknown / unmapped never falls into Marketer.
        $this->assertSame(route('admin.dashboard'), $views->workspaceHomeUrl('not_a_real_role'));
        $this->assertNotSame(route('admin.growth.index'), $views->workspaceHomeUrl('auditor'));
        $this->assertNotSame(route('admin.growth.index'), $views->workspaceHomeUrl('collector'));
    }

    public function test_complete_staff_role_workspace_matrix_has_no_marketer_fallback(): void
    {
        $views = app(AdminRoleViewService::class);
        $matrix = $views->workspaceMappingMatrix();
        $this->assertNotEmpty($matrix);

        $byWorkspace = [];
        foreach ($matrix as $row) {
            if ($row['workspace_key'] !== 'marketer') {
                $this->assertNotSame(
                    route('admin.growth.index'),
                    $row['landing_url'],
                    "{$row['role']} must not land on Marketer Growth"
                );
            }
            $byWorkspace[$row['workspace_key']] = $row;
        }

        $this->assertSame('admin.audit-logs.index', $byWorkspace['auditor']['landing_route']);
        $this->assertSame('audit', $byWorkspace['auditor']['navigation_shell']);
        $this->assertSame('admin.growth.index', $byWorkspace['marketer']['landing_route']);
        $this->assertSame('admin.support.home', $byWorkspace['support']['landing_route']);
        $this->assertSame('admin.teams.screening', $byWorkspace['officer']['landing_route']);
        $this->assertSame('admin.reports.collections-performance', $byWorkspace['collector']['landing_route']);
    }

    public function test_entering_auditor_workspace_never_opens_marketer(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'marketer'])
            ->assertRedirect(route('admin.growth.index'));

        $this->post(route('admin.role-view.enter'), ['workspace_key' => 'auditor'])
            ->assertRedirect(route('admin.audit-logs.index'));

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertSame('auditor', $ctx['role_key'] ?? null);
        $this->assertSame('auditor', $ctx['workspace_key'] ?? null);

        $html = $this->get(route('admin.audit-logs.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Auditor', $html);
        $this->assertStringNotContainsString('admin.growth.index', $html);
        // Growth chrome must not appear while Viewing Auditor.
        $this->assertStringNotContainsString('>Growth<', $html);
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

    public function test_admin_password_setup_link_cta_route_returns_copyable_url_without_inventing_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'email' => null,
            'phone' => '255711000099',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.password-setup-link', $staff))
            ->assertRedirect(route('admin.users.show', $staff))
            ->assertSessionHas('password_setup_url')
            ->assertSessionHas('status');

        $url = (string) session('password_setup_url');
        $this->assertStringContainsString('password-setup', $url);
        $this->assertNull($staff->fresh()->email);
    }

    public function test_user_show_page_exposes_browser_setup_link_form_and_posts_successfully(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'email' => null,
            'phone' => '255711000088',
            'is_active' => true,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $staff))
            ->assertOk()
            ->assertSee('data-testid="password-setup-link-cta"', false)
            ->assertSee('admin-password-setup-link-form', false)
            ->assertSee(route('admin.users.password-setup-link', $staff), false)
            ->getContent();

        // Visible CTA must be a native submit; confirmForm may enhance but must not be required.
        $this->assertMatchesRegularExpression(
            '/type="submit"[^>]*data-testid="password-setup-link-cta"|data-testid="password-setup-link-cta"[^>]*type="submit"/',
            $html
        );
        $this->assertStringNotContainsString('type="button"', substr(
            $html,
            (int) strpos($html, 'password-setup-link-form'),
            800
        ));
        $this->assertStringContainsString('confirmForm(this,', $html);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.users.show', $staff))
            ->post(route('admin.users.password-setup-link', $staff))
            ->assertRedirect(route('admin.users.show', $staff))
            ->assertSessionHas('password_setup_url');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $staff))
            ->assertOk()
            ->assertSee('data-testid="password-setup-url"', false)
            ->assertSee('password-setup', false);
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

    public function test_manager_workspace_management_page_does_not_crash(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'manager'])
            ->assertRedirect(route('admin.teams.management'));

        $this->get(route('admin.teams.management'))
            ->assertOk()
            ->assertSee('Post-approval', false)
            ->assertSee('data-kf-glass-hero', false);

        $this->assertSame('manager', app(AdminRoleViewService::class)->active()['role_key'] ?? null);

        // Refresh preserves viewing context and still renders.
        $this->get(route('admin.teams.management'))->assertOk();
        $this->assertSame('manager', app(AdminRoleViewService::class)->active()['role_key'] ?? null);
    }

    public function test_user_edit_exposes_focused_wizard_steps_not_one_long_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'is_active' => true,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.edit', $staff))
            ->assertOk()
            ->assertSee('admin-wizard', false)
            ->assertSee('data-step-label="Personal"', false)
            ->assertSee('data-step-label="Capabilities"', false)
            ->assertSee('data-step-label="Work / Team"', false)
            ->assertSee('data-step-label="Security"', false)
            ->getContent();

        $this->assertStringContainsString('Switch any section', $html);
        $this->assertStringContainsString('admin-wizard-rebuild', $html);
    }

    public function test_manual_and_auto_password_authenticate_on_staff_login(): void
    {
        config(['auth_portal.require_2fa_staff' => false, 'auth_portal.require_2fa_admin' => false]);

        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'officer',
            'roles' => ['officer'],
            'email' => 'officer.auth@example.com',
            'password' => 'old-secret-99',
            'is_active' => true,
        ]);

        $manual = app(UserAccountService::class)->resetPassword($admin, $staff, 'ManualPass99');
        $this->assertSame('ManualPass99', $manual['temporary_password']);

        $this->post(route('staff.login'), [
            'login' => 'officer.auth@example.com',
            'password' => 'ManualPass99',
        ])->assertRedirect(route('admin.teams.screening'));

        Auth::guard('admin')->logout();

        $auto = app(UserAccountService::class)->resetPassword($admin, $staff->fresh(), null);
        $this->assertNotSame('', $auto['temporary_password']);

        $this->post(route('staff.login'), [
            'email' => 'officer.auth@example.com',
            'password' => $auto['temporary_password'],
        ])->assertRedirect(route('admin.teams.screening'));
    }

    public function test_setup_link_password_authenticates_and_phone_login_without_email(): void
    {
        config(['auth_portal.require_2fa_staff' => false, 'auth_portal.require_2fa_admin' => false]);

        $admin = User::factory()->create(['role' => 'admin', 'roles' => ['admin'], 'is_active' => true]);
        $staff = User::factory()->create([
            'role' => 'collector',
            'roles' => ['collector'],
            'email' => null,
            'phone' => '255711000777',
            'password' => 'old-secret-99',
            'is_active' => true,
        ]);

        $issued = app(UserAccountService::class)->issuePasswordSetupLink($admin, $staff);
        $query = [];
        parse_str(parse_url($issued['url'], PHP_URL_QUERY) ?: '', $query);

        $this->post(route('staff.password-setup.store'), [
            'token' => $query['token'],
            'uid' => $staff->id,
            'email' => $query['email'],
            'password' => 'SetupChosen99',
            'password_confirmation' => 'SetupChosen99',
        ])->assertRedirect(route('staff.login'));

        $this->post(route('staff.login'), [
            'login' => '255711000777',
            'password' => 'SetupChosen99',
        ])->assertRedirect(route('staff.dashboard'));
    }
}