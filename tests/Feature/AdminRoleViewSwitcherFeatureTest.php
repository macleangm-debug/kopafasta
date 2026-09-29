<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\User;
use App\Services\AdminRoleViewService;
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

    public function test_admin_header_exposes_role_switcher(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('admin.role_view.short'), false)
            ->assertSee(__('admin.role_view.title'), false);
    }

    public function test_search_shows_only_assigned_partner_roles_and_borrower_has_no_enter(): void
    {
        $admin = $this->admin();
        $partnerUser = User::factory()->create([
            'role' => 'vendor',
            'pin_hash' => bcrypt('1234'),
            'pin_set_at' => now(),
        ]);
        Partner::create([
            'user_id' => $partnerUser->id,
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

        $json = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.role-view.search', ['q' => 'Aventris']))
            ->assertOk()
            ->json('results');

        $this->assertNotEmpty($json);
        $person = collect($json)->firstWhere('name', 'Aventris Insurance');
        $this->assertNotNull($person);
        $keys = collect($person['roles'])->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['insurance', 'affiliate'], $keys);
        $this->assertTrue(collect($person['roles'])->every(fn ($r) => $r['enterable'] === true));
        $this->assertStringContainsString('partners/', $person['profile_url']);

        $borrowerHits = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.role-view.search', ['q' => 'Borrower']))
            ->assertOk()
            ->json('results');
        $borrower = collect($borrowerHits)->firstWhere('subject_type', 'borrower');
        $this->assertNotNull($borrower);
        $this->assertSame([], $borrower['roles']);
        $this->assertStringContainsString('customers/', $borrower['profile_url']);
    }

    public function test_view_profile_opens_partner_360_without_entering_workspace(): void
    {
        $admin = $this->admin();
        $partner = Partner::create([
            'vendor_number' => 'PT-PROF-001',
            'name' => 'Profile Partner',
            'phone' => '255700000099',
            'email' => 'profile@example.com',
            'category' => 'affiliate',
            'roles' => ['affiliate'],
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.profile'), [
                'subject_type' => 'partner',
                'subject_id' => $partner->id,
            ])
            ->assertRedirect(route('admin.partners.show', $partner));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_enter_partner_workspace_keeps_admin_auth_and_shows_banner(): void
    {
        $admin = $this->admin();
        $partnerUser = User::factory()->create([
            'role' => 'vendor',
            'name' => 'Aventris Portal',
            'pin_hash' => bcrypt('1234'),
            'pin_set_at' => now(),
        ]);
        $partner = Partner::create([
            'user_id' => $partnerUser->id,
            'vendor_number' => 'PT-IN-TZ-C9VE',
            'name' => 'Aventris Insurance',
            'phone' => '255715222199',
            'email' => 'aventris@example.com',
            'category' => 'insurance',
            'roles' => ['insurance', 'affiliate'],
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'partner',
                'subject_id' => $partner->id,
                'role_key' => 'affiliate',
            ])
            ->assertRedirect(route('site.affiliate.dashboard'));

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame($admin->id, Auth::guard('admin')->id());
        $this->assertSame($partnerUser->id, Auth::guard('web')->id());

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertNotNull($ctx);
        $this->assertSame('partner', $ctx['subject_type']);
        $this->assertSame('affiliate', $ctx['role_key']);

        $this->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee(__('admin.role_view.viewing'), false)
            ->assertSee('Aventris Insurance', false)
            ->assertSee(__('admin.role_view.exit'), false);

        $this->assertTrue(
            AuditLog::query()->where('event', 'admin.role_view.enter')->where('user_id', $admin->id)->exists()
        );

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.exit'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertFalse(app(AdminRoleViewService::class)->isActive());
        $this->assertNull(Auth::guard('web')->user());
        $this->assertTrue(Auth::guard('admin')->check());
    }

    public function test_cannot_enter_unassigned_role_or_change_pin_while_viewing(): void
    {
        $admin = $this->admin();
        $partnerUser = User::factory()->create([
            'role' => 'vendor',
            'pin_hash' => bcrypt('1234'),
            'pin_set_at' => now(),
        ]);
        $partner = Partner::create([
            'user_id' => $partnerUser->id,
            'vendor_number' => 'PT-ONE-ROLE',
            'name' => 'Single Affiliate',
            'phone' => '255700000088',
            'email' => 'single@example.com',
            'category' => 'affiliate',
            'roles' => ['affiliate'],
            'status' => 'active',
            'activated_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'partner',
                'subject_id' => $partner->id,
                'role_key' => 'insurance',
            ])
            ->assertStatus(422);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.role-view.enter'), [
                'subject_type' => 'partner',
                'subject_id' => $partner->id,
                'role_key' => 'affiliate',
            ])
            ->assertRedirect();

        $this->put(route('site.affiliate.settings.pin'), [
                'current_pin' => '1234',
                'pin' => '5678',
                'pin_confirmation' => '5678',
            ])
            ->assertForbidden();
    }

    public function test_staff_enter_uses_same_foundation_and_borrower_cannot_enter(): void
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

        $ctx = app(AdminRoleViewService::class)->active();
        $this->assertSame('staff', $ctx['subject_type']);
        $this->assertSame('officer', $ctx['role_key']);
        $this->assertSame($admin->id, Auth::guard('admin')->id());

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
}
