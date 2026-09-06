<?php

namespace App\Services\Integrations;

use App\Models\Setting;
use App\Services\AuditService;
use App\Services\PayInService;
use Illuminate\Support\Facades\Auth;

/**
 * Shared Integration Feedback / Health response contract.
 *
 * Saving configuration ≠ testing connection ≠ being production-ready.
 */
class IntegrationFeedback
{
    public function __construct(
        protected IntegrationCatalog $catalog,
        protected IntegrationHealthService $health,
    ) {}

    /**
     * @param  list<array{key?:string,label:string,value:string,state:string}>  $statuses
     * @return array{tone:string,title:string,message:string,statuses:list<array{key?:string,label:string,value:string,state:string}>,action_required:?string,lines:list<string>,okLabel:string,secondaryLabel:?string,secondaryHref:?string}
     */
    public function settingsSaved(
        string $providerLabel,
        array $statuses = [],
        ?string $actionRequired = null,
    ): array {
        $rows = $statuses !== [] ? $statuses : [
            $this->status('configuration', 'Configuration', 'Saved', 'success'),
            $this->status('connection', 'Connection', 'Not tested', 'neutral'),
        ];

        $payload = $this->payload(
            tone: 'info',
            title: 'Settings saved',
            message: "Your {$providerLabel} configuration has been saved. Connection has not been tested.",
            statuses: $rows,
            actionRequired: $actionRequired,
        );

        $this->audit('integration.settings_saved', [
            'provider' => $providerLabel,
            'result' => 'saved_not_tested',
        ]);

        return $payload;
    }

