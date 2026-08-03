<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Phase69StaffPortalAnd2faFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth_portal.require_2fa_admin', true);
        Config::set('auth_portal.require_2fa_staff', true);
        Config::set('auth_portal.require_2fa_partner', true);
    }

    public function test_collector_can_sign_in_to_staff_workspace(): void
    {
        $collector = User::factory()->create([
            'email'    => 'collector@example.com',
            'password' => bcrypt('password'),
            'role'     => 'collector',
        ]);

        $this->post(route('staff.login'), [
            'email'    => 'collector@example.com',
            'password' => 'password',
        ])->assertRedirect(route('auth.two-factor.setup', ['context' => 'staff']));
    }

    public function test_collector_reaches_dashboard_after_2fa_enrollment(): void
    {
        $collector = User::factory()->create([
            'email'    => 'collector2@example.com',
            'password' => bcrypt('password'),
            'role'     => 'collector',
        ]);

        $this->post(route('staff.login'), [
            'email'    => 'collector2@example.com',
            'password' => 'password',
        ])->assertRedirect(route('auth.two-factor.setup', ['context' => 'staff']));

        $this->get(route('auth.two-factor.setup', ['context' => 'staff']))->assertOk();

        $secret = $collector->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);

        $code = app(TotpService::class)->currentCode((string) $secret);

        $this->post(route('auth.two-factor.confirm-setup'), [
            'code'    => $code,
            'context' => 'staff',
        ])->assertRedirect(route('staff.dashboard'));

        $this->actingAs($collector->fresh(), 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Loans', false);
    }

    public function test_collector_is_redirected_away_from_admin_dashboard(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        $collector = User::factory()->create([
            'role'                    => 'collector',
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($collector, 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_admin_login_requires_2fa_challenge_when_enrolled(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        User::factory()->create([
            'email'                   => 'admin2fa@example.com',
            'password'                => bcrypt('password'),
            'role'                    => 'admin',
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('admin.login'), [
            'email'    => 'admin2fa@example.com',
            'password' => 'password',
        ])->assertRedirect(route('auth.two-factor.challenge', ['context' => 'admin']));
    }

    public function test_trusted_device_cookie_does_not_skip_admin_2fa_challenge(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        $user = User::factory()->create([
            'email'                   => 'admin-trust@example.com',
            'password'                => bcrypt('password'),
            'role'                    => 'admin',
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $token = app(\App\Services\TrustedDeviceService::class)->create($user, request());
        $cookie = app(\App\Services\TrustedDeviceService::class)->makeCookie($token);

        $this->withCookie($cookie->getName(), $cookie->getValue())
            ->post(route('admin.login'), [
                'email'    => 'admin-trust@example.com',
                'password' => 'password',
            ])->assertRedirect(route('auth.two-factor.challenge', ['context' => 'admin']));
    }

    public function test_partner_password_login_requires_2fa_when_enrolled(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        $user = User::factory()->create([
            'email'                   => 'partner2fa@example.com',
            'password'                => bcrypt('password'),
            'role'                    => 'vendor',
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        Vendor::create([
            'user_id'        => $user->id,
            'vendor_number'  => 'V-P69',
            'name'           => 'Partner P69',
            'category'       => 'recovery',
            'status'         => 'active',
            'phone'          => '255712340069',
            'activated_at'   => now(),
        ]);

        $this->withSession(['login_portal' => 'partner'])
            ->post(route('site.login.post'), [
                'login'       => 'partner2fa@example.com',
                'password'    => 'password',
                'auth_method' => 'password',
            ])->assertRedirect(route('auth.two-factor.challenge', ['context' => 'partner']));
    }

    public function test_staff_login_hint_redirects_to_staff_portal(): void
    {
        $this->get(route('site.staff-login'))
            ->assertRedirect(route('staff.login'));
    }

    public function test_console_admin_is_redirected_away_from_staff_dashboard(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        $admin = User::factory()->create([
            'role'                    => 'admin',
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_credit_team_lands_on_role_home_from_staff_dashboard(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        $analyst = User::factory()->create([
            'role'                    => 'credit_analyst',
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($analyst, 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('admin.teams.screening'));
    }

    public function test_staff_security_redirects_into_admin_account_security(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        $collector = User::factory()->create([
            'role'                    => 'collector',
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($collector, 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->get(route('staff.security'))
            ->assertRedirect(route('admin.settings.account-security'));
    }

    public function test_enabled_security_page_shows_status_and_recovery_actions(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        $codes = ['aaaa111111', 'bbbb222222'];

        $collector = User::factory()->create([
            'role'                      => 'collector',
            'two_factor_secret'         => $secret,
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => $codes,
        ]);

        $this->actingAs($collector, 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->get(route('admin.settings.account-security'))
            ->assertOk()
            ->assertSee('Account security', false)
            ->assertSee('Two-factor authentication', false)
            ->assertSee('2 remaining', false)
            ->assertSee('Generate new recovery codes', false);
    }

    public function test_staff_can_regenerate_recovery_codes_with_authenticator(): void
    {
        $secret = app(TotpService::class)->generateSecret();

        $collector = User::factory()->create([
            'role'                      => 'collector',
            'two_factor_secret'         => $secret,
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['oldcode123'],
        ]);

        $code = app(TotpService::class)->currentCode($secret);

        $this->actingAs($collector, 'admin')
            ->withSession(['two_factor_verified_at' => now()->timestamp])
            ->post(route('admin.settings.account-security.regenerate'), ['code' => $code])
            ->assertRedirect(route('admin.settings.account-security'))
            ->assertSessionHas('fresh_recovery_codes');

        $fresh = $collector->fresh()->two_factor_recovery_codes;
        $this->assertCount(8, $fresh);
        $this->assertNotContains('oldcode123', $fresh);
    }

    public function test_admin_settings_can_disable_staff_2fa_requirement(): void
    {
        \App\Models\Setting::set('auth_portal.require_2fa_admin', false);

        $admin = User::factory()->create([
            'email'    => 'settings-admin@example.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.auth-portal.save'), [
                'require_2fa_admin'        => '1',
                'require_2fa_staff'        => '0',
                'require_2fa_partner'      => '1',
                'two_factor_session_hours' => 8,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse(app(\App\Services\AuthPortalSettingsService::class)->require2faStaff());
        $this->assertTrue(app(\App\Services\AuthPortalSettingsService::class)->require2faAdmin());
        $this->assertSame(8, app(\App\Services\AuthPortalSettingsService::class)->twoFactorSessionHours());

        User::factory()->create([
            'email'    => 'collector3@example.com',
            'password' => bcrypt('password'),
            'role'     => 'collector',
        ]);

        $this->post(route('staff.login'), [
            'email'    => 'collector3@example.com',
            'password' => 'password',
        ])->assertRedirect(route('staff.dashboard'));
    }
}
