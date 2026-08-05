<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\PayInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayInIntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_payin_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.payin.save'), [
                'enabled' => '1',
                'environment' => 'sandbox',
                'api_key' => 'pk_test_abc',
                'api_secret' => 'sk_test_xyz',
                'webhook_secret' => 'whsec_test',
                'default_callback_url' => '',
                'gateway_mode' => 'live',
                'mobile_money_threshold' => '3,000,000',
                'channels' => ['mobile_money', 'bank'],
                'intent' => 'save',
            ])
            ->assertRedirect()
            ->assertSessionHas('feedback');

        $this->assertTrue((bool) Setting::get('payin.enabled'));
        $this->assertSame('sandbox', Setting::get('payin.environment'));
        $this->assertSame('pk_test_abc', Setting::get('payin.api_key'));
        $this->assertSame('live', Setting::get('payments.gateway_mode'));
        $this->assertSame(3000000, (int) Setting::get('payments.mobile_money_threshold'));
        $this->assertSame(3000000, payment_mobile_money_threshold());
        $this->assertSame(['mobile_money', 'bank'], Setting::get('integrations.partner_channels')['payin'] ?? null);
        $channels = payment_channels_for_amount(2_500_000);
        $this->assertTrue($channels['mobile_money_allowed']);
        $this->assertFalse(payment_channels_for_amount(3_000_001)['mobile_money_allowed']);

        $service = app(PayInService::class);
        $this->assertTrue($service->isConfigured());
        $this->assertTrue($service->isLiveCollectionEnabled());
        $this->assertStringContainsString('sandbox.payin.co.tz', $service->baseUrl());
    }

    public function test_save_and_test_persists_settings_before_health_check(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.payin.save'), [
                'enabled' => '1',
                'environment' => 'sandbox',
                'api_key' => 'pk_keep_me',
                'api_secret' => 'sk_keep_me',
                'webhook_secret' => 'whsec_keep',
                'default_callback_url' => '',
                'gateway_mode' => 'live',
                'mobile_money_threshold' => '3000000',
                'channels' => ['mobile_money'],
                'intent' => 'save_and_test',
            ]);

        $response->assertRedirect(route('admin.settings.payin'));
        $this->assertSame('pk_keep_me', Setting::get('payin.api_key'));
        $this->assertSame('sk_keep_me', Setting::get('payin.api_secret'));
        $this->assertTrue((bool) Setting::get('payin.enabled'));
        $this->assertSame('error', session('feedback.tone'));
        $this->assertStringContainsString('saved', strtolower((string) session('feedback.message')));
    }

    public function test_admin_can_add_custom_payment_partner(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.integrations.partners.store'), [
                'label' => 'Selcom Live',
                'category' => 'payment',
                'description' => 'Second PSP',
                'channels' => ['mobile_money', 'bank'],
            ])
            ->assertRedirect()
            ->assertSessionHas('feedback');

        $custom = Setting::get('integrations.custom_partners');
        $this->assertIsArray($custom);
        $this->assertNotEmpty($custom);
        $first = reset($custom);
        $this->assertSame('Selcom Live', $first['label']);
        $this->assertSame(['mobile_money', 'bank'], $first['channels']);
    }

    public function test_integrations_hub_lists_payment_partners(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations'))
            ->assertOk()
            ->assertSee('PayIn')
            ->assertSee('Unitxt SMS')
            ->assertSee('Check all health');
    }

    public function test_admin_can_set_primary_payment_partner(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.integrations.primary'), [
                'category' => 'payment',
                'partner' => 'payin',
            ])
            ->assertRedirect();

        $this->assertSame('payin', Setting::get('integrations.primary.payment'));
    }

    public function test_unhealthy_integration_appears_in_admin_alerts(): void
    {
        Setting::set('integrations.health.payin', [
            'ok' => false,
            'message' => 'API key rejected',
            'checked_at' => now()->toIso8601String(),
            'provider' => 'payin',
        ]);

        $alerts = app(\App\Services\AdminAlertService::class)->alerts();

        $this->assertTrue($alerts->contains(
            fn (array $alert) => $alert['key'] === 'integration_payin' && $alert['count'] === 1
        ));
    }

    public function test_payin_settings_page_loads(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.payin'))
            ->assertOk()
            ->assertSee('PayIn payments')
            ->assertSee('webhooks/payin')
            ->assertSee('Save &amp; test connection', false);
    }

    public function test_payin_webhook_rejects_bad_signature(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.api_key' => 'pk',
            'payin.api_secret' => 'sk',
            'payin.webhook_secret' => 'secret',
        ]);

        $this->postJson(route('webhooks.payin'), [
            'event' => 'payin.completed',
            'request_ref' => 'PAY123',
        ], [
            'X-Payin-Signature' => 'bad',
            'X-Payin-Timestamp' => (string) time(),
        ])->assertStatus(401);
    }
}