    /**
     * Build feedback after a health/auth probe for any partner.
     *
     * @param  array<string, mixed>  $healthResult
     * @param  array<string, mixed>  $context
     * @return array{tone:string,title:string,message:string,statuses:list<array{key?:string,label:string,value:string,state:string}>,action_required:?string,lines:list<string>,okLabel:string,secondaryLabel:?string,secondaryHref:?string}
     */
    public function fromHealth(string $partnerKey, array $healthResult, array $context = []): array
    {
        $partner = $this->catalog->partner($partnerKey);
        $label = (string) ($partner['label'] ?? ucfirst($partnerKey));
        $configured = (bool) ($context['configured'] ?? true);
        $environment = (string) ($context['environment'] ?? '');
        $envLabel = $this->environmentLabel($environment !== '' ? $environment : ($context['environment_label'] ?? null));

        if (! $configured) {
            $payload = $this->payload(
                tone: 'info',
                title: "{$label} is not configured",
                message: 'Add the required credentials before testing the connection.',
                statuses: [
                    $this->status('configuration', 'Configuration', 'Incomplete', 'warning'),
                    $this->status('connection', 'Connection', 'Not tested', 'neutral'),
                    $this->status('readiness', 'Readiness', 'Not ready', 'error'),
                ],
            );

            $this->audit('integration.connection_test', [
                'provider' => $partnerKey,
                'result' => 'not_configured',
                'environment' => $envLabel,
            ]);

            return $payload;
        }

        $ok = (bool) ($healthResult['ok'] ?? false);
        $probeKind = (string) ($healthResult['probe_kind'] ?? ($context['probe_kind'] ?? 'connection'));
        $reason = $this->sanitizeReason((string) ($healthResult['reason'] ?? ($healthResult['message'] ?? '')));

        if ($ok && $probeKind === 'presence_only') {
            $payload = $this->payload(
                tone: 'warning',
                title: "{$label} configuration validated",
                message: 'Configuration validated — external connection could not be independently tested.',
                statuses: $this->baseStatuses(
                    environment: $envLabel,
                    authValue: 'Validated',
                    authState: 'warning',
                    context: $context,
                    ready: false,
                ),
                actionRequired: (string) ($context['action_required'] ?? null) ?: null,
            );

            $this->audit('integration.connection_test', [
                'provider' => $partnerKey,
                'result' => 'presence_only',
                'environment' => $envLabel,
            ]);

            return $payload;
        }

        if (! $ok) {
            $payload = $this->payload(
                tone: 'error',
                title: "{$label} connection failed",
                message: "Kopafasta could not authenticate with {$label}. Review the configuration and try again.",
                statuses: $this->baseStatuses(
                    environment: $envLabel,
                    authValue: 'Failed',
                    authState: 'error',
                    context: $context,
                    ready: false,
                ),
                actionRequired: $reason !== '' ? $reason : null,
                secondaryLabel: 'Review settings',
                secondaryHref: $this->settingsHref($partnerKey),
            );

            $this->audit('integration.connection_test', [
                'provider' => $partnerKey,
                'result' => 'failed',
                'environment' => $envLabel,
                'reason' => $reason,
            ]);

            return $payload;
        }

        $actionRequired = $this->actionRequiredFor($partnerKey, $context);
        $integrationReady = $actionRequired === null && (bool) ($context['ready'] ?? true);
        $liveVerified = $this->isLiveVerified($partnerKey, $context);
        $modeLabel = $this->modeLabel($partnerKey, $context);

        $readinessValue = 'Action required';
        $readinessState = 'warning';
        if ($liveVerified) {
            $readinessValue = 'Live Verified';
            $readinessState = 'success';
        } elseif ($integrationReady) {
            $readinessValue = 'Integration Ready';
            $readinessState = 'success';
        }

        $statuses = $this->baseStatuses(
            environment: $envLabel,
            authValue: 'Connected',
            authState: 'success',
            context: array_merge($context, [
                'mode_label' => $modeLabel,
                'mode_state' => $actionRequired ? 'warning' : 'success',
                'readiness_value' => $readinessValue,
                'readiness_state' => $readinessState,
            ]),
            ready: $integrationReady || $liveVerified,
        );

        // Never include a "Connection: Not tested" row after a probe has run.
        $statuses = array_values(array_filter(
            $statuses,
            fn (array $row) => ! (($row['key'] ?? '') === 'connection' && ($row['value'] ?? '') === 'Not tested')
        ));

        $softNote = null;
        if ($integrationReady && ! $liveVerified) {
            $softNote = 'Integration is ready. A controlled end-to-end rehearsal is still required before Live Verified.';
        }

        $payload = $this->payload(
            tone: ($integrationReady || $liveVerified) ? 'success' : 'warning',
            title: "{$label} connection successful",
            message: $envLabel !== ''
                ? "Kopafasta successfully authenticated with {$label} in the {$envLabel} environment."
                : "Kopafasta successfully authenticated with {$label}.",
            statuses: $statuses,
            actionRequired: $actionRequired ?: $softNote,
        );

        $this->audit('integration.connection_test', [
            'provider' => $partnerKey,
            'result' => $liveVerified ? 'live_verified' : ($integrationReady ? 'integration_ready' : 'connected_action_required'),
            'environment' => $envLabel,
        ]);

        return $payload;
    }

    /**
     * PayIn-specific context from current settings after save.
     *
     * @return array<string, mixed>
     */
    public function payinContext(?string $gatewayMode = null, ?string $environment = null): array
    {
        $payin = Setting::group('payin');
        $mode = $gatewayMode ?? (Setting::get('payments.gateway_mode') ?? config('payments.gateway_mode', 'dummy'));
        $env = $environment ?? (string) ($payin['environment'] ?? config('payin.environment', 'sandbox'));
        $configured = app(PayInService::class)->isConfigured();
        $webhookConfigured = filled($payin['webhook_secret'] ?? null);
        $integrationReady = $configured
            && $env === 'production'
            && $mode === 'live'
            && $webhookConfigured;
        $liveVerified = $this->isLiveVerified('payin');

        return [
            'configured' => $configured,
            'environment' => $env,
            'gateway_mode' => $mode,
            'mode_label' => $mode === 'live' ? 'Live' : 'Dummy',
            'webhook' => $webhookConfigured ? 'Configured' : 'Not configured',
            'webhook_state' => $webhookConfigured ? 'success' : 'warning',
            'show_webhook' => true,
            'show_mode' => true,
            'ready' => $integrationReady,
            'live_verified' => $liveVerified,
        ];
    }

    /** @param  array<string, mixed>  $context */
    public function isLiveVerified(string $partnerKey, array $context = []): bool
    {
        if (array_key_exists('live_verified', $context)) {
            return (bool) $context['live_verified'];
        }

        $raw = Setting::get("integrations.live_verified.{$partnerKey}");
        if (is_array($raw)) {
            return filled($raw['verified_at'] ?? null);
        }

        return (bool) $raw;
    }

