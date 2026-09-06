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

        $response->assertRedirect(route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'configuration']));
        $this->assertSame('pk_keep_me', Setting::get('payin.api_key'));
        $this->assertSame('sk_keep_me', Setting::get('payin.api_secret'));
        $this->assertTrue((bool) Setting::get('payin.enabled'));
        $this->assertSame('error', session('feedback.tone'));
        $this->assertSame('PayIn connection failed', session('feedback.title'));
        $this->assertStringContainsString('could not authenticate', strtolower((string) session('feedback.message')));
        $this->assertNotEmpty(session('feedback.statuses'));
    }

    public function test_plain_save_does_not_claim_connection_success(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.payin.save'), [
                'environment' => 'production',
                'api_key' => 'pk_plain',
                'api_secret' => 'sk_plain',
                'webhook_secret' => 'whsec_plain',
                'default_callback_url' => 'https://www.kopafasta.com/webhooks/payin',
                'gateway_mode' => 'dummy',
                'mobile_money_threshold' => '3000000',
                'channels' => ['mobile_money'],
                'intent' => 'save',
            ])
            ->assertRedirect()
            ->assertSessionHas('feedback.title', 'Settings saved');

        $this->assertStringContainsString('has not been tested', strtolower((string) session('feedback.message')));
        $this->assertSame('info', session('feedback.tone'));
        $statuses = collect(session('feedback.statuses'));
        $this->assertTrue($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'connection' && ($row['value'] ?? '') === 'Not tested'));
    }

    public function test_save_and_test_success_separates_auth_from_gateway_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->mock(\App\Services\Integrations\IntegrationHealthService::class, function ($mock) {
            $mock->shouldReceive('check')
                ->once()
                ->with('payin', true)
                ->andReturn([
                    'ok' => true,
                    'message' => 'Authenticated with PayIn (production).',
                    'checked_at' => now()->toIso8601String(),
                    'provider' => 'payin',
                    'guidance' => [],
                    'probe_kind' => 'connection',
                ]);
        });

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.payin.save'), [
                'environment' => 'production',
                'api_key' => 'pk_live',
                'api_secret' => 'sk_live',
                'webhook_secret' => 'whsec_live',
                'default_callback_url' => 'https://www.kopafasta.com/webhooks/payin',
                'gateway_mode' => 'dummy',
                'mobile_money_threshold' => '3000000',
                'channels' => ['mobile_money'],
                'intent' => 'save_and_test',
            ])
            ->assertRedirect()
            ->assertSessionHas('feedback.title', 'PayIn connection successful')
            ->assertSessionHas('feedback.tone', 'warning');

        $message = strtolower((string) session('feedback.message'));
        $this->assertStringContainsString('successfully authenticated', $message);
        $statuses = collect(session('feedback.statuses'));
        $this->assertTrue($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'authentication' && ($row['value'] ?? '') === 'Connected'));
        $this->assertTrue($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'mode' && ($row['value'] ?? '') === 'Dummy'));
        $this->assertStringContainsString('Gateway Mode is Dummy', (string) session('feedback.action_required'));
    }

    public function test_disabling_mobile_money_rail_disables_payin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.payin.save'), [
                'environment' => 'sandbox',
                'api_key' => 'pk_bank_only',
                'api_secret' => 'sk_bank_only',
                'webhook_secret' => '',
                'default_callback_url' => '',
                'gateway_mode' => 'live',
                'mobile_money_threshold' => '3000000',
                'channels' => ['bank'],
                'intent' => 'save',
            ])
            ->assertRedirect();

        $this->assertFalse((bool) Setting::get('payin.enabled'));
        $this->assertSame(['bank'], Setting::get('integrations.partner_channels')['payin'] ?? null);
    }

    public function test_partner_workspace_shows_configuration_and_usage_tabs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.partner', ['partner' => 'payin']))
            ->assertOk()
            ->assertSee('Configuration')
            ->assertSee('Usage &amp; billing', false)
            ->assertDontSee('Enable PayIn for mobile money');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'usage']))
            ->assertOk()
            ->assertSee('Pricing (optional)')
            ->assertSee('Monthly usage');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.partner', ['partner' => 'selcom']))
            ->assertOk()
            ->assertSee('Supported rails')
            ->assertSee('Mobile money')
            ->assertSee('Bank transfer');
    }

    public function test_admin_can_save_payment_billing_model(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.integrations.billing', 'payin'), [
                'collection_fee_type' => 'percent',
                'collection_fee_value' => '1.5',
                'disbursement_fee_type' => 'fixed',
                'disbursement_fee_value' => '500',
            ])
            ->assertRedirect(route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'usage']));

        $billing = Setting::get('integrations.billing.payin');
        $this->assertSame('percent', $billing['collection_fee_type'] ?? null);
        $this->assertEquals(1.5, (float) ($billing['collection_fee_value'] ?? 0));
        $this->assertSame('fixed', $billing['disbursement_fee_type'] ?? null);
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
            ->assertSee('Selcom')
            ->assertSee('Unitxt SMS')
            ->assertSee('Add partner')
            ->assertSee('Check all health')
            ->assertSee('Open');
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
            ->assertRedirect(route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'configuration']));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.partner', ['partner' => 'payin']))
            ->assertOk()
            ->assertSee('Configuration')
            ->assertSee('Usage &amp; billing', false)
            ->assertSee('Supported rails')
            ->assertSee('Save &amp; test connection', false);
    }

    public function test_save_and_test_live_production_is_integration_ready_not_live_verified(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->mock(\App\Services\Integrations\IntegrationHealthService::class, function ($mock) {
            $mock->shouldReceive('check')
                ->once()
                ->with('payin', true)
                ->andReturn([
                    'ok' => true,
                    'message' => 'Authenticated with PayIn (production).',
                    'checked_at' => now()->toIso8601String(),
                    'provider' => 'payin',
                    'guidance' => [],
                    'probe_kind' => 'connection',
                ]);
        });

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.payin.save'), [
                'environment' => 'production',
                'api_key' => 'pk_live',
                'api_secret' => 'sk_live',
                'webhook_secret' => 'whsec_live',
                'default_callback_url' => 'https://www.kopafasta.com/webhooks/payin',
                'gateway_mode' => 'live',
                'mobile_money_threshold' => '3000000',
                'channels' => ['mobile_money'],
                'intent' => 'save_and_test',
            ])
            ->assertRedirect()
            ->assertSessionHas('feedback.title', 'PayIn connection successful')
            ->assertSessionHas('feedback.tone', 'success');

        $statuses = collect(session('feedback.statuses'));
        $this->assertTrue($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'authentication' && ($row['value'] ?? '') === 'Connected'));
        $this->assertTrue($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'readiness' && ($row['value'] ?? '') === 'Integration Ready'));
        $this->assertFalse($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'connection' && ($row['value'] ?? '') === 'Not tested'));
        $this->assertFalse($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'readiness' && ($row['value'] ?? '') === 'Live Verified'));
        $this->assertStringContainsString('end-to-end rehearsal', strtolower((string) session('feedback.action_required')));
    }

    public function test_payin_partner_page_shows_provider_live_test_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'configuration']))
            ->assertOk()
            ->assertSee('id="live-test"', false)
            ->assertSee('Mobile number')
            ->assertSee('Amount (TZS)')
            ->assertSee('Continue to payment.show', false);
    }

    public function test_notifications_settings_page_saves_delivery_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.notifications'))
            ->assertOk()
            ->assertSee('Management digests')
            ->assertSee('Operational assignments');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.notifications.save'), [
                'management' => [
                    'enabled' => '1',
                    'cadence' => 'daily',
                    'events' => ['integration_failures' => '1', 'sla_breaches' => '1'],
                    'channels' => ['in_app' => '1', 'email' => '1'],
                ],
                'operational' => [
                    'enabled' => '1',
                    'events' => ['screening' => '1', 'recovery' => '1'],
                    'channels' => ['in_app' => '1'],
                ],
            ])
            ->assertRedirect(route('admin.settings.notifications'))
            ->assertSessionHas('feedback');

        $stored = Setting::get('notifications.delivery');
        $this->assertSame('daily', $stored['management']['cadence'] ?? null);
        $this->assertTrue((bool) ($stored['management']['events']['integration_failures'] ?? false));
        $this->assertTrue((bool) ($stored['operational']['events']['recovery'] ?? false));
    }

    public function test_email_smtp_partner_page_embeds_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.partner', ['partner' => 'email_smtp']))
            ->assertOk()
            ->assertSee('Email (SMTP) configuration')
            ->assertSee('SMTP host')
            ->assertSee('Send test email');
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
