<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Messaging\TransactionalMessagingService;
use App\Services\Messaging\WhatsApp\WhatsAppManager;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private readonly SmsManager $sms,
        private readonly TransactionalMessagingService $messaging,
        private readonly WhatsAppManager $whatsapp,
    ) {}

    /**
     * Send a raw SMS. Logs to notification_logs.
     */
    public function sendSms(string $phone, string $message, ?Customer $customer = null, ?string $templateCode = null): NotificationLog
    {
        if (! $this->messaging->channelEnabled('sms')
            || ($templateCode && ! $this->messaging->eventEnabled($templateCode))) {
            return $this->skippedLog('sms', $phone, $message, $customer, $templateCode);
        }

        $log = NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel'     => 'sms',
            'template'    => $templateCode,
            'recipient'   => $phone,
            'message'     => Str::limit($message, 800, ''),
            'status'      => 'queued',
        ]);

        $result = $this->sms->driver()->send($phone, $message);

        $log->update([
            'status'  => $result['ok'] ? 'sent' : 'failed',
            'sent_at' => $result['ok'] ? now() : null,
        ]);

        return $log;
    }

    /**
     * Send a raw email via Laravel mail. Logs to notification_logs.
     */
    public function sendEmail(string $email, string $subject, string $body, ?Customer $customer = null, ?string $templateCode = null): NotificationLog
    {
        if (! $this->messaging->channelEnabled('email')
            || ($templateCode && ! $this->messaging->eventEnabled($templateCode))) {
            return $this->skippedLog('email', $email, "[{$subject}] ".$body, $customer, $templateCode);
        }

        $log = NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel'     => 'email',
            'template'    => $templateCode,
            'recipient'   => $email,
            'message'     => "[{$subject}] ".Str::limit($body, 500, ''),
            'status'      => 'queued',
        ]);

        try {
            Mail::raw($body, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed']);
        }

        return $log;
    }

    public function sendWhatsApp(string $phone, string $message, ?Customer $customer = null, ?string $templateCode = null): NotificationLog
    {
        if (! $this->messaging->channelEnabled('whatsapp')
            || ($templateCode && ! $this->messaging->eventEnabled($templateCode))) {
            return $this->skippedLog('whatsapp', $phone, $message, $customer, $templateCode);
        }

        $log = NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel'     => 'whatsapp',
            'template'    => $templateCode,
            'recipient'   => $phone,
            'message'     => Str::limit($message, 800, ''),
            'status'      => 'queued',
        ]);

        $result = $this->whatsapp->driver()->send($phone, $message);

        $log->update([
            'status'  => $result['ok'] ? 'sent' : 'failed',
            'sent_at' => $result['ok'] ? now() : null,
        ]);

        return $log;
    }

    /**
     * Send to a customer via the best channels available, using a template lookup if provided.
     *
     * @param  array<string,mixed>  $vars
     */
    public function notifyCustomer(Customer $customer, string $templateCode, array $vars = []): void
    {
        if (! $this->messaging->eventEnabled($templateCode)) {
            return;
        }

        $tpl = NotificationTemplate::where('code', $templateCode)->where('is_active', true)->first();

        $body = $tpl ? $this->render($tpl->body, $vars) : ($vars['_fallback_body'] ?? '');
        $subject = $tpl ? $this->render((string) $tpl->subject, $vars) : ($vars['_fallback_subject'] ?? brand_name());

        if (! $body) {
            return;
        }

        $allowed = $this->messaging->allowedChannelsFor($templateCode);
        if ($allowed === []) {
            // Fall back to template channel if event has no channel list yet.
            $channel = $tpl?->channel ?? 'sms';
            $allowed = match ($channel) {
                'all' => ['sms', 'email', 'in_app'],
                default => [$channel],
            };
            $allowed = array_values(array_filter(
                $allowed,
                fn (string $ch) => $this->messaging->channelEnabled($ch)
            ));
        }

        foreach ($allowed as $channel) {
            if ($channel === 'sms' && $customer->phone) {
                $this->sendSms($customer->phone, $body, $customer, $templateCode);
            }
            if ($channel === 'email') {
                $email = $customer->email ?? optional($customer->user)->email;
                if ($email) {
                    $this->sendEmail($email, $subject, $body, $customer, $templateCode);
                }
            }
            if ($channel === 'whatsapp' && $customer->phone) {
                $this->sendWhatsApp($customer->phone, $body, $customer, $templateCode);
            }
            if ($channel === 'in_app') {
                $this->notifyInApp(
                    $customer,
                    $body,
                    'loan_updates',
                    $templateCode,
                    $subject,
                );
            }
        }
    }

    /**
     * @param  array{title_key?: string, body_key?: string, params?: array<string, mixed>}|null  $i18n
     *        When provided, title/body are re-translated at read time from these keys.
     */
    public function notifyInApp(
        Customer $customer,
        string $message,
        string $category = 'general',
        ?string $template = null,
        ?string $title = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?array $i18n = null,
    ): NotificationLog {
        if (! $this->messaging->channelEnabled('in_app')
            || ($template && ! $this->messaging->eventEnabled($template))) {
            return $this->skippedLog('in_app', 'in_app', $message, $customer, $template);
        }

        $payload = [
            'customer_id' => $customer->id,
            'channel'     => 'in_app',
            'category'    => $category,
            'template'    => $template,
            'recipient'   => $this->normalizeActionRecipient($actionUrl)
                ?: (string) ($customer->phone ?: $customer->email ?: 'in_app'),
            'message'     => Str::limit(trim(($title ? $title."\n" : '').$message), 800, ''),
            'status'      => 'sent',
            'sent_at'     => now(),
        ];

        if (is_array($i18n) && (filled($i18n['title_key'] ?? null) || filled($i18n['body_key'] ?? null))) {
            $payload['meta'] = [
                'title_key' => $i18n['title_key'] ?? null,
                'body_key'  => $i18n['body_key'] ?? null,
                'params'    => is_array($i18n['params'] ?? null) ? $i18n['params'] : [],
            ];
        }

        return NotificationLog::create($payload);
    }

    private function skippedLog(
        string $channel,
        string $recipient,
        string $message,
        ?Customer $customer,
        ?string $templateCode,
    ): NotificationLog {
        return NotificationLog::create([
            'customer_id' => $customer?->id,
            'channel'     => $channel,
            'template'    => $templateCode,
            'recipient'   => $recipient,
            'message'     => Str::limit('[skipped] '.$message, 800, ''),
            'status'      => 'skipped',
        ]);
    }

    /** Store CTA as a site-relative path so notification UIs can detect it. */
    private function normalizeActionRecipient(?string $actionUrl): ?string
    {
        if (! filled($actionUrl)) {
            return null;
        }

        if (str_starts_with($actionUrl, '/')) {
            return $actionUrl;
        }

        $path = parse_url($actionUrl, PHP_URL_PATH);
        if (! filled($path) || ! str_starts_with($path, '/')) {
            return null;
        }

        $query = parse_url($actionUrl, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }

    private function render(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $k => $v) {
            $out = str_replace(['{{ '.$k.' }}', '{{'.$k.'}}'], (string) $v, $out);
        }

        return $out;
    }
}
