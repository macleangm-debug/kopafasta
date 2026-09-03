<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TurnstileAuthPagesFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function enableTurnstile(): void
    {
        Setting::set('security.turnstile_site_key', 'test-site-key');
        Setting::set('security.turnstile_secret_key', 'test-secret-key');
    }

    private function rejectHumans(): void
    {
        $this->enableTurnstile();
        $mock = Mockery::mock(TurnstileService::class)->makePartial();
        $mock->shouldReceive('enabled')->andReturn(true);
        $mock->shouldReceive('siteKey')->andReturn('test-site-key');
        $mock->shouldReceive('verify')->andReturn(false);
        $this->app->instance(TurnstileService::class, $mock);
    }

    public function test_login_and_register_pages_render_the_challenge_when_configured(): void
    {
        $this->enableTurnstile();

        foreach ([
            route('site.login'),
            route('admin.login'),
            route('staff.login'),
            route('site.register.borrower'),
            route('site.register.vendor'),
            route('site.register.investor'),
            route('site.register.capital'),
            route('site.partner.start'),
            route('site.forgot-pin'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('cf-turnstile', false)
                ->assertSee('test-site-key', false);
        }
    }

    public function test_login_and_register_posts_are_blocked_without_a_human_token(): void
    {
        $this->rejectHumans();

        $this->from(route('site.login'))
            ->post(route('site.login.post'), [
                'auth_method' => 'password',
                'login' => 'bot@example.com',
                'password' => 'secret',
            ])
            ->assertRedirect(route('site.login'))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->from(route('admin.login'))
            ->post(route('admin.login'), [
                'email' => 'bot@example.com',
                'password' => 'secret',
            ])
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->from(route('staff.login'))
            ->post(route('staff.login'), [
                'email' => 'bot@example.com',
                'password' => 'secret',
            ])
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->from(route('site.register.borrower'))
            ->post(route('site.register.borrower.post'), [
                'first_name' => 'Bot',
                'last_name' => 'User',
                'gender' => 'male',
                'phone' => '255712345678',
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->from(route('site.register.vendor'))
            ->post(route('site.register.vendor.post'), [
                'name' => 'Bot Vendor',
                'category' => 'valuer',
                'email' => 'vendor-bot@example.com',
                'phone' => '255712345678',
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->from(route('site.register.investor'))
            ->post(route('site.register.investor.post'), [
                'name' => 'Bot Investor',
                'type' => 'individual',
                'email' => 'investor-bot@example.com',
                'phone' => '255712345678',
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->from(route('site.register.capital'))
            ->post(route('site.register.capital.post'), [
                'organization' => 'Bot Capital',
                'org_type' => 'bank',
                'contact_name' => 'Bot Contact',
                'email' => 'capital-bot@example.com',
                'phone' => '255712345678',
                'country' => 'Tanzania',
                'commitment_band' => '50k_250k',
                'password' => 'password1',
                'password_confirmation' => 'password1',
            ])
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->from(route('site.partner.start'))
            ->post(route('site.partner.start.lookup'), [
                'partner_code' => 'PT-XX-TZ-0001',
                'phone' => '255712345678',
            ])
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->from(route('site.forgot-pin'))
            ->post(route('site.forgot-pin.start'), [
                'phone' => '255712345678',
            ])
            ->assertSessionHasErrors('cf-turnstile-response');
    }
}
