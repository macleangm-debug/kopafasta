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

    public function test_partner_logout_returns_to_partner_landing_not_admin(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);

        $response = $this->actingAs($user)->post(route('site.logout'));

        $response->assertRedirect(route('site.partners'));
        $this->assertStringNotContainsString('/admin/login', (string) $response->headers->get('Location'));
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
