<?php

namespace App\Services\Integrations;

use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PayInService;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IntegrationHealthService
{
    public function __construct(
        protected IntegrationCatalog $catalog,
    ) {}

    /** @return array{ok: bool, message: string, checked_at: ?string, provider: ?string, guidance: list<string>, unknown?: bool} */
    public function lastStatus(string $partnerKey): array
    {
        $raw = Setting::get("integrations.health.{$partnerKey}");
        if (! is_array($raw)) {
            return [
                'ok' => false,
                'message' => 'Not checked yet',
                'checked_at' => null,
                'provider' => $partnerKey,
                'guidance' => $this->guidanceFor($partnerKey, false, 'Not checked yet'),
                'unknown' => true,
            ];
        }

        $ok = (bool) ($raw['ok'] ?? false);
        $message = (string) ($raw['message'] ?? '');

        return [
            'ok' => $ok,
            'message' => $message,
            'checked_at' => $raw['checked_at'] ?? null,
            'provider' => $raw['provider'] ?? $partnerKey,
            'guidance' => is_array($raw['guidance'] ?? null)
                ? $raw['guidance']
                : $this->guidanceFor($partnerKey, $ok, $message),
            'unknown' => false,
        ];
    }

    /**
     * @return array{ok: bool, message: string, checked_at: string, provider: string, guidance: list<string>}
     */
    public function check(string $partnerKey, bool $notifyOnFailure = true): array
    {
        $partner = $this->catalog->partner($partnerKey);
        if (! $partner || ($partner['status'] ?? '') !== 'available') {
            return $this->store($partnerKey, false, 'Partner is not available for health checks.');
        }

        $result = match ($partnerKey) {
            'payin' => app(PayInService::class)->healthCheck(),
            'unitxt' => app(SmsManager::class)->healthCheck(),
            'email_smtp' => $this->checkEmail(),
            'crb' => $this->checkCrb(),
            default => [
                'ok' => false,
                'message' => 'No automated health probe yet — open Configure and verify credentials manually.',
                'probe_kind' => 'none',
            ],
        };

        $stored = $this->store(
            $partnerKey,
            (bool) ($result['ok'] ?? false),
            (string) ($result['message'] ?? 'Health check finished.'),
            $partnerKey,
            isset($result['probe_kind']) ? (string) $result['probe_kind'] : null,
        );

        if ($notifyOnFailure && ! $stored['ok']) {
            $this->notifyAdmins($partner, $stored);
        }

        return $stored;
    }

    /**
     * @return list<array{key: string, ok: bool, message: string}>
     */
    public function checkAll(bool $notifyOnFailure = true): array
    {
        $results = [];
        foreach ($this->catalog->availableForHealthCheck() as $partner) {
            $status = $this->check($partner['key'], $notifyOnFailure);
            $results[] = [
                'key' => $partner['key'],
                'ok' => $status['ok'],
                'message' => $status['message'],
            ];
        }

        return $results;
    }

    /** @return list<array{key: string, label: string, message: string, url: string}> */
    public function unhealthyPartners(): array
    {
        $items = [];
        foreach ($this->catalog->availableForHealthCheck() as $partner) {
            $status = $this->lastStatus($partner['key']);
            if (($status['unknown'] ?? false) || ($status['ok'] ?? false)) {
                continue;
            }

            $route = $partner['settings_route'] ?? 'admin.settings.integrations';
            $items[] = [
                'key' => $partner['key'],
                'label' => (string) $partner['label'],
                'message' => (string) ($status['message'] ?: 'Integration unhealthy'),
                'url' => $route === 'admin.settings.integrations.partner'
                    ? route($route, $partner['key'])
                    : route($route),
            ];
        }

        return $items;
    }

    /**
     * @return array{ok: bool, message: string, checked_at: string, provider: string, guidance: list<string>, probe_kind?: string}
     */
    protected function store(string $partnerKey, bool $ok, string $message, ?string $provider = null, ?string $probeKind = null): array
    {
        $payload = [
            'ok' => $ok,
            'message' => $message,
            'checked_at' => now()->toIso8601String(),
            'provider' => $provider ?: $partnerKey,
            'guidance' => $this->guidanceFor($partnerKey, $ok, $message),
        ];
        if ($probeKind) {
            $payload['probe_kind'] = $probeKind;
        }

        Setting::set("integrations.health.{$partnerKey}", $payload);

        return $payload;
    }

    /** @return list<string> */
    public function guidanceFor(string $partnerKey, bool $ok, string $message): array
    {
        if ($ok) {
            return match ($partnerKey) {
                'payin' => [
                    'Set Payment gateway mode to Live (not Dummy) so borrowers get USSD prompts.',
                    'Confirm the PayIn dashboard callback URL matches this app’s /webhooks/payin endpoint.',
                ],
                default => ['Integration is healthy.'],
            };
        }

        $lower = strtolower($message);
        $tips = [];

        if (str_contains($lower, 'disabled') || str_contains($lower, 'missing') || str_contains($lower, 'incomplete')) {
            $tips[] = 'Open Configuration, select Mobile money under Supported rails, paste API credentials, then Save & test connection.';
        }
        if (str_contains($lower, 'ip') || str_contains($lower, 'forbidden') || str_contains($lower, '401') || str_contains($lower, '403')) {
            $tips[] = 'In the partner dashboard, whitelist this server’s public IP and wait for approval.';
        }
        if (str_contains($lower, 'timeout') || str_contains($lower, 'connection failed') || str_contains($lower, 'could not resolve')) {
            $tips[] = 'Check outbound HTTPS from the server and that the partner environment (sandbox vs production) matches your keys.';
        }

        $tips[] = match ($partnerKey) {
            'payin' => 'Review Settings → Integrations → PayIn → Configuration, then PayIn Dashboard → Webhook & API Keys.',
            'unitxt', 'email_smtp' => 'Review Settings → Integrations → partner workspace → SMS / Email gateways.',
            'crb' => 'Review Settings → Integrations → CRB → Configuration (or CRB settings).',
            default => 'Open the partner workspace Configuration tab and re-check credentials.',
        };

        return array_values(array_unique($tips));
    }

    /** @return array{ok: bool, message: string, probe_kind?: string} */
    protected function checkEmail(): array
    {
        $g = Setting::group('gateway');
        $from = (string) ($g['email_from_address'] ?? '');
        $host = (string) ($g['email_smtp_host'] ?? '');
        $port = (int) ($g['email_smtp_port'] ?? 587);

        if ($from === '') {
            return ['ok' => false, 'message' => 'Email from address is not configured.', 'probe_kind' => 'connection'];
        }

        if ($host === '' && config('mail.default') === 'log') {
            return [
                'ok' => true,
                'message' => 'Email is using the log mailer (dev). Configure SMTP for production.',
                'probe_kind' => 'presence_only',
            ];
        }

        if ($host === '') {
            return ['ok' => false, 'message' => 'SMTP host is missing.', 'probe_kind' => 'connection'];
        }

        // Non-transactional TCP reachability probe — does not send mail.
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port > 0 ? $port : 587, $errno, $errstr, 5);
        if ($socket === false) {
            return [
                'ok' => false,
                'message' => 'SMTP host is unreachable.',
                'probe_kind' => 'connection',
                'reason' => 'Provider unavailable',
            ];
        }
        fclose($socket);

        return [
            'ok' => true,
            'message' => 'SMTP host is reachable.',
            'probe_kind' => 'connection',
        ];
    }

    /** @return array{ok: bool, message: string, probe_kind?: string} */
    protected function checkCrb(): array
    {
        $service = app(\App\Services\CrbService::class);
        if ($service->usesStub()) {
            return [
                'ok' => true,
                'message' => 'CRB stub driver is active (sandbox/dev).',
                'probe_kind' => 'presence_only',
            ];
        }

        $endpoint = Setting::get('kyc.crb_endpoint') ?: config('crb.endpoint');
        $email = Setting::get('kyc.crb_email') ?: config('crb.email');

        if (! filled($endpoint) || ! filled($email)) {
            return [
                'ok' => false,
                'message' => 'CRB live credentials incomplete (endpoint/email).',
                'probe_kind' => 'connection',
            ];
        }

        return [
            'ok' => true,
            'message' => 'CRB live credentials are present.',
            'probe_kind' => 'presence_only',
        ];
    }

    protected function notifyAdmins(array $partner, array $status): void
    {
        $label = (string) ($partner['label'] ?? $partner['key']);
        $subject = "Integration unhealthy: {$label}";
        $body = "{$label} failed its health check.\n\n{$status['message']}\n\nOpen Settings → Integrations to review.";
        $smsBody = Str::limit("{$label} integration unhealthy. Check Settings → Integrations.", 160, '…');

        $staff = User::query()
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'super_admin', 'manager'])
            ->get(['email', 'phone']);

        if ($staff->isEmpty()) {
            Log::warning('Integration health failure with no admin recipients', [
                'partner' => $partner['key'] ?? null,
                'message' => $status['message'],
            ]);

            return;
        }

        $notify = app(NotificationService::class);
        $staff->pluck('email')->filter()->unique()->each(function (string $email) use ($notify, $subject, $body): void {
            $notify->sendEmail($email, $subject, $body, null, 'integration_health');
        });

        if ((bool) Setting::get('gateway.staff_sms_alerts', true)) {
            $staff->pluck('phone')->filter()->unique()->each(function (string $phone) use ($notify, $smsBody): void {
                $notify->sendSms($phone, $smsBody, null, 'integration_health');
            });
        }
    }
}
