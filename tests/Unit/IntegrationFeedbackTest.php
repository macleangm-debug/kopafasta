<?php

namespace Tests\Unit;

use App\Services\Integrations\IntegrationCatalog;
use App\Services\Integrations\IntegrationFeedback;
use App\Services\Integrations\IntegrationHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationFeedbackTest extends TestCase
{
    use RefreshDatabase;
    public function test_settings_saved_never_implies_connection(): void
    {
        $feedback = app(IntegrationFeedback::class);
        $payload = $feedback->settingsSaved('PayIn');

        $this->assertSame('Settings saved', $payload['title']);
        $this->assertStringContainsString('has not been tested', $payload['message']);
        $this->assertSame('info', $payload['tone']);
        $this->assertTrue(collect($payload['statuses'])->contains(
            fn ($row) => $row['key'] === 'connection' && $row['value'] === 'Not tested'
        ));
    }

    public function test_connected_but_dummy_is_warning_not_failure(): void
    {
        $feedback = app(IntegrationFeedback::class);
        $payload = $feedback->fromHealth('payin', [
            'ok' => true,
            'message' => 'Authenticated with PayIn (production).',
            'probe_kind' => 'connection',
        ], [
            'configured' => true,
            'environment' => 'production',
            'gateway_mode' => 'dummy',
            'mode_label' => 'Dummy',
            'webhook' => 'Configured',
            'webhook_state' => 'success',
            'show_webhook' => true,
            'show_mode' => true,
            'ready' => false,
        ]);

        $this->assertSame('PayIn connection successful', $payload['title']);
        $this->assertSame('warning', $payload['tone']);
        $this->assertStringContainsString('Gateway Mode is Dummy', (string) $payload['action_required']);
        $this->assertTrue(collect($payload['statuses'])->contains(
            fn ($row) => $row['key'] === 'authentication' && $row['value'] === 'Connected'
        ));
        $this->assertTrue(collect($payload['statuses'])->contains(
            fn ($row) => $row['key'] === 'readiness' && in_array($row['value'], ['Action required', 'Integration Ready', 'Live Verified'], true)
        ));
        $this->assertFalse(collect($payload['statuses'])->contains(
            fn ($row) => ($row['key'] ?? '') === 'connection' && ($row['value'] ?? '') === 'Not tested'
        ));
    }

    public function test_not_configured_is_not_connection_failed(): void
    {
        $feedback = app(IntegrationFeedback::class);
        $payload = $feedback->fromHealth('payin', ['ok' => false, 'message' => 'missing'], [
            'configured' => false,
        ]);

        $this->assertSame('PayIn is not configured', $payload['title']);
        $this->assertNotSame('PayIn connection failed', $payload['title']);
    }

    public function test_sanitize_reason_never_echoes_raw_secret_like_text(): void
    {
        $feedback = app(IntegrationFeedback::class);
        $this->assertSame('Invalid credentials', $feedback->sanitizeReason('401 unauthorized key=sk_live_secret'));
        $this->assertSame('Connection could not be completed', $feedback->sanitizeReason('Exception: stack with secret sk_abc'));
    }

    public function test_integration_ready_when_live_production_webhook_and_connected(): void
    {
        $feedback = app(IntegrationFeedback::class);
        $payload = $feedback->fromHealth('payin', [
            'ok' => true,
            'message' => 'Authenticated with PayIn (production).',
            'probe_kind' => 'connection',
        ], [
            'configured' => true,
            'environment' => 'production',
            'gateway_mode' => 'live',
            'mode_label' => 'Live',
            'webhook' => 'Configured',
            'webhook_state' => 'success',
            'show_webhook' => true,
            'show_mode' => true,
            'ready' => true,
            'live_verified' => false,
        ]);

        $this->assertSame('success', $payload['tone']);
        $this->assertTrue(collect($payload['statuses'])->contains(
            fn ($row) => $row['key'] === 'readiness' && $row['value'] === 'Integration Ready'
        ));
        $this->assertStringContainsString('Live Verified', (string) $payload['action_required']);
    }

    public function test_services_resolve(): void
    {
        $this->assertInstanceOf(IntegrationFeedback::class, app(IntegrationFeedback::class));
        $this->assertInstanceOf(IntegrationHealthService::class, app(IntegrationHealthService::class));
        $this->assertInstanceOf(IntegrationCatalog::class, app(IntegrationCatalog::class));
    }
}
