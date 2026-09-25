<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutRedirectFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrower_logout_returns_to_public_home(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);

        $this->actingAs($user)
            ->post(route('site.logout'))
            ->assertRedirect(route('site.home'));

        $this->assertGuest('web');
    }

    public function test_partner_logout_returns_to_partner_login_not_admin(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);

        $response = $this->actingAs($user)->post(route('site.logout'));

        $response->assertRedirect(route('site.login.partner'));
        $this->assertStringNotContainsString('/admin/login', (string) $response->headers->get('Location'));
        $this->assertGuest('web');
    }

    public function test_borrower_login_partner_cta_opens_the_partner_login_page(): void
    {
        $this->get(route('site.login'))
            ->assertOk()
            ->assertSee(route('site.login.partner'), false)
            ->assertDontSee('partnerOpen', false);

        $this->get(route('site.login.partner'))
            ->assertRedirect(route('site.login', ['portal' => 'partner']));

        $this->followingRedirects()
            ->get(route('site.login.partner'))
            ->assertOk()
            ->assertSee(__('site.auth.partner_sign_in'), false);
    }

    public function test_investor_logout_returns_to_partner_login(): void
    {
        $user = User::factory()->create(['role' => 'investor']);

        $this->actingAs($user)
            ->post(route('site.logout'))
            ->assertRedirect(route('site.login.partner'));

        $this->assertGuest('web');
    }

    public function test_admin_logout_returns_to_staff_admin_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));
    }
}
