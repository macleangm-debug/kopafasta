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
                'api_key_replace' => '1',
                'api_key' => 'pk_test_abc',
                'api_secret_replace' => '1',
                'api_secret' => 'sk_test_xyz',
                'webhook_secret_replace' => '1',
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
                'api_key_replace' => '1',
                'api_key' => 'pk_keep_me',
                'api_secret_replace' => '1',
                'api_secret' => 'sk_keep_me',
                'webhook_secret_replace' => '1',
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
                'api_key_replace' => '1',
                'api_key' => 'pk_plain',
                'api_secret_replace' => '1',
                'api_secret' => 'sk_plain',
                'webhook_secret_replace' => '1',
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
                'api_key_replace' => '1',
                'api_key' => 'pk_live',
                'api_secret_replace' => '1',
                'api_secret' => 'sk_live',
                'webhook_secret_replace' => '1',
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
                'api_key_replace' => '1',
                'api_key' => 'pk_bank_only',
                'api_secret_replace' => '1',
                'api_secret' => 'sk_bank_only',
                'webhook_secret_replace' => '1',
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
                'api_key_replace' => '1',
                'api_key' => 'pk_live',
                'api_secret_replace' => '1',
                'api_secret' => 'sk_live',
                'webhook_secret_replace' => '1',
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
            ->assertSee('data-live-test-trigger', false)
            ->assertSee('data-integration-live-test-panel', false)
            ->assertSee('integrationLiveTest(', false)
            ->assertSee('PayIn operational rehearsal')
            ->assertSee('Review test payment')
            ->assertSee('Continue to payment.show', false)
            ->assertSee('data-loading-label="Opening payment…"', false)
            ->assertSee('data-loading-label="Opening…"', false)
            ->assertDontSee('id="live-test"', false)
            ->assertDontSee('name=\\"phone\\"', false);
    }

    public function test_sms_email_crb_partner_pages_use_live_test_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        foreach ([
            'unitxt' => 'Review test SMS',
            'email_smtp' => 'Review test email',
            'crb' => 'Review CRB enquiry',
        ] as $partner => $reviewCta) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.settings.integrations.partner', ['partner' => $partner]))
                ->assertOk()
                ->assertSee('data-live-test-trigger', false)
                ->assertSee('data-integration-live-test-panel', false)
                ->assertSee('integrationLiveTest(', false)
                ->assertSee($reviewCta);
        }
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

    public function test_edit_form_hydrates_persisted_production_live_not_defaults(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'production',
            'payin.api_key' => 'pk_prod_xxxx',
            'payin.api_secret' => 'sk_prod_yyyy',
            'payin.webhook_secret' => 'whsec_prod',
            'payments.gateway_mode' => 'live',
            'payments.mobile_money_threshold' => 3000000,
            'integrations.partner_channels' => ['payin' => ['mobile_money']],
            'integrations.health.payin' => [
                'ok' => true,
                'message' => 'Authenticated',
                'checked_at' => now()->toIso8601String(),
                'provider' => 'payin',
            ],
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.partner', ['partner' => 'payin', 'tab' => 'configuration']))
            ->assertOk()
            ->assertSee('data-persisted-environment="production"', false)
            ->assertSee('data-persisted-gateway-mode="live"', false)
            ->assertSee('data-no-draft', false)
            ->assertDontSee('sk_prod_yyyy')
            ->assertDontSee('whsec_prod')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="environment"[\s\S]*?<option[^>]*value="production"[^>]*selected/i',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/name="gateway_mode"[\s\S]*?<option[^>]*value="live"[^>]*selected/i',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="gateway_mode"[\s\S]*?<option[^>]*value="dummy"[^>]*selected/i',
            $html
        );
    }

    public function test_edit_form_hydrates_persisted_sandbox_dummy(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'sandbox',
            'payin.api_key' => 'pk_sand',
            'payin.api_secret' => 'sk_sand',
            'payin.webhook_secret' => 'whsec_sand',
            'payments.gateway_mode' => 'dummy',
            'payments.mobile_money_threshold' => 3000000,
            'integrations.partner_channels' => ['payin' => ['mobile_money']],
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.partner', ['partner' => 'payin']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="environment"[\s\S]*?<option[^>]*value="sandbox"[^>]*selected/i',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/name="gateway_mode"[\s\S]*?<option[^>]*value="dummy"[^>]*selected/i',
            $html
        );
    }

    public function test_save_without_replace_keeps_existing_secrets(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'production',
            'payin.api_key' => 'pk_keep',
            'payin.api_secret' => 'sk_keep',
            'payin.webhook_secret' => 'whsec_keep',
            'payments.gateway_mode' => 'live',
            'payments.mobile_money_threshold' => 3000000,
            'integrations.partner_channels' => ['payin' => ['mobile_money']],
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.payin.save'), [
                'environment' => 'production',
                'api_key' => '',
                'api_secret' => '',
                'webhook_secret' => '',
                'default_callback_url' => 'https://www.kopafasta.com/webhooks/payin',
                'gateway_mode' => 'live',
                'mobile_money_threshold' => '3000000',
                'channels' => ['mobile_money'],
                'intent' => 'save',
            ])
            ->assertRedirect();

        $this->assertSame('pk_keep', Setting::get('payin.api_key'));
        $this->assertSame('sk_keep', Setting::get('payin.api_secret'));
        $this->assertSame('whsec_keep', Setting::get('payin.webhook_secret'));
        $this->assertSame('production', Setting::get('payin.environment'));
        $this->assertSame('live', Setting::get('payments.gateway_mode'));
    }

    public function test_check_health_does_not_mutate_configuration(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'production',
            'payin.api_key' => 'pk_health',
            'payin.api_secret' => 'sk_health',
            'payin.webhook_secret' => 'whsec_health',
            'payments.gateway_mode' => 'live',
        ]);

        $this->mock(\App\Services\Integrations\IntegrationHealthService::class, function ($mock) {
            $mock->shouldReceive('check')->once()->with('payin', true)->andReturn([
                'ok' => true,
                'message' => 'Authenticated with PayIn (production).',
                'checked_at' => now()->toIso8601String(),
                'provider' => 'payin',
                'guidance' => [],
                'probe_kind' => 'connection',
            ]);
            $mock->shouldReceive('lastStatus')->andReturn([
                'ok' => true,
                'message' => 'Authenticated',
                'checked_at' => now()->toIso8601String(),
                'unknown' => false,
            ]);
        });

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.payin.health'))
            ->assertRedirect();

        $this->assertSame('production', Setting::get('payin.environment'));
        $this->assertSame('live', Setting::get('payments.gateway_mode'));
        $this->assertSame('pk_health', Setting::get('payin.api_key'));
        $statuses = collect(session('feedback.statuses'));
        $this->assertTrue($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'authentication' && ($row['value'] ?? '') === 'Connected'));
        $this->assertFalse($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'readiness' && ($row['value'] ?? '') === 'Live Verified'));
        $this->assertFalse($statuses->contains(fn ($row) => ($row['key'] ?? '') === 'connection' && ($row['value'] ?? '') === 'Not tested'));
    }

    public function test_payin_live_test_creates_obligation_without_calling_collect(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'sandbox',
            'payin.api_key' => 'pk_test',
            'payin.api_secret' => 'sk_test',
            'payin.webhook_secret' => 'whsec_test',
            'payments.gateway_mode' => 'live',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        \Illuminate\Support\Facades\Http::fake();

        $customersBefore = \App\Models\Customer::query()->count();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.integrations.live-test'), [
                'suite' => 'payment',
                'partner' => 'payin',
                'phone' => '255715222132',
                'amount' => 1000,
            ]);

        \Illuminate\Support\Facades\Http::assertNothingSent();

        $payment = \App\Models\CustomerPayment::query()->latest('id')->first();
        $this->assertNotNull($payment, 'Feedback: '.json_encode(session('feedback')).' Result: '.json_encode(session('live_test_result')));
        $this->assertNull($payment->customer_id);
        $this->assertSame('awaiting_payment', $payment->status);
        $this->assertTrue((bool) data_get($payment->provider_meta, 'integration_live_test'));
        $this->assertNull($payment->provider_ref);
        $this->assertNull(Setting::get('integrations.live_verified.payin'));
        $this->assertSame($customersBefore, \App\Models\Customer::query()->count());
        $this->assertSame(0, \App\Models\Customer::query()->where('last_name', 'LiveTest')->count());

        $response->assertRedirect(route('admin.settings.integrations.live-test.payment', $payment));
    }

    public function test_admin_live_test_payment_gate_is_canonical_and_pay_calls_collect(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'sandbox',
            'payin.api_key' => 'pk_test',
            'payin.api_secret' => 'sk_test',
            'payments.gateway_mode' => 'live',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $payment = \App\Models\CustomerPayment::create([
            'reference' => 'PAY-LIVE-GATE-1',
            'customer_id' => null,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'mobile_number' => '255715222132',
            'provider_meta' => [
                'integration_live_test' => true,
                'integration_rehearsal' => true,
                'integration_partner' => 'payin',
                'awaiting_collection' => true,
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.live-test.payment', $payment))
            ->assertOk()
            ->assertSee('Controlled integration rehearsal', false)
            ->assertSee('data-payment-surface', false)
            ->assertSee(route('admin.settings.integrations.live-test.payment.pay', $payment), false);

        $payIn = \Mockery::mock(PayInService::class)->makePartial();
        $payIn->shouldReceive('isConfigured')->andReturn(true);
        $payIn->shouldReceive('isLiveCollectionEnabled')->andReturn(true);
        $payIn->shouldReceive('normalizePhone')->andReturnUsing(fn ($p) => preg_replace('/\D+/', '', (string) $p));
        $payIn->shouldReceive('normalizeOperator')->andReturn(null);
        $payIn->shouldReceive('collect')->once()->andReturn([
            'ok' => true,
            'request_ref' => 'PAYREF-LIVE-1',
            'status' => 'processing',
            'operator' => 'M-Pesa',
            'message' => 'Collection request sent.',
            'raw' => [],
            'idempotency_key' => 'idem-1',
        ]);
        $this->app->instance(PayInService::class, $payIn);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.integrations.live-test.payment.pay', $payment), [
                'payment_method' => 'mobile_money',
                'mobile_number' => '255715222132',
            ])
            ->assertRedirect(route('admin.settings.integrations.live-test.payment', $payment));

        $fresh = $payment->fresh();
        $this->assertSame('processing', $fresh->status);
        $this->assertSame('PAYREF-LIVE-1', $fresh->provider_ref);
        $this->assertNull(Setting::get('integrations.live_verified.payin'));
    }

    public function test_admin_live_test_pay_ajax_returns_waiting_surface_state(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'sandbox',
            'payin.api_key' => 'pk_test',
            'payin.api_secret' => 'sk_test',
            'payments.gateway_mode' => 'live',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $payment = \App\Models\CustomerPayment::create([
            'reference' => 'PAY-LIVE-AJAX-1',
            'customer_id' => null,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'mobile_number' => '255715222132',
            'provider_meta' => [
                'integration_live_test' => true,
                'integration_rehearsal' => true,
                'integration_partner' => 'payin',
                'awaiting_collection' => true,
            ],
        ]);

        $payIn = \Mockery::mock(PayInService::class)->makePartial();
        $payIn->shouldReceive('isConfigured')->andReturn(true);
        $payIn->shouldReceive('isLiveCollectionEnabled')->andReturn(true);
        $payIn->shouldReceive('normalizePhone')->andReturn('255715222132');
        $payIn->shouldReceive('normalizeOperator')->andReturn(null);
        $payIn->shouldReceive('collect')->once()->andReturn([
            'ok' => true,
            'request_ref' => 'PAYREF-AJAX-1',
            'status' => 'processing',
            'operator' => 'Tigo Pesa',
            'message' => 'Collection accepted.',
            'raw' => [],
            'idempotency_key' => 'idem-ajax-1',
        ]);
        $this->app->instance(PayInService::class, $payIn);

        $this->actingAs($admin, 'admin')
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('admin.settings.integrations.live-test.payment.pay', $payment), [
                'payment_method' => 'mobile_money',
                'mobile_number' => '255715222132',
            ])
            ->assertOk()
            ->assertJsonPath('state', 'waiting')
            ->assertJsonPath('reference', 'PAY-LIVE-AJAX-1')
            ->assertJsonPath('status', 'processing');

        $this->assertSame('processing', $payment->fresh()->status);
    }

    public function test_admin_live_test_pay_operator_failure_stays_on_same_obligation(): void
    {
        Setting::setMany([
            'payin.enabled' => true,
            'payin.environment' => 'sandbox',
            'payin.api_key' => 'pk_test',
            'payin.api_secret' => 'sk_test',
            'payments.gateway_mode' => 'live',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $payment = \App\Models\CustomerPayment::create([
            'reference' => 'PAY-LIVE-OP-1',
            'customer_id' => null,
            'payment_type' => 'registration_fee',
            'payment_method' => 'mobile_money',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'awaiting_payment',
            'mobile_number' => '255712345678',
            'provider_meta' => [
                'integration_live_test' => true,
                'integration_rehearsal' => true,
                'integration_partner' => 'payin',
                'awaiting_collection' => true,
            ],
        ]);

        $payIn = \Mockery::mock(PayInService::class)->makePartial();
        $payIn->shouldReceive('isConfigured')->andReturn(true);
        $payIn->shouldReceive('isLiveCollectionEnabled')->andReturn(true);
        $payIn->shouldReceive('normalizePhone')->andReturn('255712345678');
        $payIn->shouldReceive('normalizeOperator')->andReturn(null);
        $payIn->shouldReceive('collect')->once()->andThrow(
            \Illuminate\Validation\ValidationException::withMessages([
                'payment_phone' => ['Could not detect operator from phone number.'],
            ])
        );
        $this->app->instance(PayInService::class, $payIn);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.settings.integrations.live-test.payment', $payment))
            ->post(route('admin.settings.integrations.live-test.payment.pay', $payment), [
                'payment_method' => 'mobile_money',
                'mobile_number' => '255712345678',
            ])
            ->assertRedirect(route('admin.settings.integrations.live-test.payment', $payment))
            ->assertSessionHas('show_collect_failed', true)
            ->assertSessionHas('collect_error');

        $this->assertSame(1, \App\Models\CustomerPayment::query()->count());
        $fresh = $payment->fresh();
        $this->assertSame('awaiting_payment', $fresh->status);
        $this->assertNull($fresh->provider_ref);
        $this->assertStringContainsString(
            'mobile-money network',
            strtolower((string) data_get($fresh->provider_meta, 'last_collect_error'))
        );
        $this->assertNull(Setting::get('integrations.live_verified.payin'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.integrations.live-test.payment', $payment))
            ->assertOk()
            ->assertSee(__('borrower.payment_waiting.operator_title'), false)
            ->assertSee('supported mobile-money network', false);
    }
}