    public function markLiveVerified(string $partnerKey): void
    {
        Setting::set("integrations.live_verified.{$partnerKey}", [
            'verified_at' => now()->toIso8601String(),
            'verified_by' => Auth::guard('admin')->id() ?? Auth::id(),
        ]);

        $this->audit('integration.live_verified', [
            'provider' => $partnerKey,
            'result' => 'live_verified',
        ]);
    }

    /**
     * Compact persistent summary for hub/partner pages.
     *
     * @return array{headline:string,detail:string,state:string,last_tested_at:?string}
     */
    public function persistentSummary(string $partnerKey): array
    {
        $status = $this->health->lastStatus($partnerKey);
        $unknown = ! empty($status['unknown']);
        $ok = ! empty($status['ok']);
        $partner = $this->catalog->partner($partnerKey);
        $context = $partnerKey === 'payin' ? $this->payinContext() : [];

        if ($unknown) {
            return [
                'headline' => 'Not tested',
                'detail' => 'Configured · Connected · Integration Ready are unknown until you test',
                'state' => 'neutral',
                'last_tested_at' => null,
            ];
        }

        if (! $ok) {
            return [
                'headline' => 'Failed',
                'detail' => (string) ($status['message'] ?? 'Connection failed'),
                'state' => 'error',
                'last_tested_at' => $status['checked_at'] ?? null,
            ];
        }

        $bits = ['Connected'];
        if ($partnerKey === 'payin') {
            $bits[] = $this->environmentLabel((string) ($context['environment'] ?? ''));
            $bits[] = (string) ($context['mode_label'] ?? 'Dummy');
            if (! empty($context['live_verified'])) {
                return [
                    'headline' => 'Connected · Live Verified',
                    'detail' => implode(' · ', array_filter($bits)),
                    'state' => 'success',
                    'last_tested_at' => $status['checked_at'] ?? null,
                ];
            }
            if (! empty($context['ready'])) {
                return [
                    'headline' => 'Connected · Integration Ready',
                    'detail' => implode(' · ', array_filter($bits)),
                    'state' => 'success',
                    'last_tested_at' => $status['checked_at'] ?? null,
                ];
            }

            return [
                'headline' => 'Connected · Action required',
                'detail' => implode(' · ', array_filter($bits)),
                'state' => 'warning',
                'last_tested_at' => $status['checked_at'] ?? null,
            ];
        }

        return [
            'headline' => implode(' · ', array_filter($bits)),
            'detail' => (string) ($partner['label'] ?? $partnerKey).' health is current',
            'state' => 'success',
            'last_tested_at' => $status['checked_at'] ?? null,
        ];
    }

    /**
     * @param  list<array{key?:string,label:string,value:string,state:string}>  $statuses
     * @return array{tone:string,title:string,message:string,statuses:list<array{key?:string,label:string,value:string,state:string}>,action_required:?string,lines:list<string>,okLabel:string,secondaryLabel:?string,secondaryHref:?string}
     */
    public function payload(
        string $tone,
        string $title,
        string $message,
        array $statuses = [],
        ?string $actionRequired = null,
        string $okLabel = 'Got it',
        ?string $secondaryLabel = null,
        ?string $secondaryHref = null,
    ): array {
        $lines = [];
        foreach ($statuses as $row) {
            $lines[] = ($row['label'] ?? '').' — '.($row['value'] ?? '');
        }
        if ($actionRequired) {
            $lines[] = 'Action required: '.$actionRequired;
        }

        return [
            'tone' => $tone,
            'title' => $title,
            'message' => $message,
            'statuses' => array_values($statuses),
            'action_required' => $actionRequired,
            // Backward-compatible for older modal consumers / tests.
            'lines' => $lines,
            'okLabel' => $okLabel,
            'secondaryLabel' => $secondaryLabel,
            'secondaryHref' => $secondaryHref,
        ];
    }

    /** @return array{key:string,label:string,value:string,state:string} */
    public function status(string $key, string $label, string $value, string $state): array
    {
        return compact('key', 'label', 'value', 'state');
    }

