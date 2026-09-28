<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateSupplierProductionEnablementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_activate_affiliate_for_partner_pin_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Release2 Affiliate',
                'category' => 'affiliate',
                'status' => 'inactive',
                'phone' => '255710000201',
                'coverage_type' => 'nationwide',
                'activation_mode' => 'activate_now',
                'activation_pin' => '2468',
                'notify_partner' => '0',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Release2 Affiliate')->first();
        $this->assertNotNull($partner);
        $this->assertNotEmpty($partner->vendor_number ?: $partner->partner_number);
        $this->assertSame('affiliate', $partner->category);
        $this->assertSame('active', $partner->status);
        $this->assertNotNull($partner->user_id);
        $this->assertNotNull($partner->activated_at);
        $this->assertTrue(app(PinService::class)->verify('2468', $partner->user->pin_hash));

        $this->markWelcomeSeen($partner->fresh()->user);
        $this->flushSession();
        auth('admin')->logout();
        $this->app['auth']->shouldUse('web');

        $this->withSession(['login_portal' => 'partner'])
            ->post(route('site.login.post'), [
                'phone' => '255710000201',
                'pin' => '2468',
                'auth_method' => 'pin',
            ])
            ->assertRedirect();

        $user = $partner->fresh()->user;
        $this->actingAs($user, 'web')
            ->get(route('site.affiliate.dashboard'))
            ->assertOk();
        $this->actingAs($user, 'web')
            ->get(route('site.affiliate.performance'))
            ->assertOk();
        $this->actingAs($user, 'web')
            ->get(route('site.affiliate.wallet'))
            ->assertRedirect(route('site.affiliate.performance', ['tab' => 'commissions']));
        $this->actingAs($user, 'web')
            ->get(route('site.affiliate.profile'))
            ->assertOk();
        $this->actingAs($user, 'web')
            ->get(route('site.affiliate.share'))
            ->assertOk();
    }

    public function test_admin_can_create_and_activate_supplier_for_partner_pin_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.partners.store'), [
                'name' => 'Release2 Supplier',
                'category' => 'supplier',
                'status' => 'inactive',
                'phone' => '255710000202',
                'regions' => ['Dar es Salaam'],
                'activation_mode' => 'activate_now',
                'activation_pin' => '1357',
                'notify_partner' => '0',
            ])
            ->assertRedirect();

        $partner = Vendor::query()->where('name', 'Release2 Supplier')->first();
        $this->assertNotNull($partner);
        $this->assertMatchesRegularExpression('/^PT-SP-TZ-[A-Z0-9]{4}$/', (string) ($partner->vendor_number ?: $partner->partner_number));
        $this->assertSame('supplier', $partner->category);
        $this->assertSame('active', $partner->status);
        $this->assertNotNull($partner->user_id);
        $this->assertTrue(app(PinService::class)->verify('1357', $partner->user->pin_hash));

        $this->markWelcomeSeen($partner->fresh()->user);
        $this->flushSession();
        auth('admin')->logout();
        $this->app['auth']->shouldUse('web');

        $this->withSession(['login_portal' => 'partner'])
            ->post(route('site.login.post'), [
                'phone' => '255710000202',
                'pin' => '1357',
                'auth_method' => 'pin',
            ])
            ->assertRedirect();

        $user = $partner->fresh()->user;
        $this->actingAs($user, 'web')
            ->get(route('site.supplier.dashboard'))
            ->assertOk();
        $this->actingAs($user, 'web')
            ->get(route('site.supplier.assets'))
            ->assertOk();
        $this->actingAs($user, 'web')
            ->get(route('site.supplier.requests'))
            ->assertOk();
        $this->actingAs($user, 'web')
            ->get(route('site.supplier.settlements'))
            ->assertOk();
        $this->actingAs($user, 'web')
            ->get(route('site.supplier.profile'))
            ->assertOk();
    }

    private function markWelcomeSeen(User $user): void
    {
        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $preferences['account_welcome_completed_at'] = now()->toIso8601String();
        $user->forceFill(['preferences' => $preferences])->save();
    }
}
