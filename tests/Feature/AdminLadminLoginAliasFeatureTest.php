<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLadminLoginAliasFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_ladmin_login_renders_admin_login_for_guests(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/ladmin/login')
            ->assertOk()
            ->assertSee('Welcome back', false)
            ->assertSee('kf-premium-panel', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('Show password', false);
    }

    public function test_canonical_admin_login_still_renders(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Welcome back', false)
            ->assertSee('kf-premium-panel', false);
    }

    public function test_invalid_ladmin_credentials_show_validation_error(): void
    {
        $this->from('/ladmin/login')
            ->post('/ladmin/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/ladmin/login')
            ->assertSessionHasErrors('email');
    }

    public function test_authenticated_admin_hitting_ladmin_login_redirects_once_to_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get('/ladmin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_ladmin_root_redirects_to_admin_login(): void
    {
        $this->get('/ladmin')
            ->assertRedirect('/admin/login');
    }
}