    public function sanitizeReason(string $message): string
    {
        $lower = strtolower($message);
        if ($message === '') {
            return '';
        }
        if (str_contains($lower, 'invalid') || str_contains($lower, '401') || str_contains($lower, 'unauthorized') || str_contains($lower, 'forbidden')) {
            return 'Invalid credentials';
        }
        if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
            return 'Request timed out';
        }
        if (str_contains($lower, 'unavailable') || str_contains($lower, '503') || str_contains($lower, '502')) {
            return 'Provider unavailable';
        }
        if (str_contains($lower, 'webhook')) {
            return 'Webhook verification failed';
        }
        if (str_contains($lower, 'environment') || str_contains($lower, 'sandbox') || str_contains($lower, 'production')) {
            return 'Unsupported environment';
        }
        if (str_contains($lower, 'missing') || str_contains($lower, 'disabled') || str_contains($lower, 'incomplete')) {
            return 'Configuration incomplete';
        }

        // Never echo raw provider/exception text that might include secrets.
        return 'Connection could not be completed';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{key?:string,label:string,value:string,state:string}>
     */
    protected function baseStatuses(
        string $environment,
        string $authValue,
        string $authState,
        array $context,
        bool $ready,
    ): array {
        $rows = [
            $this->status('configuration', 'Configuration', 'Saved', 'success'),
        ];

        if ($environment !== '') {
            $rows[] = $this->status('environment', 'Environment', $environment, 'neutral');
        }

        $rows[] = $this->status('authentication', 'API authentication', $authValue, $authState);

        if (! empty($context['show_webhook'])) {
            $rows[] = $this->status(
                'webhook',
                'Webhook',
                (string) ($context['webhook'] ?? 'Not applicable'),
                (string) ($context['webhook_state'] ?? 'neutral'),
            );
        }

        if (! empty($context['show_mode']) || isset($context['mode_label'])) {
            $rows[] = $this->status(
                'mode',
                'Gateway mode',
                (string) ($context['mode_label'] ?? ($context['mode'] ?? '—')),
                (string) ($context['mode_state'] ?? 'neutral'),
            );
        }

        $rows[] = $this->status(
            'readiness',
            'Integration readiness',
            (string) ($context['readiness_value'] ?? ($ready
                ? 'Integration Ready'
                : ($authState === 'error' ? 'Not ready' : 'Action required'))),
            (string) ($context['readiness_state'] ?? ($ready
                ? 'success'
                : ($authState === 'error' ? 'error' : 'warning'))),
        );

        return $rows;
    }

    /** @param  array<string, mixed>  $context */
    protected function actionRequiredFor(string $partnerKey, array $context): ?string
    {
        if ($partnerKey === 'payin' && (($context['gateway_mode'] ?? '') !== 'live')) {
            return 'Gateway Mode is Dummy. Switch to Live before accepting real payments.';
        }

        if (! empty($context['action_required'])) {
            return (string) $context['action_required'];
        }

        return null;
    }

    /** @param  array<string, mixed>  $context */
    protected function modeLabel(string $partnerKey, array $context): string
    {
        if (isset($context['mode_label'])) {
            return (string) $context['mode_label'];
        }

        if ($partnerKey === 'payin') {
            return (($context['gateway_mode'] ?? '') === 'live') ? 'Live' : 'Dummy';
        }

        return (string) ($context['mode'] ?? '');
    }

    protected function environmentLabel(?string $environment): string
    {
        $environment = strtolower(trim((string) $environment));

        return match ($environment) {
            'production', 'live' => 'Production',
            'sandbox', 'test', 'staging' => 'Sandbox',
            '' => '',
            default => ucfirst($environment),
        };
    }

    protected function settingsHref(string $partnerKey): ?string
    {
        try {
            return route('admin.settings.integrations.partner', ['partner' => $partnerKey, 'tab' => 'configuration']);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param  array<string, mixed>  $context */
    protected function audit(string $event, array $context): void
    {
        try {
            app(AuditService::class)->logAdminAction(
                Auth::guard('admin')->user() ?? Auth::user(),
                $event,
                null,
                $context,
            );
        } catch (\Throwable) {
            // Audit must never break the settings UX.
        }
    }
}
