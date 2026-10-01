<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AdminRoleViewService;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

    public function test_admin_header_exposes_role_first_directory_without_person_search(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('admin.role_view.short'), false)
            ->assertSee(__('admin.role_view.title'), false)
            ->assertSee(__('admin.role_view.filter_all'), false)
            ->assertSee(__('admin.role_view.enter_workspace'), false)
            ->assertSee('Support', false)
            ->assertDontSee('Search partner, staff, or borrower', false)
            ->getContent();

        $this->assertStringContainsString('No staff assigned', $html);
        $this->assertStringNotContainsString('Search a person', $html);
        $this->assertStringNotContainsString('name="subject_id"', $html);
    }

    public function test_directory_lists_all_configured_roles_and_unifies_support(): void
    {
        $roles = app(RoleService::class)->staffRoles();
        $this->assertNotEmpty($roles);

        $directory = app(AdminRoleViewService::class)->staffRoleDirectory();
        $keys = collect($directory)->pluck('key')->all();

        $this->assertContains('support', $keys);
        $this->assertNotContains('agent', $keys);
        $this->assertNotContains('partner_support', $keys);
        $this->assertContains('Support', collect($directory)->pluck('label')->all());
        $this->assertTrue(collect($directory)->every(fn ($row) => array_key_exists('staff_count', $row)));
        $this->assertTrue(collect($directory)->contains(fn ($row) => $row['staff_count'] === 0));

        // Every non-support staff role still appears.
        foreach ($roles as $code) {
            if (in_array($code, AdminRoleViewService::SUPPORT_ROLE_KEYS, true)) {
                continue;
            }
            $this->assertContains($code, $keys);
        }
    }

    public function test_click_support_opens_workspace_without_staff_and_banner_is_role_first(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'workspace',
                'workspace_key' => 'support',
            ])
            ->assertRedirect(route('admin.support.home'));

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame($admin->id, Auth::guard('admin')->id());

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertSame('support', $ctx['role_key']);
        $this->assertSame('all', $ctx['filter_mode']);
        $this->assertNull($ctx['subject_id']);
        $this->assertSame('Support', app(AdminRoleViewService::class)->bannerLabel());

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee(__('admin.role_view.viewing'), false)
            ->assertSee('>Support</', false)
            ->assertSee(__('admin.role_view.staff_all'), false)
            ->assertDontSee('Operations dashboard', false);

        $this->assertSame('Support', app(AdminRoleViewService::class)->bannerLabel());
    }

    public function test_role_with_no_staff_still_enters_workspace(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'workspace_key' => 'marketer',
            ])
            ->assertRedirect(route('admin.growth.index'));

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertSame('marketer', $ctx['role_key']);
        $this->assertSame('all', $ctx['filter_mode']);
        $this->assertSame('Marketer', app(AdminRoleViewService::class)->bannerLabel());

        $this->assertStringContainsString('/growth', app(AdminRoleViewService::class)->workspaceHomeUrl('marketer'));
        $this->assertStringContainsString('/support', app(AdminRoleViewService::class)->workspaceHomeUrl('support'));
    }

    public function test_staff_selector_inside_support_filters_to_person(): void
    {
        $admin = $this->admin();
        $agent = User::factory()->create([
            'role' => 'agent',
            'roles' => ['agent'],
            'name' => 'Rogathe Nyela',
            'is_active' => true,
        ]);
        $partnerSupport = User::factory()->create([
            'role' => 'partner_support',
            'roles' => ['partner_support'],
            'name' => 'Asha Partner Desk',
            'is_active' => true,
        ]);

        $directory = app(AdminRoleViewService::class)->staffRoleDirectory();
        $support = collect($directory)->firstWhere('key', 'support');
        $this->assertSame(2, $support['staff_count']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'workspace_key' => 'support',
            ])
            ->assertRedirect(route('admin.support.home'));

        $this->post(route('admin.role-view.select-staff'), [
            'staff_id' => $agent->id,
        ])->assertRedirect(route('admin.support.home'));

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertSame('staff', $ctx['filter_mode']);
        $this->assertSame($agent->id, (int) $ctx['subject_id']);
        $this->assertSame('Rogathe Nyela · Support', app(AdminRoleViewService::class)->bannerLabel());

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee('Rogathe Nyela', false);

        // Partner support capability staff can also be selected in unified Support.
        $this->post(route('admin.role-view.select-staff'), [
            'staff_id' => $partnerSupport->id,
        ])->assertRedirect(route('admin.support.home'));

        $this->assertSame('Asha Partner Desk · Support', app(AdminRoleViewService::class)->bannerLabel());
        $this->assertSame('support', app(AdminRoleViewService::class)->active()['role_key']);
    }

    public function test_directory_excludes_partners_and_borrowers_as_staff_roles(): void
    {
        Partner::create([
            'vendor_number' => 'PT-AVENTRIS-001',
            'name' => 'Aventris Insurance',
            'phone' => '255715222132',
            'email' => 'info@aventris.co.tz',
            'category' => 'insurance',
            'roles' => ['insurance', 'affiliate'],
            'status' => 'active',
        ]);
        Customer::create([
            'customer_number' => 'CU-BORROWER-ONLY',
            'first_name' => 'Borrower',
            'last_name' => 'Only',
            'phone' => '255700111222',
            'status' => 'active',
        ]);

        $labels = collect(app(AdminRoleViewService::class)->staffRoleDirectory())->pluck('label')->all();
        $this->assertNotContains('Borrower', $labels);
        $this->assertNotContains('Affiliate', $labels);
        $this->assertNotContains('Insurance', $labels);
    }

    public function test_admin_remains_audit_actor_and_exit_clears_context(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'workspace_key' => 'support',
            ])
            ->assertRedirect();

        $this->assertTrue(
            AuditLog::query()->where('event', 'admin.role_view.enter')->where('user_id', $admin->id)->exists()
        );

        $this->post(route('admin.role-view.exit'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
        $this->assertTrue(Auth::guard('admin')->check());
    }

    public function test_admin_exit_clears_support_and_restores_admin_chrome(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support'])
            ->assertRedirect(route('admin.support.home'));

        $this->get(route('admin.support.home'))
            ->assertOk()
            ->assertSee(__('admin.role_view.back_to_admin'), false);

        $this->post(route('admin.role-view.exit'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
        $this->assertFalse(app(\App\Services\Support\CustomerSupportWorkspaceService::class)->isSupportShellSticky());

        // Visiting Support routes after Exit must NOT silently re-trap Admin into Viewing.
        $this->get(route('admin.support.home'))->assertOk();
        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
        $this->assertFalse(app(\App\Services\Support\CustomerSupportWorkspaceService::class)->inSupportShell($admin));

        $dash = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Lending', $dash);
        $this->assertStringNotContainsString(__('admin.role_view.viewing').': Support', $dash);
    }

    public function test_admin_can_exit_via_account_role_admin_option_after_marketer(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), ['workspace_key' => 'support'])
            ->assertRedirect();

        $this->post(route('admin.role-view.enter'), ['workspace_key' => 'marketer'])
            ->assertRedirect(route('admin.growth.index'));

        $this->assertSame('marketer', app(AdminRoleViewService::class)->active()['role_key']);
        $this->assertFalse(app(\App\Services\Support\CustomerSupportWorkspaceService::class)->isSupportShellSticky());

        $this->get(route('admin.growth.index'))
            ->assertOk()
            ->assertSee(__('admin.role_view.admin_account'), false)
            ->assertSee(__('admin.role_view.back_to_admin'), false);

        $this->post(route('admin.role-view.exit'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
    }

    public function test_legacy_staff_enter_still_maps_agent_into_support_workspace(): void
    {
        $admin = $this->admin();
        $agent = User::factory()->create([
            'role' => 'agent',
            'roles' => ['agent'],
            'name' => 'Legacy Agent',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'staff',
                'subject_id' => $agent->id,
                'role_key' => 'agent',
            ])
            ->assertRedirect(route('admin.support.home'));

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertSame('support', $ctx['role_key']);
        $this->assertSame('Legacy Agent · Support', app(AdminRoleViewService::class)->bannerLabel());
    }
}
